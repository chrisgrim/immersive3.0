<?php

namespace App\Mcp\Tools;

use App\Actions\Events\UpdateEventAction;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Mcp\Tools\Concerns\BuildsSyntheticRequests;
use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Attach a listing to a venue, past its edit lock too; the venue\'s address and point are copied onto the listing. Null takes the venue off and leaves the address. A secret-location listing is refused.')]
class SetEventVenue extends Tool
{
    use AnswersShowActions;
    use BuildsSyntheticRequests;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'venue_id' => 'nullable|integer|exists:venues,id',
        ]);

        $event = Event::where('slug', $validated['event_slug'])->first();

        if ($event === null || ! $event->hasLocation) {
            return Response::error('No in-person listing with that slug.');
        }

        return $this->attempt(function () use ($event, $validated, $user) {
            $data = ['location' => ['venue_id' => $validated['venue_id'] ?? null]];
            $event = app(UpdateEventAction::class)->handle($event, $data, $this->syntheticRequest($data, $user));

            return ['event' => $event->only(['id', 'slug']), 'location' => $event->location?->only(['venue', 'venue_id', 'street', 'city'])];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The listing.')->required(),
            'venue_id' => $schema->integer()->description('The venue, from search-venues; null to take it off.'),
        ];
    }
}
