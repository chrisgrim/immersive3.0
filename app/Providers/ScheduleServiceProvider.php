<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
                
            // Run the publish-embargoed command every 2 hours
            // We handle event timezones properly, so checking every 2 hours
            // is sufficient to ensure events go live at appropriate times
            $schedule->command('ei:publish-embargoed')
                ->everyTwoHours()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/publish-events.log'));
        });
    }
}
