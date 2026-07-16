<?php

namespace App\Mcp\Tools;

use App\Services\GeocodingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Resolve a human-readable address or venue into the exact structured location fields (street, city, region, country, postal code, latitude/longitude) that update-event needs. Returns up to 3 candidates — confirm the right one with the user before saving it to the event. Never guess coordinates yourself.')]
class GeocodeAddress extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
        ]);

        try {
            $candidates = app(GeocodingService::class)->search($validated['address']);
        } catch (\RuntimeException $e) {
            return Response::error('Geocoding failed: '.$e->getMessage());
        }

        if ($candidates === []) {
            return Response::error('No location found for that address. Ask the user for more detail (street, city, country).');
        }

        return Response::json([
            'candidates' => $candidates,
            'next_step' => count($candidates) > 1
                ? 'Multiple matches — confirm the right one with the user, then pass its fields as the `location` object to update-event (add the venue name separately if the user gave one).'
                : 'Confirm this address with the user, then pass its fields as the `location` object to update-event (add the venue name separately if the user gave one).',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'address' => $schema->string()
                ->description('The address or venue to resolve, as specific as the user gave it, e.g. "30 Rockefeller Plaza, New York" or "The Bellwether, Los Angeles".')
                ->required(),
        ];
    }
}
