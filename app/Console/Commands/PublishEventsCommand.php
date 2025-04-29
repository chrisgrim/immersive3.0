<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        
        $this->info("Publishing embargoed events");
        
        // Get all embargoed events
        $events = Event::whereNotNull('embargo_date')
            ->where('status', 'e')
            ->get();
        
        $count = $events->count();
        $this->info("Found {$count} embargoed events to check");
        
        if ($debug) {
            $this->info("Current server time: " . $now->toDateTimeString());
            $this->info("App timezone: " . Config::get('app.timezone', 'UTC'));
        }
        
        $publishedCount = 0;

        foreach ($events as $event) {
            // Get event's timezone or fall back to Etc/UTC (using the same Region/City format)
            $eventTimezone = $event->timezone ?? 'Etc/UTC';
            
            // Convert embargo_date to Carbon using the event's timezone
            $embargoDate = Carbon::parse($event->embargo_date, $eventTimezone);
            
            // Get current time in event's timezone
            $nowInEventTimezone = $now->copy()->setTimezone($eventTimezone);
            
            if ($debug) {
                $this->info("Event #{$event->id} ({$event->name}):");
                $this->info("- Timezone: {$eventTimezone}");
                $this->info("- Embargo: {$embargoDate->toDateTimeString()}");
                $this->info("- Current: {$nowInEventTimezone->toDateTimeString()}");
                $this->info("- Publish: " . ($embargoDate->lte($nowInEventTimezone) ? 'Yes' : 'No'));
            }
            
            // Check if embargo date has passed in the event's timezone
            if ($embargoDate->lte($nowInEventTimezone)) {
                if ($debug) {
                    $this->info("Publishing event #{$event->id}: {$event->name}");
                }
                
                
                $event->update([
                    'status' => 'p',
                    'embargo_date' => null,
                ]);
                
                $publishedCount++;
            }
        }

        // Clear caches only if we've made updates
        if ($publishedCount > 0) {
            $this->info("Published {$publishedCount} events");
            
            Cache::forget('active-categories');
            Cache::forget('active-genres');
        } else {
            $this->info("No events to publish at this time");
        }
        
        return Command::SUCCESS;
    }
}
