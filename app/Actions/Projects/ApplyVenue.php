<?php

namespace App\Actions\Projects;

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Venue;
use App\Services\GeocodingService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The venue rules of a location save, shared by the wizard and MCP through
 * UpdateEventAction. A picked venue's address and point are copied onto the
 * listing's own row, so search and the map read nothing new. An address or
 * coordinate that arrives without the venue being picked again takes the
 * venue off. A secret-location listing never holds one.
 */
class ApplyVenue
{
    private const ADDRESS_FIELDS = ['home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code'];

    private const POINT_FIELDS = ['latitude', 'longitude'];

    /**
     * Places an address before any row is locked: geocoding is a slow outside
     * call, and an edit of the same listing should not wait on it. handle()
     * then finds the point in the fields and makes no call. Read unlocked, so
     * nothing here decides anything: secrecy and the venue stay with handle().
     *
     * @return array<string, mixed> the fields, with a point if one will be needed
     */
    public function withPointIfNeeded(Event $event, array $location): array
    {
        $current = Location::where('event_id', $event->id)->first();

        return $current && ($location['venue_id'] ?? null) === null && $this->needsPoint($current, $location)
            ? $this->withGeocodedPoint($current, $location)
            : $location;
    }

    /** @return array<string, mixed> the location fields to save */
    public function handle(Event $event, array $location): array
    {
        $current = $event->location;
        $secret = (bool) ($location['hiddenLocationToggle'] ?? $current?->hiddenLocationToggle);
        $picked = $location['venue_id'] ?? null;

        // The wizard re-sends the venue it holds: with secret on, that is
        // taking it off, and refusing would leave the organizer stuck, with
        // the picker hidden. A different venue is still refused below.
        if ($secret && $picked !== null && (int) $picked === (int) $current?->venue_id) {
            $picked = null;
            $location['venue_id'] = null;
        }

        if ($picked !== null) {
            if ($secret) {
                throw ValidationException::withMessages(['location.venue_id' => 'A secret location cannot be linked to a venue.']);
            }

            if ($event->hasLocation === false || (int) $event->attendance_type_id === 2) {
                throw ValidationException::withMessages(['location.venue_id' => 'An online listing cannot be linked to a venue.']);
            }

            // A venue merged away stands for the one it went into. Then:
            // approved, already this listing's, or proposed by its own team, as
            // a pending venue's address isn't anyone else's to copy.
            $venue = Venue::find($picked)?->winner();

            if ($venue && (int) $venue->id !== (int) $current?->venue_id && ! auth()->user()?->isModerator()) {
                $venue = Venue::visibleToOrganizer($event->organizer_id)->find($venue->id);
            }

            // A draft is never linked, by anyone: it has no checked address yet,
            // and it can still be deleted or cleared away.
            if (! $venue || $venue->status === Venue::DRAFT) {
                throw ValidationException::withMessages(['location.venue_id' => 'That venue is not available. Search for it again, or add the address.']);
            }

            return $this->copyVenue($venue, $location);
        }

        $held = $current?->venue_id;
        $touched = array_intersect_key($location, array_flip([...self::ADDRESS_FIELDS, ...self::POINT_FIELDS]));
        $kind = $location['kind'] ?? null;

        // "Somewhere else", said plainly, takes the listing off its venue.
        if ($secret || array_key_exists('venue_id', $location) || $touched !== [] || $kind === Location::KIND_PLACE) {
            $location['venue_id'] = null;
        }

        // The kind follows what is true: a hidden location is always somewhere
        // else; "venue" with no venue linked says nothing; and a listing taken
        // off its venue without saying "somewhere else" is unknown again, so a
        // typed name is never mistaken for a decision.
        if ($secret) {
            $location['kind'] = Location::KIND_PLACE;
        } elseif ($kind === Location::KIND_VENUE) {
            // "At a venue" is kept only while one stays linked (the one it
            // already has, untouched); with none it says nothing.
            $keepsVenue = $held !== null && ! array_key_exists('venue_id', $location) && $touched === [];
            $location['kind'] = $keepsVenue ? Location::KIND_VENUE : null;
        } elseif (! array_key_exists('kind', $location) && $held !== null && array_key_exists('venue_id', $location) && $location['venue_id'] === null) {
            $location['kind'] = null;
        }

        // Going secret: a venue's name goes with it, also when the link went
        // first and the name stayed behind in the free-text field.
        $goingSecret = $secret && ! $current?->hiddenLocationToggle;

        if ($secret && ($held !== null || ($goingSecret && $this->isVenueName($location['venue'] ?? $current?->venue)))) {
            $location['venue'] = null;
        }

        // Moving off a venue with no new point would leave its point behind.
        if ($this->needsPoint($current, $location)) {
            $location = $this->withGeocodedPoint($current, $location);
        }

        return $location;
    }

    /** Copied names are cut to the free-text field's 80 characters (copyVenue). */
    private function isVenueName(?string $name): bool
    {
        return filled($name) && Venue::whereRaw('LEFT(name, 80) = ?', [$name])->exists();
    }

    private function copyVenue(Venue $venue, array $location): array
    {
        return array_merge($location, $venue->only([...self::ADDRESS_FIELDS, ...self::POINT_FIELDS]), [
            'venue_id' => $venue->id,
            'venue' => Str::limit($venue->name, 80, ''),
            'kind' => Location::KIND_VENUE,
        ]);
    }

    private function addressChanged($current, array $location): bool
    {
        foreach (self::ADDRESS_FIELDS as $field) {
            if (array_key_exists($field, $location) && (string) $location[$field] !== (string) $current->{$field}) {
                return true;
            }
        }

        return false;
    }

    private function needsPoint($current, array $location): bool
    {
        $secret = (bool) ($location['hiddenLocationToggle'] ?? $current?->hiddenLocationToggle);

        return $current?->venue_id !== null && ! $secret && $this->addressChanged($current, $location) && ! $this->hasPoint($location);
    }

    private function hasPoint(array $location): bool
    {
        return isset($location['latitude'], $location['longitude']);
    }

    private function withGeocodedPoint($current, array $location): array
    {
        $parts = collect(self::ADDRESS_FIELDS)
            ->reject(fn (string $field) => in_array($field, ['region_long', 'country_long'], true))
            ->map(fn (string $field) => array_key_exists($field, $location) ? $location[$field] : $current->{$field})
            ->filter();

        try {
            $match = app(GeocodingService::class)->search($parts->implode(' '), fallback: false)[0] ?? null;
        } catch (Throwable $e) {
            report($e);
            $match = null;
        }

        if (! isset($match['latitude'], $match['longitude'])) {
            throw ValidationException::withMessages(['location.latitude' => 'We could not place that address on the map. Please pick it from the address search.']);
        }

        return array_merge($location, ['latitude' => $match['latitude'], 'longitude' => $match['longitude']]);
    }
}
