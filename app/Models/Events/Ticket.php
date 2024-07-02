<?php

namespace App\Models\Events;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
	protected $fillable = ['name','ticket_price','ticket_id', 'ticket_type', 'description','type', 'currency'];
    
	/**
     * Ticket Belongs to the Show Model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function ticket()
    {
        return $this->morphTo();
    }

    public static function handleTickets(\Illuminate\Http\Request $request, Event $event)
{
    foreach ($event->shows as $show) {
        $submittedTicketNames = collect($request->tickets)->pluck('name')->all();

        $show->tickets()->whereNotIn('name', $submittedTicketNames)->delete();

        foreach ($request->tickets as $ticketData) {
            $show->tickets()->updateOrCreate(
                ['name' => $ticketData['name']],
                [
                    'description' => $ticketData['description'],
                    'currency' => $ticketData['currency'],
                    'ticket_price' => $ticketData['ticket_price'],
                    'ticket_id' => $show->id,
                    'ticket_type' => get_class($show),
                ]
            );
        }
    }

    $event->priceranges()->delete();

    $prices = [];
    $names = [];
    $currency = '';

    foreach ($request->tickets as $ticketData) {
        $event->priceranges()->create(['price' => $ticketData['ticket_price']]);
        $prices[] = $ticketData['ticket_price'];
        $names[] = $ticketData['name'];
        $currency = $ticketData['currency'];
    }

    $priceRange = self::getPriceRange($prices, $currency);

    $event->update([
        'price_range' => $priceRange,
    ]);

    $event = $event->fresh();
    $event->searchable();
}

    

    public static function getPriceRange($prices, $currency)
    {
        rsort($prices);
        $lowestPrice = last($prices);

        if ($lowestPrice == 0) {
            $first = 'Free';
        } else {
            $first = $currency . $lowestPrice;
        }

        if (sizeof($prices) > 1) {
            return $pricerange = $first . ' - ' . $currency . $prices[0];
        } else {
            return $pricerange = $first;
        }
    }


}
