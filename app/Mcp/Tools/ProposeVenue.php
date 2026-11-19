<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\ProposeVenue as ProposeVenueAction;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Propose a venue that search-venues did not find. Resolve the address with geocode-address first and pass the fields it returns. Pass event_slug, the listing the venue is for: that listing\'s company keeps the venue (moderators must always pass it). A moderator approves it; until then it has no public page. Then attach it to the listing.')]
class ProposeVenue extends Tool
{
    public function handle(Request $request): Response
    {
        if (! $request->user()->can('propose', Venue::class)) {
            return Response::error('Venues are for members of an organizer team.');
        }

        $validated = $request->validate([...ProposeVenueAction::RULES, 'event_slug' => 'nullable|string']);
        $user = $request->user();

        $event = null;
        if (filled($validated['event_slug'] ?? null)) {
            $event = Event::where('slug', $validated['event_slug'])->first();
            if ($event === null || ! $user->can('manage', $event)) {
                return Response::error('No listing you can edit has that slug.');
            }
            if (! $event->organizer_id) {
                return Response::error('That listing has no company to keep the venue.');
            }
        } elseif ($user->isModerator() || in_array($user->type, ['m', 'a'], true) || \Illuminate\Support\Facades\DB::table('organizer_user')->where('user_id', $user->id)->count() > 1) {
            // Working across companies: the one last selected is not
            // necessarily the one this venue belongs to.
            return Response::error('Say which listing this venue is for (event_slug), so it is kept by that listing\'s company.');
        }

        unset($validated['event_slug']);

        return Response::json(['venue' => app(ProposeVenueAction::class)->handle($validated, $user, $event)->only(['id', 'name', 'slug', 'status', 'organizer_id'])]);
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
            'event_slug' => $schema->string()->description('The listing this venue is for: its company keeps the venue. Required for moderators.'),
        ];
    }
}
