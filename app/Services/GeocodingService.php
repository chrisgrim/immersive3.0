<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Server-side forward geocoding for the MCP location flow, so AI clients
 * pass a human address and get back the exact structured fields the events
 * location table stores — no client-side guessing of coordinates.
 *
 * Uses the Google Geocoding API when services.google_geocoding.key is set
 * (best accuracy, matches the wizard's Places data); otherwise falls back to
 * OpenStreetMap Nominatim (no key, fine for occasional MCP use).
 */
class GeocodingService
{
    /**
     * @return array<int, array<string, mixed>> Up to 3 candidates, best first.
     */
    public function search(string $query): array
    {
        $googleKey = config('services.google_geocoding.key');

        return $googleKey
            ? $this->google($query, $googleKey)
            : $this->nominatim($query);
    }

    protected function google(string $query, string $key): array
    {
        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $query,
            'key' => $key,
        ]);

        if (! $response->successful() || $response->json('status') === 'REQUEST_DENIED') {
            throw new \RuntimeException('Google geocoding failed: '.($response->json('error_message') ?? $response->status()));
        }

        return collect($response->json('results') ?? [])->take(3)->map(function ($result) {
            $components = collect($result['address_components'] ?? []);
            $get = fn (string $type, string $field = 'long_name') => $components
                ->first(fn ($c) => in_array($type, $c['types']))[$field] ?? null;

            return [
                'formatted_address' => $result['formatted_address'] ?? null,
                'home' => $get('street_number'),
                'street' => $get('route'),
                'city' => $get('locality') ?? $get('sublocality') ?? $get('postal_town'),
                'region' => $get('administrative_area_level_1', 'short_name'),
                'region_long' => $get('administrative_area_level_1'),
                'country' => $get('country', 'short_name'),
                'country_long' => $get('country'),
                'postal_code' => $get('postal_code'),
                'latitude' => $result['geometry']['location']['lat'] ?? null,
                'longitude' => $result['geometry']['location']['lng'] ?? null,
                'source' => 'google',
            ];
        })->all();
    }

    protected function nominatim(string $query): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['User-Agent' => 'EverythingImmersive/1.0 (support@everythingimmersive.com)'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 3,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Nominatim geocoding failed: HTTP '.$response->status());
        }

        return collect($response->json() ?? [])->map(function ($result) {
            $addr = $result['address'] ?? [];

            return [
                'formatted_address' => $result['display_name'] ?? null,
                'venue' => in_array($result['category'] ?? '', ['amenity', 'tourism', 'leisure', 'building'])
                    ? ($result['name'] ?? null)
                    : null,
                'home' => $addr['house_number'] ?? null,
                'street' => $addr['road'] ?? null,
                'city' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? null,
                'region' => $addr['state_code'] ?? ($addr['ISO3166-2-lvl4'] ?? null ? substr($addr['ISO3166-2-lvl4'], -2) : null),
                'region_long' => $addr['state'] ?? null,
                'country' => isset($addr['country_code']) ? strtoupper($addr['country_code']) : null,
                'country_long' => $addr['country'] ?? null,
                'postal_code' => $addr['postcode'] ?? null,
                'latitude' => isset($result['lat']) ? (float) $result['lat'] : null,
                'longitude' => isset($result['lon']) ? (float) $result['lon'] : null,
                'source' => 'nominatim',
            ];
        })->all();
    }
}
