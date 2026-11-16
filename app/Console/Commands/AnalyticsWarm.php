<?php

namespace App\Console\Commands;

use App\Actions\Analytics\SearchConsoleReport;
use App\Actions\Analytics\SiteAnalyticsReport;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;

/**
 * Rebuilds the admin Analytics pages' numbers in the background (hourly),
 * so Local and Search open with them already there. A cold build of the
 * site's own report reads every raw row in the range (about 7 s for 30
 * days on prod), which is what the page used to wait on.
 */
class AnalyticsWarm extends Command
{
    protected $signature = 'ei:analytics-warm';

    protected $description = 'Rebuild the cached admin Analytics reports (Local and Search) for each range the pages offer.';

    /** The ranges the Local page offers (Analytics.vue dayOptions). */
    public const LOCAL_DAYS = [30, 7, 90];

    /** The ranges the Search page offers (AnalyticsSearch.vue dayOptions). */
    public const SEARCH_DAYS = [28, 7, 90, 180, 365, 480];

    public function handle(SiteAnalyticsReport $local, SearchConsoleReport $google): int
    {
        foreach (self::LOCAL_DAYS as $days) {
            try {
                $local->refresh($days);
            } catch (LockTimeoutException) {
                // Someone else is building one right now; the next run catches up.
                $this->warn("Local {$days} days: another build is running; skipped.");
            }
        }

        if ($google->configured()) {
            foreach (self::SEARCH_DAYS as $days) {
                try {
                    $google->warm($days);
                } catch (QueryException $e) {
                    // One slow range must not leave the ranges after it cold.
                    report($e);
                    $this->warn("Search {$days} days: the read failed; skipped.");
                }
            }
        }

        return self::SUCCESS;
    }
}
