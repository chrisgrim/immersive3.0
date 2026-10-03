<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyticsPrune extends Command
{
    protected $signature = 'ei:analytics-prune';

    protected $description = 'Delete analytics_events rows older than analytics.raw_days (bots: bot_raw_days), in small chunks.';

    public function handle(): int
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
            ->whereIn('dim', ['query', 'ref', 'city', 'utm_source', 'utm_medium', 'utm_campaign'])
            ->where('visitors', '<', 3)
            ->delete();

        $this->info("Deleted {$deleted} old analytics rows.");

        return self::SUCCESS;
    }

    private function prune($cutoff, bool $bots = false): int
    {
        $deleted = 0;

        // Small chunks so one big delete never holds the table.
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
