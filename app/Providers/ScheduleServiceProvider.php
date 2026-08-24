<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('ei:check-closing-events')
                ->dailyAt('00:00')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/event-renewals.log'));

            $schedule->command('ei:publish-embargoed')
                ->everyTwoHours()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/publish-events.log'));

            $schedule->command('ei:archive-clicks')
                ->dailyAt('03:30')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/archive-clicks.log'));

            // Delete expired MCP API tokens a week after they expire.
            $schedule->command('sanctum:prune-expired --hours=168')
                ->dailyAt('04:00')
                ->withoutOverlapping();

            // Saved-search "notify me about new events" pilot — see
            // NotifySavedSearchMatchesCommand's own docblock.
            $schedule->command('ei:notify-saved-searches')
                ->twiceDaily(8, 20)
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/saved-search-notifications.log'));
        });
    }
}
