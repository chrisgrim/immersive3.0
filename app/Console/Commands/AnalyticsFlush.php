<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use App\Support\Analytics\GeoLookup;
use Illuminate\Console\Command;
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

        if ($written > 0) {
            $this->info("Wrote {$written} analytics events.");
        }

        return self::SUCCESS;
    }

    /**
     * One multi-row insert; if that fails, row by row, so one bad note
     * cannot hold up the buffer for good. If no row goes in at all, the
     * database itself is the problem: rethrow, and the notes stay.
     */
    private function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            DB::table('analytics_events')->insert($rows);

            return count($rows);
        } catch (Throwable $batchError) {
            $written = 0;
            foreach ($rows as $row) {
                try {
                    DB::table('analytics_events')->insert($row);
                    $written++;
                } catch (Throwable $e) {
                    Log::warning('Analytics row skipped: '.$e->getMessage());
                }
            }

            if ($written === 0) {
                throw $batchError;
            }

            return $written;
        }
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

        return [
            'type' => mb_substr((string) $note['t'], 0, 32),
            'occurred_at' => $at->format('Y-m-d H:i:s'),
            'visitor' => $visitor,
            'bot' => $this->botFlags($ua, $ip, $day, $visitor) | (isset($this->hostingAsns[$this->geo->asn($ip) ?? 0]) ? Analytics::BOT_DATACENTER : 0),
            'event_id' => isset($data['event_id']) ? (int) $data['event_id'] : null,
            'source' => isset($data['source']) ? mb_substr((string) $data['source'], 0, 16) : null,
            'search_id' => isset($data['search_id']) && preg_match(Analytics::SEARCH_ID_PATTERN, (string) $data['search_id']) ? $data['search_id'] : null,
            'query' => isset($data['query']) ? mb_substr((string) $data['query'], 0, 255) : null,
            'results' => isset($data['results']) ? max(0, (int) $data['results']) : null,
            'country' => $this->geo->country($ip),
            'props' => ! empty($data['props']) ? json_encode($data['props']) : null,
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

    private function salt(string $day): string
    {
        return $this->salts[$day] ??= (function () use ($day) {
            $key = "analytics:salt:{$day}";
            Cache::add($key, bin2hex(random_bytes(16)), now()->addDays(2));

            return (string) Cache::get($key);
        })();
    }
}
