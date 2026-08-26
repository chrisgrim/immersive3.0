<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the full editable state of an event by slug, including a readiness checklist showing what still needs to be filled in before submit-event-for-review. Works on any event you can manage — for moderators and admins that is EVERY event on the platform, not just your own organizers. Find slugs with list-all-events.')]
class GetEvent extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'event_slug' => 'required|string',
        ]);

        $event = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $validated['event_slug'])
            ->first();

        if (! $event) {
            return Response::error('No event found with that slug.');
        }

        if (! $request->user()->can('manage', $event)) {
            return Response::error('You do not have permission to view this event.');
        }

        $event->load([
            'shows.tickets',
            'location',
            'advisories',
            'mobilityAdvisories',
            'contentAdvisories',
            'contactLevels',
            'interactive_level',
            'category',
            'genres',
            'remotelocations',
            'age_limits',
            'images',
            'videos',
            'organizer:id,name,slug',
        ]);

        $readiness = $this->readiness($event);
        $missing = collect($readiness)->reject(fn ($ok) => $ok)->keys()->values();

        return Response::json([
            'event' => $this->eventSummary($event) + [
                'tag_line' => $event->tag_line,
                'description' => $event->description,
                'category' => $event->category?->only(['id', 'name']),
                'attendance_type_id' => $event->attendance_type_id,
                'hasLocation' => (bool) $event->hasLocation,
                // hiddenLocation belongs here next to its toggle: the readiness
                // check `secret_location_explained` is what this field satisfies,
                // and leaving it out left clients able to see the flag was unmet
                // but not the field that clears it — they guessed at
                // remote_description (visible below) instead.
                'location' => $event->location?->only([
                    'venue', 'home', 'street', 'city', 'region', 'country', 'postal_code', 'latitude', 'longitude', 'hiddenLocationToggle', 'hiddenLocation',
                ]),
                'remote_locations' => $event->remotelocations->pluck('name'),
                'remote_description' => $event->remote_description,
                'timezone' => $event->timezone,
                'showtype' => $event->showtype,
                'showtype_config' => $event->showtype_config,
                'show_dates' => $event->shows->pluck('date'),
                'show_times' => $event->show_times,
                'closing_date' => $event->closingDate,
                'embargo_date' => $event->embargo_date,
                'tickets' => $event->shows->first()?->tickets->map->only(['name', 'ticket_price', 'currency', 'description']) ?? [],
                'price_range' => $event->price_range,
                'genres' => $event->genres->map->only(['id', 'name']),
                'content_advisories' => $event->contentAdvisories->pluck('name'),
                'mobility_advisories' => $event->mobilityAdvisories->pluck('name'),
                'advisories' => $event->advisories?->only(['sexual', 'sexualDescription', 'audience', 'wheelchairReady']),
                'contact_level' => $event->contactLevels->first()?->only(['id', 'name']),
                'interactive_level' => $event->interactive_level?->only(['id', 'name']),
                'age_limit' => $event->age_limits?->only(['id', 'name']),
                'websiteUrl' => $event->websiteUrl,
                'ticketUrl' => $event->ticketUrl,
                'call_to_action' => $event->call_to_action,
                'images' => $event->images->map->only(['id', 'rank', 'large_image_path']),
                'videos' => $event->videos->map->only(['platform', 'url', 'rank']),
            ],
            'readiness' => $readiness,
            'missing' => $missing,
            'next_step' => $missing->isEmpty()
                ? 'All checks pass. Review the details with the user, then call submit-event-for-review.'
                : 'Fill in the missing fields with update-event (use attach-event-image for the primary image).',
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()
                ->description('The event slug, as returned by create-event-draft or list-my-events.')
                ->required(),
        ];
    }
}
