<?php

namespace App\Console\Commands;

use App\Support\Analytics\Analytics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Jaybizzle\CrawlerDetect\CrawlerDetect;

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

    public function handle(Analytics $analytics): int
    {
        if (! config('analytics.enabled')) {
            return self::SUCCESS;
        }

        $this->crawlers = new CrawlerDetect;
        $written = 0;

        for ($i = 0; $i < (int) $this->option('batches'); $i++) {
            $notes = $analytics->pop(self::BATCH);
            if ($notes === []) {
                break;
            }

            $rows = array_values(array_filter(array_map(fn ($note) => $this->row($note), $notes)));
            if ($rows !== []) {
                DB::table('analytics_events')->insert($rows);
                $written += count($rows);
            }
        }

        if ($written > 0) {
            $this->info("Wrote {$written} analytics events.");
        }

        return self::SUCCESS;
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
        $visitor = substr(hash('sha256', $this->salt($day).'|'.($note['ip'] ?? '').'|'.$ua), 0, 16);
        $data = is_array($note['d'] ?? null) ? $note['d'] : [];

        return [
            'type' => mb_substr((string) $note['t'], 0, 32),
            'occurred_at' => $at->format('Y-m-d H:i:s'),
            'visitor' => $visitor,
            'bot' => $this->botFlags($ua, $day, $visitor),
            'event_id' => isset($data['event_id']) ? (int) $data['event_id'] : null,
            'source' => isset($data['source']) ? mb_substr((string) $data['source'], 0, 16) : null,
            'query' => isset($data['query']) ? mb_substr((string) $data['query'], 0, 255) : null,
            'results' => isset($data['results']) ? max(0, (int) $data['results']) : null,
            'country' => null,
            'props' => isset($data['props']) ? json_encode($data['props']) : null,
        ];
    }

    private function botFlags(string $ua, string $day, string $visitor): int
    {
        $flags = 0;

        if (trim($ua) === '') {
            $flags |= Analytics::BOT_NO_USER_AGENT;
        } elseif ($this->crawlers->isCrawler($ua)) {
            $flags |= Analytics::BOT_CRAWLER;
        }

        $key = "analytics:hits:{$day}:{$visitor}";
        Cache::add($key, 0, now()->addDays(2));
        if (Cache::increment($key) > (int) config('analytics.daily_cap')) {
            $flags |= Analytics::BOT_OVER_DAILY_CAP;
        }

        return $flags;
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
