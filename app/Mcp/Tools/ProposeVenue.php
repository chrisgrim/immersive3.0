<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\ProposeVenue as ProposeVenueAction;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Propose a venue that search-venues did not find. Resolve the address with geocode-address first and pass the fields it returns. A moderator approves it; until then it has no public page.')]
class ProposeVenue extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->can('propose', Venue::class)) {
            return Response::error('Venues are for members of an organizer team.');
        }

        $validated = $request->validate(ProposeVenueAction::RULES);

        return Response::json(['venue' => app(ProposeVenueAction::class)->handle($validated, $request->user())->only(['id', 'name', 'slug', 'status'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The venue\'s name.')->required(),
            'room' => $schema->string()->description('A room or stage inside it.'),
            'home' => $schema->string()->description('Street number.'),
            'street' => $schema->string()->description('Street.')->required(),
            'city' => $schema->string()->description('City.')->required(),
            'region' => $schema->string()->description('State or region, short form.'),
            'region_long' => $schema->string()->description('State or region, in full.'),
            'country' => $schema->string()->description('Country, short form.'),
            'country_long' => $schema->string()->description('Country, in full.'),
            'postal_code' => $schema->string()->description('Postal code.'),
            'latitude' => $schema->number()->description('From geocode-address.')->required(),
            'longitude' => $schema->number()->description('From geocode-address.')->required(),
        ];
    }
}
