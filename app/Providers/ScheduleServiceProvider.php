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

            // OAuth housekeeping for the MCP server (Passport). purge drops
            // revoked and expired tokens and codes; the second sweeps client
            // registrations that never went on to obtain a token.
            $schedule->command('passport:purge')
                ->dailyAt('04:00')
                ->timezone('America/Los_Angeles');

            $schedule->command('mcp:prune-oauth-clients')
                ->dailyAt('04:10')
                ->timezone('America/Los_Angeles');

            $schedule->command('model:prune', ['--model' => [\App\Models\McpToolCall::class]])
                ->dailyAt('04:20')
                ->timezone('America/Los_Angeles');

            // Saved-search "notify me about new events" pilot — see
            // NotifySavedSearchMatchesCommand's own docblock.
            $schedule->command('ei:notify-saved-searches')
                ->twiceDaily(8, 20)
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/saved-search-notifications.log'));
        });
    }
}
