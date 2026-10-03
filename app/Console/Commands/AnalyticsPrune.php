<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsPrune extends Command
{
    protected $signature = 'ei:analytics-prune';

    protected $description = 'Delete analytics_events rows older than analytics.raw_days (bots: bot_raw_days), in small chunks.';

    public function handle(): int
    {
        // Not while a rollup runs: deleting raw rows between its statements
        // would leave one day's dimensions counting different rows. Shares
        // the rollup's lock; waits up to 25 minutes, else tries next night.
        try {
            return Cache::lock('analytics:rollup', 3600)->block(1500, fn () => $this->pruneAll());
        } catch (LockTimeoutException) {
            $this->warn('A rollup is still running; skipped.');

            return self::SUCCESS;
        }
    }

    private function pruneAll(): int
    {
        // People's rows: 13 months. Bot rows: 30 days, since analytics_daily
        // keeps their counts (rolled up nightly, well inside 30 days).
        // Defaults and a floor: a config cache from older code (no
        // bot_raw_days) must never turn into "delete everything".
        $deleted = $this->prune(now()->subDays(max(30, (int) config('analytics.raw_days', 395))))
            + $this->prune(now()->subDays(max(7, (int) config('analytics.bot_raw_days', 30))), bots: true);

        // Daily totals are kept for good, except text people typed or sent
        // (places, nav searches, referring sites, cities, campaign tags) that
        // fewer than three people shared: those go with the raw rows, so no
        // one person's typed text outlives 13 months.
        $deleted += DB::table('analytics_daily')
            ->where('day', '<', now()->subDays(max(30, (int) config('analytics.raw_days', 395)))->toDateString())
            ->whereIn('dim', ['query', 'ref', 'city', 'utm_source', 'utm_medium', 'utm_campaign', 'path'])
            ->where('visitors', '<', 3)
            ->delete();

        $this->info("Deleted {$deleted} old analytics rows.");

        return self::SUCCESS;
    }

    private function prune($cutoff, bool $bots = false): int
    {
        $deleted = 0;

        // Small chunks so one big delete never holds the table. Bot rows are
        // found through (bot, occurred_at): only the last month of them
        // exists, rather than every person's row of the past 13 months.
        do {
            $batch = DB::table('analytics_events')
                ->where('occurred_at', '<', $cutoff)
                ->when($bots, fn ($query) => $query->where('bot', '>', 0))
                ->limit(5000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
