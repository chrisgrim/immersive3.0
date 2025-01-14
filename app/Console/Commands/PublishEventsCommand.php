<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PublishEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ei:publish-embargoed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish events that have an embargo date';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $events = Event::whereNotNull('embargo_date')->get();
        $hasUpdates = false;

        foreach ($events as $event) {
            if ($event->embargo_date <= Carbon::now() && $event->status == 'e') {
                $event->update([
                    'status' => 'p',
                    'embargo_date' => null,
                ]);
                $hasUpdates = true;
            }
        }

        // Just clear the caches
        Cache::forget('active-categories');
        Cache::forget('active-genres');
    }
}
