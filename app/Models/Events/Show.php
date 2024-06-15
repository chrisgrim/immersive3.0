<?php

namespace App\Models\Events;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Scopes\DateScope;

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

    // Delete all old shows and their tickets if showtype is 'a' or delete those not in the new date array if 's'
    $showsToDelete = $event->shows();
    if ($request->showtype === 's') {
        $showsToDelete = $showsToDelete->whereNotIn('date', $request->dateArray);
    }

    // Get the shows to delete
    $showsToDelete = $showsToDelete->get();

    $showsToDelete->each(function ($show) {
        $show->tickets()->delete();
        $show->delete();
    });

    // Handle show creation based on showtype
    if ($request->showtype === 's') {
        foreach ($request->dateArray as $date) {
            self::createOrUpdateShow($date, $event->id, $old_tickets);
        }
    } elseif ($request->showtype === 'a') {
        $sixMonthsFromNow = Carbon::now()->addMonths(6)->format('Y-m-d H:i:s');
        self::createOrUpdateShow($sixMonthsFromNow, $event->id, $old_tickets);
    }

    // Update the event's showtype to the new value
    $event->update(['showtype' => $request->showtype]);
}

private static function createOrUpdateShow($date, $eventId, $oldTickets)
{
    // Format the date to match the datetime column in Laravel
    $formattedDate = \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');

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
    public static function updateEvent($request, $event)
    {
        //  get the last date based on show type
        if ($request->shows) {
            $type = 's';
            $lastDate = $event->shows()->orderBy('date', 'DESC')->first()->date;
        }
        if ($request->limited) {
            $type = 'l';
            $lastDate = $event->shows()->orderBy('date', 'DESC')->first()->date;
        }
        if ($request->onGoing) {
            $type = 'o';
            $lastDate = $event->shows()->orderBy('date', 'DESC')->first()->date;
        }
        if ($request->always) {
            $type = 'a';
            $lastDate = Carbon::now()->addMonths(6)->format('Y-m-d H:i:s');
        }

        if ($event->status === 'e' && $request->embargo_date === null ) {
            return $event->update([
                'show_times' => $request->showTimes,
                'embargo_date' => $request->embargoDate,
                'closingDate' => $lastDate,
                'showtype' => $type,
                'timezone_id' => $request->timezone ? $request->timezone['id'] : null,
                'status' => 'p'
            ]);
        }

        if ($event->status === 'p' && $request->embargo_date ) {
            $event->update([
                'show_times' => $request->showTimes,
                'embargo_date' => $request->embargoDate,
                'closingDate' => $lastDate,
                'showtype' => $type,
                'timezone_id' => $request->timezone ? $request->timezone['id'] : null,
                'status' => 'e'
            ]);
            return $event->unsearchable();
        }
        
        $event->update([
            'show_times' => $request->showTimes,
            'embargo_date' => $request->embargoDate,
            'closingDate' => $lastDate,
            'showtype' => $type,
            'timezone_id' => $request->timezone ? $request->timezone['id'] : null,
        ]);
    }
}
