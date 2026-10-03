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
        $deleted = $this->prune(now()->subDays((int) config('analytics.raw_days')))
            + $this->prune(now()->subDays((int) config('analytics.bot_raw_days')), bots: true);

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
