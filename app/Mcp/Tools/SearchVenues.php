<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\FindVenues;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Find venues by name or address. Each one carries its address; confirm the match with the user, then pass its id as location.venue_id to update-event, which copies the address onto the listing. Never use a venue for a secret location.')]
class SearchVenues extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->can('propose', Venue::class)) {
            return Response::error('Venues are for members of an organizer team.');
        }

        $validated = $request->validate(['keywords' => 'required|string|min:2|max:255']);

        return Response::json(['venues' => app(FindVenues::class)->handle($validated['keywords'], $request->user())]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['keywords' => $schema->string()->description('Part of the venue\'s name or address.')->required()];
    }
}
