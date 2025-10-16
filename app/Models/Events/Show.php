<?php

namespace App\Models\Events;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\DateScope;
use App\Models\Event;

class Show extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
	protected $fillable = ['date','event_id'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new DateScope);
    }

	/**
     * Show Model belongs to the Event Model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function event() 
    {
        return $this->belongsTo(Event::class);
    }
    
    /**
     * Each Show has many tickets 
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets() 
    {
        return $this->morphMany(Ticket::class, 'ticket');
    }

    
    public static function saveShows($request, $event)
    {
        // Check if tickets exist for this show and save them as $old_tickets
        $firstShow = $event->shows()->first();
        $old_tickets = $firstShow && $firstShow->tickets()->exists() ? $firstShow->tickets()->get() : null;

        // Always delete all existing shows when changing showtype
        if ($request->showtype !== $event->showtype) {
            $event->shows()->each(function ($show) {
                $show->tickets()->delete();
                $show->delete();
            });
        } else {
            // Only delete shows not in the new date array if staying on same showtype
            $showsToDelete = $event->shows();
            if ($request->showtype === 's' || $request->showtype === 'o') {
                // For specific and ongoing dates, only delete shows not in the new date array
                $showsToDelete = $showsToDelete->whereNotIn('date', $request->dateArray);
            }
            $showsToDelete->get()->each(function ($show) {
                $show->tickets()->delete();
                $show->delete();
            });
        }

        // Handle show creation based on showtype
        if ($request->showtype === 's' || $request->showtype === 'o') {
            // For specific dates ('s') and ongoing dates ('o'), create individual shows
            foreach ($request->dateArray as $date) {
                self::createOrUpdateShow($date, $event->id, $old_tickets);
            }
        } elseif ($request->showtype === 'a') {
            // For 'always available' shows, use custom end date if provided
            $endDate = isset($request->always_config) && $request->always_config['endDate']
                ? Carbon::parse($request->always_config['endDate'])->format('Y-m-d H:i:s')
                : Carbon::now()->addMonths(6)->format('Y-m-d H:i:s');
            self::createOrUpdateShow($endDate, $event->id, $old_tickets);
        } elseif ($request->showtype === 'l') {
            // For 'limited availability' shows
            $sixMonthsFromNow = Carbon::now()->addMonths(6)->format('Y-m-d H:i:s');
            self::createOrUpdateShow($sixMonthsFromNow, $event->id, $old_tickets);
        }

        // Update the event's showtype to the new value
        $event->update(['showtype' => $request->showtype]);
        
        // Force reindex the event in Elasticsearch
        if ($event->shouldBeSearchable()) {
            $event->searchable();
        }
    }

    private static function createOrUpdateShow($date, $eventId, $oldTickets)
    {
        // Date arrives from frontend as UTC timestamp in 'Y-m-d H:i:s' format
        // Parse it as UTC to preserve the exact date selected by the user
        $formattedDate = \Carbon\Carbon::parse($date, 'UTC')->format('Y-m-d H:i:s');

        $show = self::updateOrCreate([
            'date' => $formattedDate,
            'event_id' => $eventId
        ]);

        // If tickets were already entered, add them to the new dates
        if ($oldTickets) {
            foreach ($oldTickets as $ticket) {
                $show->tickets()->updateOrCreate([
                    'name' => $ticket['name'],
                ], [
                    'description' => $ticket['description'],
                    'currency' => $ticket['currency'],
                    'ticket_price' => $ticket['ticket_price'],
                    'type' => $ticket['type']
                ]);
            }
        }
    }


    /**
     * Get the showtimes and price range to update the event model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public static function updateEvent($request, Event $event)
    {
        // Determine show type and last date
        $type = self::determineShowType($request);
        $lastDate = self::calculateLastDate($event, $type, $request);

        // Prepare update data
        $updateData = [
            'show_times' => $request->show_times,
            'embargo_date' => $request->embargo_date,
            'closingDate' => $lastDate,
            'showtype' => $type,
        ];
        
        // Include timezone if provided
        if ($request->timezone) {
            $updateData['timezone'] = $request->timezone;
        }

        // Handle embargo status changes
        if ($event->status === 'e' && !$request->embargo_date) {
            $updateData['status'] = 'p';
        } elseif ($event->status === 'p' && $request->embargo_date) {
            $updateData['status'] = 'e';
            $event->unsearchable();
        }

        // Update the event
        $event->update($updateData);
        
        // Force reindex the event in Elasticsearch
        if ($event->shouldBeSearchable()) {
            $event->searchable();
        }
    }

    private static function determineShowType($request): string
    {
        return $request->showtype ?? 's';
    }

    private static function calculateLastDate(Event $event, string $type, $request = null): string
    {
        // Get the event's timezone, default to UTC
        $timezone = $request->timezone ?? $event->timezone ?? 'UTC';
        
        if ($type === 'a') {
            // For 'always available' shows, check if there's a specific end date in the configuration
            if ($request && isset($request->always_config) && $request->always_config['endDate']) {
                // Parse in UTC (frontend already converted)
                return Carbon::parse($request->always_config['endDate'], 'UTC')->endOfDay()->format('Y-m-d H:i:s');
            }
            
            // Default for always shows: 6 months from now in the event's timezone
            return Carbon::now($timezone)->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
        }
        
        if ($type === 'l') {
            // For 'limited availability' shows
            return Carbon::now($timezone)->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
        }
        
        if ($type === 'o') {
            // For ongoing shows, check if there's a specific end date in the configuration
            if ($request && isset($request->ongoing_config) && $request->ongoing_config['endDate']) {
                // Parse in UTC (frontend already converted)
                return Carbon::parse($request->ongoing_config['endDate'], 'UTC')->endOfDay()->format('Y-m-d H:i:s');
            }
            
            // Default for ongoing shows: 6 months from now in the event's timezone
            return Carbon::now($timezone)->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
        }
        
        // For single shows, get the last date from shows
        $lastShow = $event->shows()
            ->orderBy('date', 'DESC')
            ->first();
        
        // If we have a last show, use end of day for that date; otherwise use current date
        if ($lastShow) {
            return Carbon::parse($lastShow->date, 'UTC')->endOfDay()->format('Y-m-d H:i:s');
        }
        
        return Carbon::now($timezone)->endOfDay()->format('Y-m-d H:i:s');
    }
}
