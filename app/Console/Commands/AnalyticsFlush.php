<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use App\Support\Analytics\GeoLookup;
use hisorange\BrowserDetect\Facade as Browser;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Throwable;

/**
 * Moves the analytics buffer into analytics_events, a batch at a time. This
 * is where the slow parts live, so a page view never pays for them:
 *
 * - visitor: a hash of IP + user agent with a per-day random salt (kept two
 *   days, then gone), so a visitor can be counted within a day but never
 *   recognised later, and the IP itself is never stored.
 * - bot: flags, not a filter (see Analytics::BOT_*). Reports read bot = 0.
 * - load pings: not rows; each marks its page view as browser confirmed.
 */
class AnalyticsFlush extends Command
{
    protected $signature = 'ei:analytics-flush {--batches=20 : At most this many batches of 1000 per run}';

    protected $description = 'Write buffered analytics notes to analytics_events.';

    private const BATCH = 1000;

    private CrawlerDetect $crawlers;

    /** @var array<string, string> day => salt, for this run */
    private array $salts = [];

    private GeoLookup $geo;

    /** @var array<string, array> user agent hash => device() result, for this run */
    private array $devices = [];

    /** @var array<string, int> visitor => last page view timestamp, for the live set */
    private array $live = [];

    /** @var array<int, true> */
    private array $hostingAsns = [];

    /** Whether analytics_events.js exists yet (Analytics::hasConfirmationColumns). */
    private bool $jsColumn = false;

    /** @var array<string, int> daily cap key => hits counted this batch, not yet saved */
    private array $tally = [];

    /** @var list<string> cap keys the current note counted toward */
    private array $counted = [];

    public function handle(Analytics $analytics, GeoLookup $geo): int
    {
        if (! config('analytics.enabled')) {
            // Switched off: whatever is still buffered holds raw IPs and will
            // never be written, so it goes.
            $analytics->clear();

            return self::SUCCESS;
        }

        $this->crawlers = new CrawlerDetect;
        $this->geo = $geo;
        $this->hostingAsns = array_fill_keys(config('analytics.hosting_asns'), true);
        $this->jsColumn = Analytics::hasConfirmationColumns();
        $written = 0;

        // Load pings not matched to their page view yet, carried from batch
        // to batch (the view may come in a later one).
        $pings = [];

        for ($i = 0; $i < (int) $this->option('batches'); $i++) {
            $notes = $analytics->pop(self::BATCH);
            if ($notes === []) {
                break;
            }

            // Daily cap hits are saved only for rows that were written, so a
            // batch that goes back and is retried is not counted twice.
            $rows = $sources = $hits = $batchPings = [];
            $done = 0;
            $inserting = false;
            try {
                foreach ($notes as $note) {
                    // A load ping is not a row: it marks its page view below.
                    if (($ping = $this->ping($note)) !== null) {
                        $batchPings[] = $ping;

                        continue;
                    }
                    $this->counted = [];
                    if (($row = $this->row($note)) !== null) {
                        $rows[] = $row;
                        $sources[] = $note;
                        $hits[] = $this->counted;
                    }
                }
                $inserting = true;
                $written += $this->insert($rows, $done);
                // After the inserts, so a ping in the same batch as its view
                // (or ahead of it) still finds it.
                $pings = $this->markPings([...$pings, ...$batchPings]);
            } catch (Throwable $e) {
                // The cache or the database is down: what was not written
                // goes back for the next run, pings included (marking is
                // idempotent, so one marked before the failure does no harm).
                $back = $inserting ? [...array_slice($sources, $done), ...array_column($batchPings, 'note')] : $notes;
                $analytics->putBack([...array_column($pings, 'note'), ...$back]);
                $this->saveHits(array_slice($hits, 0, $done));

                throw $e;
            }
            $this->saveHits($hits);
        }

        $this->retryPings($analytics, $pings);

        if ($this->live !== [] || Analytics::captures('live')) {
            $analytics->markLive($this->live);
        }

        if ($written > 0) {
            $this->info("Wrote {$written} analytics events.");
        }

        return self::SUCCESS;
    }

    /**
     * One multi-row insert. Only if the database rejected the data itself
     * (SQLSTATE class 22 or 23: a value it will never accept) does it go row
     * by row, so one bad note cannot hold up the buffer for good. Anything
     * else (connection lost, timeout, deadlock) is rethrown at once and the
     * whole batch goes back for the next run.
     */
    private function insert(array $rows, int &$done): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            DB::table('analytics_events')->insert($rows);
            $done = count($rows);

            return $done;
        } catch (QueryException $e) {
            if (! $this->isBadData($e)) {
                throw $e;
            }
        }

        $written = 0;
        $skipped = 0;
        foreach ($rows as $i => $row) {
            try {
                DB::table('analytics_events')->insert($row);
                $written++;
            } catch (QueryException $e) {
                if (! $this->isBadData($e)) {
                    throw $e;
                }
                $skipped++;
            }
            // Rows before $done are settled (written or dropped for good).
            $done = $i + 1;
        }

        Log::warning("Analytics: {$skipped} rows skipped as invalid.");

        return $written;
    }

    /** Tries a load ping gets (one per run) before it is given up on. */
    private const PING_TRIES = 3;

    /**
     * A load ping note as [note, view_id, webdriver, tries], or null for any
     * other note. A ping without a valid view id gets view_id null, and
     * markPings drops it.
     */
    private function ping(string $note): ?array
    {
        $decoded = json_decode($note, true);
        if (! is_array($decoded) || ($decoded['t'] ?? null) !== Analytics::PAGE_PING) {
            return null;
        }

        $viewId = $decoded['d']['view_id'] ?? null;

        return [
            'note' => $note,
            'view_id' => is_string($viewId) && preg_match(Analytics::SEARCH_ID_PATTERN, $viewId) ? $viewId : null,
            'webdriver' => ! empty($decoded['d']['webdriver']),
            'tries' => (int) ($decoded['r'] ?? 0),
        ];
    }

    /**
     * Marks the page views these pings confirm (js = 1). A browser that said
     * it is automated flags its visitor's whole day so far
     * (BOT_AUTOMATION): searches and clicks from a script are not a
     * person's either. One lookup and one UPDATE per 500 view ids (view_id
     * index), one per 500 automated visitors and day (visitor index).
     *
     * @return list<array> the pings whose page view is not written yet
     */
    private function markPings(array $pings): array
    {
        $pings = array_values(array_filter($pings, fn ($ping) => $ping['view_id'] !== null));
        if ($pings === []) {
            return [];
        }

        $found = [];
        foreach (array_chunk(array_values(array_unique(array_column($pings, 'view_id'))), 500) as $ids) {
            foreach (DB::table('analytics_events')->where('type', Analytics::PAGE_VIEW)->whereIn('view_id', $ids)
                ->get(['view_id', 'visitor', 'occurred_at']) as $view) {
                $found[$view->view_id] = [$view->visitor, substr((string) $view->occurred_at, 0, 10)];
            }
        }

        // Before the migration has run there is no js column to mark.
        if ($this->jsColumn) {
            foreach (array_chunk(array_keys($found), 500) as $ids) {
                DB::table('analytics_events')->where('type', Analytics::PAGE_VIEW)->whereIn('view_id', $ids)->update(['js' => 1]);
            }
        }

        $automated = [];
        foreach ($pings as $ping) {
            if ($ping['webdriver'] && isset($found[$ping['view_id']])) {
                [$visitor, $day] = $found[$ping['view_id']];
                $automated[$day][$visitor] = true;
            }
        }
        foreach ($automated as $day => $visitors) {
            $from = Carbon::parse($day, 'UTC');
            foreach (array_chunk(array_keys($visitors), 500) as $chunk) {
                DB::table('analytics_events')->whereIn('visitor', $chunk)
                    ->where('occurred_at', '>=', $from->format('Y-m-d H:i:s'))
                    ->where('occurred_at', '<', $from->copy()->addDay()->format('Y-m-d H:i:s'))
                    ->update(['bot' => DB::raw('bot | '.Analytics::BOT_AUTOMATION)]);
            }
        }

        return array_values(array_filter($pings, fn ($ping) => ! isset($found[$ping['view_id']])));
    }

    /**
     * Pings whose page view was not found this run go back to the buffer
     * for the next one (the view may still be buffered, or put back after a
     * failed insert), up to PING_TRIES runs in all.
     */
    private function retryPings(Analytics $analytics, array $pings): void
    {
        $again = [];
        foreach ($pings as $ping) {
            if ($ping['tries'] + 1 < self::PING_TRIES) {
                $note = json_decode($ping['note'], true);
                $note['r'] = $ping['tries'] + 1;
                $again[] = json_encode($note, JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }

        if ($again !== []) {
            $analytics->putBack($again);
        }
    }

    private function isBadData(QueryException $e): bool
    {
        return in_array(substr((string) $e->getCode(), 0, 2), ['22', '23'], true);
    }

    private function row(string $note): ?array
    {
        $note = json_decode($note, true);
        if (! is_array($note) || ! isset($note['t'], $note['at'])) {
            return null;
        }

        $at = Carbon::createFromTimestamp((int) $note['at'], 'UTC');
        $day = $at->toDateString();
        $ua = (string) ($note['ua'] ?? '');
        $ip = (string) ($note['ip'] ?? '');
        $visitor = substr(hash('sha256', $this->salt($day).'|'.$ip.'|'.$ua), 0, 16);
        $data = is_array($note['d'] ?? null) ? $note['d'] : [];

        $type = mb_substr((string) $note['t'], 0, 32);
        $bot = $this->botFlags($ua, $ip, $day, $visitor, $type)
            | (isset($this->hostingAsns[$this->geo->asn($ip) ?? 0]) ? Analytics::BOT_DATACENTER : 0)
            | (Analytics::missingBrowserHeaders($ua, $note['h'] ?? null) ? Analytics::BOT_HEADERS : 0);
        $text = fn ($key, $max) => isset($data[$key]) && is_scalar($data[$key]) && (string) $data[$key] !== '' ? mb_substr((string) $data[$key], 0, $max) : null;
        $utm = is_array($data['utm'] ?? null) ? $data['utm'] : [];
        $device = $bot === 0 && Analytics::captures('device') ? $this->device($ua) : [];
        $place = $bot === 0 && Analytics::captures('city') ? $this->geo->place($ip) : [];

        if ($bot === 0 && $type === Analytics::PAGE_VIEW && Analytics::captures('live')) {
            $this->live[$visitor] = max($this->live[$visitor] ?? 0, $at->getTimestamp());
        }

        return [
            'type' => $type,
            'occurred_at' => $at->format('Y-m-d H:i:s'),
            'visitor' => $visitor,
            'bot' => $bot,
            'event_id' => isset($data['event_id']) ? (int) $data['event_id'] : null,
            'source' => isset($data['source']) ? mb_substr((string) $data['source'], 0, 16) : null,
            'search_id' => isset($data['search_id']) && preg_match(Analytics::SEARCH_ID_PATTERN, (string) $data['search_id']) ? $data['search_id'] : null,
            'query' => isset($data['query']) ? mb_substr((string) $data['query'], 0, 255) : null,
            'results' => isset($data['results']) ? max(0, (int) $data['results']) : null,
            'country' => $this->geo->country($ip),
            'props' => ! empty($data['props']) ? json_encode($data['props']) : null,
            'page' => $text('page', 32),
            'path' => $text('path', 191),
            'view_id' => isset($data['view_id']) && preg_match(Analytics::SEARCH_ID_PATTERN, (string) $data['view_id']) ? $data['view_id'] : null,
            'organizer_id' => isset($data['organizer_id']) && is_numeric($data['organizer_id']) ? (int) $data['organizer_id'] : null,
            'seconds' => isset($data['seconds']) && is_numeric($data['seconds']) ? min(1800, max(0, (int) $data['seconds'])) : null,
            'depth' => isset($data['depth']) && is_numeric($data['depth']) ? min(100, max(0, (int) $data['depth'])) : null,
            'utm_source' => isset($utm['source']) && is_string($utm['source']) ? mb_substr($utm['source'], 0, 64) : null,
            'utm_medium' => isset($utm['medium']) && is_string($utm['medium']) ? mb_substr($utm['medium'], 0, 64) : null,
            'utm_campaign' => isset($utm['campaign']) && is_string($utm['campaign']) ? mb_substr($utm['campaign'], 0, 64) : null,
            'device' => $device['device'] ?? null,
            'browser' => $device['browser'] ?? null,
            'os' => $device['os'] ?? null,
            'city' => $place['city'] ?? null,
            'region' => $place['region'] ?? null,
        ] + ($this->jsColumn ? [
            // 0: the page asked its browser for a load ping (see markPings).
            'js' => $type === Analytics::PAGE_VIEW && isset($data['js']) ? 0 : null,
        ] : []);
    }

    /**
     * Rows that count toward the daily caps: a visit, a search, a click.
     * Nav search keystrokes and time-on-page notes are side effects of one
     * visit; counting them would push an ordinary heavy user over the cap.
     */
    private const CAPPED_TYPES = [Analytics::PAGE_VIEW, Analytics::EVENT_VIEW, Analytics::SEARCH, Analytics::TICKET_CLICK, Analytics::SEARCH_CLICK];

    private function botFlags(string $ua, string $ip, string $day, string $visitor, string $type): int
    {
        $flags = 0;

        if (trim($ua) === '') {
            $flags |= Analytics::BOT_NO_USER_AGENT;
        } elseif ($this->crawlers->isCrawler($ua)) {
            $flags |= Analytics::BOT_CRAWLER;
        }

        // Per visitor, and per IP address alone: a script that changes its
        // user agent on every hit is a new "visitor" each time, but not a
        // new address. The IP cap is higher, for offices and shared networks.
        // Rows of other types only read the counts (a capped visitor's
        // keystrokes are flagged too), without adding to them. Nav typing
        // also counts toward its own, higher caps, so a loop of it is caught.
        $network = substr(hash('sha256', $this->salt($day).'|'.$ip), 0, 16);
        $counts = in_array($type, self::CAPPED_TYPES, true);
        if ($this->overCap("analytics:hits:{$day}:{$visitor}", (int) config('analytics.daily_cap'), $counts)
            | $this->overCap("analytics:ip-hits:{$day}:{$network}", (int) config('analytics.ip_daily_cap'), $counts)) {
            $flags |= Analytics::BOT_OVER_DAILY_CAP;
        }
        if ($type === Analytics::NAV_SEARCH
            && ($this->overCap("analytics:nav-hits:{$day}:{$visitor}", (int) config('analytics.nav_daily_cap'))
                | $this->overCap("analytics:nav-ip-hits:{$day}:{$network}", (int) config('analytics.nav_ip_daily_cap')))) {
            $flags |= Analytics::BOT_OVER_DAILY_CAP;
        }

        return $flags;
    }

    private function overCap(string $key, int $cap, bool $counts = true): bool
    {
        $seen = (int) Cache::get($key, 0) + ($this->tally[$key] ?? 0);
        if (! $counts) {
            return $seen > $cap;
        }

        $this->tally[$key] = ($this->tally[$key] ?? 0) + 1;
        $this->counted[] = $key;

        return $seen + 1 > $cap;
    }

    /**
     * Adds the cap hits of written rows to the daily counters and forgets
     * the rest of this batch's tally.
     *
     * @param  list<list<string>>  $hits  cap keys per written row
     */
    private function saveHits(array $hits): void
    {
        $this->tally = [];
        foreach (array_count_values(array_merge(...$hits)) as $key => $count) {
            Cache::add($key, 0, now()->addDays(2));
            Cache::increment($key, $count);
        }
    }

    /**
     * Device type and browser/OS family (never versions), from the user
     * agent. hisorange caches each parse; this run memoizes per agent too.
     *
     * @return array{device: string, browser: ?string, os: ?string}
     */
    private function device(string $ua): array
    {
        return $this->devices[md5($ua)] ??= (function () use ($ua) {
            try {
                $result = Browser::parse($ua);
                $family = fn (?string $name) => $name !== null && $name !== '' && $name !== 'Unknown' ? mb_substr($name, 0, 32) : null;

                return [
                    'device' => $result->isMobile() ? 'mobile' : ($result->isTablet() ? 'tablet' : ($result->isDesktop() ? 'desktop' : 'other')),
                    'browser' => $family($result->browserFamily()),
                    'os' => $family($result->platformFamily()),
                ];
            } catch (Throwable) {
                return ['device' => 'other', 'browser' => null, 'os' => null];
            }
        })();
    }

    private function salt(string $day): string
    {
        return $this->salts[$day] ??= (function () use ($day) {
            $key = "analytics:salt:{$day}";
            Cache::add($key, bin2hex(random_bytes(16)), now()->addDays(2));

            return (string) Cache::get($key);
        })();
    }
}
