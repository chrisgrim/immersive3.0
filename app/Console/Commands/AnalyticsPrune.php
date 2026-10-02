<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyticsPrune extends Command
{
    protected $signature = 'ei:analytics-prune';

    protected $description = 'Delete analytics_events rows older than analytics.raw_days, in small chunks.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('analytics.raw_days'));
        $deleted = 0;

        // Small chunks so one big delete never holds the table.
        do {
            $batch = DB::table('analytics_events')->where('occurred_at', '<', $cutoff)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} analytics rows older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
