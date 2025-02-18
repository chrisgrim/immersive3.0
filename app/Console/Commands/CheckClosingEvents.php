<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventClosingSoon;
use Carbon\Carbon;

class CheckClosingEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ei:check-closing-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if events are closing in 3 days and notify creators';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check for events closing between 4 and 5 days from now
        $from = Carbon::now()->addDays(4)->startOfDay();
        $to = Carbon::now()->addDays(5)->endOfDay();
                
        $events = Event::where('status', 'p')
            ->whereBetween('closingDate', [$from, $to])
            ->with(['organizer.user']) // Eager load relationships
            ->get();

        if ($events->isEmpty()) {
            return Command::SUCCESS;
        }

        foreach ($events as $event) {
            try {
                if (!$event->organizer?->user) {
                    continue;
                }

                $user = $event->organizer->user;
                
                // Skip if user has silenced emails
                if ($user->silence === 'y') {
                    continue;
                }

                Mail::to($user->email)->send(new EventClosingSoon($event));
                
            } catch (\Exception $e) {
                $this->error("Failed to send email for event {$event->id}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
