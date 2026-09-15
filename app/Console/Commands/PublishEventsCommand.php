<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Show;
use App\Services\EventNotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class PublishEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ei:publish-embargoed {--debug : Show extended debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish events that have passed their embargo date';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = Carbon::now();
        $debug = $this->option('debug');

        $this->info('Publishing embargoed events');

        // Get all embargoed events
        $events = Event::whereNotNull('embargo_date')
            ->where('status', 'e')
            ->get();

        $count = $events->count();
        $this->info("Found {$count} embargoed events to check");

        if ($debug) {
            $this->info('Current server time: '.$now->toDateTimeString());
            $this->info('App timezone: '.Config::get('app.timezone', 'UTC'));
        }

        $publishedCount = 0;

        foreach ($events as $event) {
            // embargo_date is a wall-clock time in the event's own timezone;
            // Event::embargoLiftsAt() is the one place that interprets it.
            $liftsAt = $event->embargoLiftsAt();

            if ($debug) {
                $this->info("Event #{$event->id} ({$event->name}):");
                $this->info('- Timezone: '.Show::validTimezone($event->timezone));
                $this->info("- Embargo: {$event->embargo_date} (lifts {$liftsAt->toIso8601String()})");
                $this->info("- Current: {$now->toIso8601String()}");
                $this->info('- Publish: '.($event->embargoIsPending($now) ? 'No' : 'Yes'));
            }

            if (! $event->embargoIsPending($now)) {
                if ($debug) {
                    $this->info("Publishing event #{$event->id}: {$event->name}");
                }

                $event->update([
                    'status' => 'p',
                    'embargo_date' => null,
                    'published_at' => $now,
                ]);

                app(EventNotificationDispatcher::class)->newEventFromFollowedOrganizer($event);

                $publishedCount++;
            }
        }

        // Clear caches only if we've made updates
        if ($publishedCount > 0) {
            $this->info("Published {$publishedCount} events");

            Cache::forget('active-categories');
            Cache::forget('active-genres');
        } else {
            $this->info('No events to publish at this time');
        }

        return Command::SUCCESS;
    }
}
