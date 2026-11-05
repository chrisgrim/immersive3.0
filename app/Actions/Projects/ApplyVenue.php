<?php

namespace App\Actions\Projects;

use App\Models\Event;
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

    /** @return array<string, mixed> the location fields to save */
    public function handle(Event $event, array $location): array
    {
        $current = $event->location;
        $secret = (bool) ($location['hiddenLocationToggle'] ?? $current?->hiddenLocationToggle);
        $picked = $location['venue_id'] ?? null;

        if ($picked !== null) {
            if ($secret) {
                throw ValidationException::withMessages(['location.venue_id' => 'A secret location cannot be linked to a venue.']);
            }

            return $this->copyVenue(Venue::findOrFail($picked), $location);
        }

        $held = $current?->venue_id;
        $touched = array_intersect_key($location, array_flip([...self::ADDRESS_FIELDS, ...self::POINT_FIELDS]));

        if ($secret || array_key_exists('venue_id', $location) || $touched !== []) {
            $location['venue_id'] = null;
        }

        // Moving off a venue with no new point would leave its point behind.
        if ($held !== null && ! $secret && $this->addressChanged($current, $location) && ! $this->hasPoint($location)) {
            $location = $this->withGeocodedPoint($current, $location);
        }

        return $location;
    }

    private function copyVenue(Venue $venue, array $location): array
    {
        return array_merge($location, $venue->only([...self::ADDRESS_FIELDS, ...self::POINT_FIELDS]), [
            'venue_id' => $venue->id,
            'venue' => Str::limit($venue->name, 80, ''),
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

    private function hasPoint(array $location): bool
    {
        return isset($location['latitude'], $location['longitude']);
    }

    private function withGeocodedPoint($current, array $location): array
    {
        $parts = collect(self::ADDRESS_FIELDS)
            ->reject(fn (string $field) => in_array($field, ['region_long', 'country_long'], true))
            ->map(fn (string $field) => $location[$field] ?? $current->{$field})
            ->filter();

        try {
            $match = app(GeocodingService::class)->search($parts->implode(' '))[0] ?? null;
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
