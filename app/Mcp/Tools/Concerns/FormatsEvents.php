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
     * What still needs to be filled in before the event is worth submitting.
     * Mirrors what the web wizard collects; each key is true when satisfied.
     */
    protected function readiness(Event $event): array
    {
        $hasShows = $event->shows->isNotEmpty();
        $hasTickets = $hasShows && $event->shows->first()->tickets->isNotEmpty();

        $locationReady = $event->hasLocation
            ? (bool) ($event->location?->latitude && $event->location?->longitude)
            : ($event->remotelocations->isNotEmpty() || filled($event->remote_description));

        return [
            'name' => ! str_starts_with((string) $event->slug, 'new-event-') || filled($event->name),
            'category' => $event->category_id !== null,
            'description' => filled($event->description),
            'location_or_remote' => $locationReady,
            'dates' => $hasShows,
            'tickets' => $hasTickets,
            'primary_image' => filled($event->largeImagePath) || $event->images->contains(fn ($image) => (int) $image->rank === 0),
            'contact_level' => $event->contactLevels->isNotEmpty(),
            'interactive_level' => $event->interactive_level_id !== null,
        ];
    }
}
