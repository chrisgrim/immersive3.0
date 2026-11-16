<?php

namespace App\Models\Events;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    /** At a venue page (venue_id is set). */
    public const KIND_VENUE = 'venue';

    /** Somewhere else: a park, the streets, a home, a hidden location. Its name is a label, never a venue. */
    public const KIND_PLACE = 'place';

    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = [
        'hiddenLocation', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'longitude', 'latitude', 'event_id', 'hiddenLocationToggle', 'venue', 'venue_id', 'kind',
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
     * Where the public may put this location on a map, and what
     * events.location_latlon and the search index hold. A secret location
     * gives the centre of its grid cell, so the private point goes nowhere and
     * every surface says the same. The exact point stays here, for the team.
     *
     * @return array{lat: float, lon: float}|null
     */
    public function publicPoint(): ?array
    {
        $lat = (float) $this->latitude;
        $lon = (float) $this->longitude;

        if ($lat == 0 || $lon == 0) {
            return null;
        }

        return $this->hiddenLocationToggle
            ? ['lat' => self::cellCentre($lat, 90), 'lon' => self::cellCentre($lon, 180)]
            : ['lat' => $lat, 'lon' => $lon];
    }

    /** The centre of the 0.01 degree cell, kept on the map at its very edge. */
    private static function cellCentre(float $degrees, int $limit): float
    {
        return min($limit - 0.005, round((floor($degrees * 100) + 0.5) / 100, 3));
    }

    /** Leaves a secret location's exact point out of what this row serializes. */
    public function hideSecretPoint(): void
    {
        if ($this->hiddenLocationToggle) {
            $this->makeHidden(['latitude', 'longitude']);
        }
    }

    /**
     * These fields as someone may read them: a secret location's exact point,
     * like its street, is only for those who can edit the event.
     */
    public function onlyFor(bool $canEdit, array $fields): array
    {
        return $this->only($canEdit || ! $this->hiddenLocationToggle ? $fields : array_diff($fields, ['latitude', 'longitude']));
    }

    /**
        Store Event Physical Location to Database
     */
    public static function storeEventLocation($request, $event)
    {
        $event->location->update($request->all());
        $event->update([
            'location_latlon' => $event->location->publicPoint(),
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
