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
#[Description('Get the full editable state of an event by slug, including a readiness checklist showing what still needs to be filled in before submit-event-for-review. Works on any event you can manage — for moderators and admins that is EVERY event on the platform, not just your own organizers. Find slugs with list-all-events. Pass summary=true to collapse the schedule to a count plus first/last date — do that whenever you are working on content rather than dates, since a recurring event can return thousands of them.')]
class GetEvent extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'event_slug' => 'required|string',
            'summary' => 'nullable|boolean',
        ]);

        $summary = (bool) ($validated['summary'] ?? false);

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
                ...$this->scheduleFields($event, $summary),
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
     * The schedule, either in full or collapsed to its endpoints.
     *
     * An ongoing event's show_dates array is by far the largest thing in this
     * response — one escape room carries 2,282 dates, 49 KB of nothing but
     * timestamps, and 371 events hold more than 100. That crowded out the
     * fields a content or metadata pass actually reads, to the point where a
     * tag cleanup had to farm events out to subagents to stay within context.
     *
     * Summary mode answers the question the dates are usually being read for
     * ("when does this run, and how many performances?") in three fields
     * instead of thousands. The median event has 4 shows, so this changes
     * nothing for most calls — it exists for the long tail.
     */
    protected function scheduleFields(Event $event, bool $summary): array
    {
        if (! $summary) {
            return ['show_dates' => $event->shows->pluck('date')];
        }

        return [
            'shows_count' => $event->shows->count(),
            'first_show_date' => $event->shows->min('date'),
            'last_show_date' => $event->shows->max('date'),
        ];
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
            'summary' => $schema->boolean()
                ->description('Collapse the schedule instead of listing every date: replaces show_dates with shows_count, first_show_date and last_show_date. Every other field is unchanged. Use this for content/metadata work (tags, descriptions, advisories) and for sweeping many events — a recurring event can carry thousands of dates. Defaults to false; omit it when you need to see or edit the individual dates.'),
        ];
    }
}
