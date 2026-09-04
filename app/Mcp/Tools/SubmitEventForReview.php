<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\AdminSubmissionNotifier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Submit a completed event draft for human admin review. Works on any event you can manage — for moderators and admins that is any event on the platform. The event goes live only after an admin approves it. Rejected events ("needs revision") can be edited and resubmitted. Confirm with the user before submitting.')]
class SubmitEventForReview extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'event_slug' => 'required|string',
        ]);

        $event = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        // One message whether the slug is unknown or the event is someone
        // else's — see GetEvent.
        if (! $event || ! $user->can('manage', $event)) {
            return Response::error('No event with that slug that you can submit. Slugs come from list-my-events.');
        }

        // Same guard as the web submit flow.
        if (in_array($event->status, ['r', 'p', 'e'])) {
            return Response::error('Event is already submitted or published (status: '.$this->statusLabel($event->status).').');
        }

        $missing = collect($this->readiness($event->load([
            'shows.tickets', 'location', 'advisories', 'contentAdvisories', 'mobilityAdvisories',
            'contactLevels', 'interactive_level', 'category', 'genres', 'remotelocations', 'images',
        ])))->reject(fn ($ok) => $ok)->keys();

        if ($missing->isNotEmpty()) {
            return Response::error('The event is missing: '.$missing->implode(', ').'. Fill these in with update-event (and attach-event-image for the primary image) before submitting.');
        }

        $event->status = 'r';
        $event->save();

        // Notify admins (who haven't opted out) that an event entered the review queue.
        app(AdminSubmissionNotifier::class)->eventSubmitted($event);

        return Response::json([
            'message' => 'Event submitted for review. A human admin will approve or reject it; the creator is notified either way.',
            'event' => $this->eventSummary($event->load('organizer')),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event slug.')->required(),
        ];
    }
}
