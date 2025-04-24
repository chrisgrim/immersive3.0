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

    $priceRange = self::getPriceRange($prices, $currency, $names);

    $event->update([
        'price_range' => $priceRange,
    ]);

    $event = $event->fresh();
    $event->searchable();
}

    public static function getPriceRange($prices, $currency, $names = [])
    {
        rsort($prices);
        $lowestPrice = last($prices);
        
        // Check if any ticket name is "PWYC" (case insensitive)
        $hasPWYC = false;
        foreach ($names as $name) {
            if (strtoupper(trim($name)) === 'PWYC') {
                $hasPWYC = true;
                break;
            }
        }

        if ($hasPWYC) {
            $first = 'PWYC';
        } else if ($lowestPrice == 0) {
            $first = 'Free';
        } else {
            $formattedLowestPrice = number_format($lowestPrice, 2);
            $formattedLowestPrice = preg_replace('/\.00$/', '', $formattedLowestPrice);
            $first = $currency . $formattedLowestPrice;
        }

        if (sizeof($prices) > 1) {
            $formattedHighestPrice = number_format($prices[0], 2);
            $formattedHighestPrice = preg_replace('/\.00$/', '', $formattedHighestPrice);
            
            // If lowest price is PWYC but there are higher prices, show the range
            if ($hasPWYC) {
                return $pricerange = 'PWYC - ' . $currency . $formattedHighestPrice;
            } else {
                return $pricerange = $first . ' - ' . $currency . $formattedHighestPrice;
            }
        } else {
            return $pricerange = $first;
        }
    }


}
