<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Event;

trait FormatsEvents
{
    /**
     * Human-readable label for an event status char.
     */
    protected function statusLabel(?string $status): string
    {
        if ($status === null) {
            return 'unknown';
        }

        return match (true) {
            $status === 'p' => 'published',
            $status === 'e' => 'embargoed (approved, publishes automatically on the embargo date)',
            $status === 'r' => 'in review (awaiting admin approval)',
            $status === 'n' => 'needs revision (rejected — edit and resubmit)',
            default => 'draft (in progress)',
        };
    }

    protected function eventSummary(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'status' => $event->status,
            'status_label' => $this->statusLabel($event->status),
            'organizer' => $event->organizer?->name,
            'organizer_id' => $event->organizer_id,
        ];
    }

    /**
     * Advisory chips the wizard adds automatically from the yes/no answers.
     * The "at least one advisory" requirements count entries BEYOND these.
     */
    protected const SEXUAL_CHIP_SLUGS = ['sexual-content', 'no-sexual-content'];

    protected const WHEELCHAIR_CHIP_SLUGS = ['wheelchair-accessible', 'not-wheelchair-accessible'];

    /**
     * What still needs to be filled in before the event can be submitted.
     * Full parity with the web wizard's step-by-step requirements — an MCP
     * submission must be at least as complete as a wizard one.
     */
    protected function readiness(Event $event): array
    {
        $hasShows = $event->shows->isNotEmpty();
        $hasTickets = $hasShows && $event->shows->first()->tickets->isNotEmpty();

        $locationReady = $event->hasLocation
            ? (bool) ($event->location?->latitude && $event->location?->longitude)
            : $event->remotelocations->isNotEmpty();

        return [
            'name' => ! str_starts_with((string) $event->slug, 'new-event-') || filled($event->name),
            'tag_line' => filled($event->tag_line),
            'category' => $event->category_id !== null,
            'genres' => $event->genres->isNotEmpty(),
            'description' => filled($event->description),
            'location_or_remote' => $locationReady,
            // A secret location needs an explanation of how attendees learn it.
            'secret_location_explained' => ! $event->location?->hiddenLocationToggle
                || filled($event->location?->hiddenLocation),
            'dates' => $hasShows,
            'tickets' => $hasTickets,
            'ticket_url' => filled($event->ticketUrl),
            'ticket_button_text' => filled($event->call_to_action),
            'primary_image' => filled($event->largeImagePath) || $event->images->contains(fn ($image) => (int) $image->rank === 0),
            'contact_level' => $event->contactLevels->isNotEmpty(),
            'age_limit' => $event->age_limits_id !== null,
            'interactive_level' => $event->interactive_level_id !== null,
            'audience_role' => filled($event->advisories?->audience),
            // The wizard forces explicit yes/no answers (nullable columns, so
            // null means "never answered").
            'sexual_content_answered' => $event->advisories?->sexual !== null,
            'wheelchair_answered' => $event->advisories?->wheelchairReady !== null,
            // At least one advisory beyond the automatic yes/no chips.
            'content_advisories' => $event->contentAdvisories
                ->reject(fn ($a) => in_array($a->slug, self::SEXUAL_CHIP_SLUGS))->isNotEmpty(),
            'mobility_advisories' => $event->mobilityAdvisories
                ->reject(fn ($a) => in_array($a->slug, self::WHEELCHAIR_CHIP_SLUGS))->isNotEmpty(),
        ];
    }
}
