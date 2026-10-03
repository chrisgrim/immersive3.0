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
        $written = 0;

        for ($i = 0; $i < (int) $this->option('batches'); $i++) {
            $notes = $analytics->pop(self::BATCH);
            if ($notes === []) {
                break;
            }

            try {
                $rows = array_values(array_filter(array_map(fn ($note) => $this->row($note), $notes)));
                $written += $this->insert($rows);
            } catch (Throwable $e) {
                // The cache or the database is down: the notes go back for
                // the next run.
                $analytics->putBack($notes);

                throw $e;
            }
        }

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
    private function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            DB::table('analytics_events')->insert($rows);

            return count($rows);
        } catch (QueryException $e) {
            if (! $this->isBadData($e)) {
                throw $e;
            }
        }

        $written = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            try {
                DB::table('analytics_events')->insert($row);
                $written++;
            } catch (QueryException $e) {
                if (! $this->isBadData($e)) {
                    throw $e;
                }
                $skipped++;
            }
        }

        Log::warning("Analytics: {$skipped} rows skipped as invalid.");

        return $written;
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

        $bot = $this->botFlags($ua, $ip, $day, $visitor) | (isset($this->hostingAsns[$this->geo->asn($ip) ?? 0]) ? Analytics::BOT_DATACENTER : 0);
        $type = mb_substr((string) $note['t'], 0, 32);
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
        ];
    }

    private function botFlags(string $ua, string $ip, string $day, string $visitor): int
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
        $network = substr(hash('sha256', $this->salt($day).'|'.$ip), 0, 16);
        if ($this->overCap("analytics:hits:{$day}:{$visitor}", (int) config('analytics.daily_cap'))
            | $this->overCap("analytics:ip-hits:{$day}:{$network}", (int) config('analytics.ip_daily_cap'))) {
            $flags |= Analytics::BOT_OVER_DAILY_CAP;
        }

        return $flags;
    }

    private function overCap(string $key, int $cap): bool
    {
        Cache::add($key, 0, now()->addDays(2));

        return Cache::increment($key) > $cap;
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
