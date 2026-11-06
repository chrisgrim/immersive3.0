<?php

namespace App\Models\Events;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = [
        'hiddenLocation', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'longitude', 'latitude', 'event_id', 'hiddenLocationToggle', 'venue', 'venue_id',
    ];

    /**
     * Each Location belongs to an Event Model.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The known venue, when one is attached. Not venue(): that name is the
     * free-text column, and an attribute shadows a relation of the same name.
     * Never load this while hiddenLocationToggle is on.
     */
    public function venueRecord()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    /**
     * The venue name the public may read. A secret location that holds a
     * linked venue names it nowhere. A free-text name on a secret listing is
     * the organizer's own wording, and stays as it always has.
     */
    public function publicVenueName(): ?string
    {
        return $this->hiddenLocationToggle && $this->venue_id ? null : $this->venue;
    }

    /**
     * Listings reach visitors as JSON whole (search, similar events, some of
     * it cached), so the rule holds here rather than at each endpoint. The
     * keys stay, as on any listing without a venue.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->hiddenLocationToggle && $this->venue_id) {
            return array_replace($array, array_fill_keys(array_intersect(['venue', 'venue_id'], array_keys($array)), null));
        }

        return $array;
    }

    /**
     * "Brooklyn, NY" in the US, "London, United Kingdom" elsewhere — the
     * same shape the event cards print (event-grid.vue).
     */
    public function placeLabel(): string
    {
        $area = in_array($this->country, ['US', 'United States'], true)
            ? $this->region
            : ($this->country_long ?: $this->country);

        return implode(', ', array_filter([Str::ucfirst((string) $this->city), $area]));
    }

    /**
        Store Event Physical Location to Database
     */
    public static function storeEventLocation($request, $event)
    {
        $event->location->update($request->all());
        $event->update([
            'location_latlon' => [
                'lat' => $request->latitude,
                'lon' => $request->longitude,
            ],
            'hasLocation' => true,
        ]);
    }

    /**
        Store Event Remote Location
     */
    public static function storeRemoteLocation($request, $event)
    {
        foreach ($request->remote as $loc) {
            RemoteLocation::firstOrCreate([
                'slug' => Str::slug($loc),
            ],
                [
                    'name' => $loc,
                    'user_id' => auth()->user()->id,
                ]);
        }
        $newSync = RemoteLocation::whereIn('slug', collect($request->remote)->map(function ($item) {
            return Str::slug($item);
        })->toArray())->get();
        $event->remotelocations()->sync($newSync);

        $event->update([
            'hasLocation' => false,
            'location_latlon' => null,
            'remote_description' => $request->description,
        ]);

    }
}
