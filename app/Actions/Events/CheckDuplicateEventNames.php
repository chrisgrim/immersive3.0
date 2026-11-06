<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Case-insensitive duplicate check on event names, shared by the web
 * creation flow (HostEventController) and the MCP tools.
 */
class CheckDuplicateEventNames
{
    /**
     * @return Collection|null null when the name is unused; otherwise the duplicates.
     */
    public function handle(?string $name, ?int $excludeId = null, ?User $viewer = null, array $ignoring = []): ?Collection
    {
        if (empty($name)) {
            return null;
        }

        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when($excludeId, function ($query) use ($excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            // Runs of the show it asked to join, and the listing it asked to
            // join, are meant to share its name (Event::sameShowIds).
            ->when($ignoring !== [], fn ($query) => $query->whereKeyNot($ignoring))
            // A held draft's name is staff's business until it is approved.
            ->visibleToTeam($viewer)
            ->with(['organizer.owner', 'organizer.users'])
            ->select('id', 'name', 'slug', 'project_id', 'instance_slug', 'status', 'organizer_id', 'embargo_date', 'timezone')
            ->get();

        // Someone else's embargoed listing is unannounced, and so is one in
        // review with an embargo date still ahead: it does not surface at all.
        // Their other unlisted work surfaces only for the ownership claim,
        // with no address and no status: a company that can't be claimed gives
        // nothing to act on, so its draft stays its own.
        $mayOpen = fn (Event $event) => $event->status === Event::STATUS_PUBLISHED || (bool) $viewer?->can('manage', $event);

        $duplicateEvents = $duplicateEvents
            ->reject(fn (Event $event) => ! $mayOpen($event) && (
                $event->status === Event::STATUS_EMBARGOED
                || $event->embargoIsPending()
                || ! $event->organizer?->isClaimable()
            ))
            ->values();

        if ($duplicateEvents->isEmpty()) {
            return null;
        }

        // Surface the organizer behind each duplicate. A matching event title is often a
        // stronger duplicate-org signal than the org name itself ("Artechouse" vs
        // "Artechouse NYC" never match, but the show title does). When that organizer was
        // entered by staff and isn't externally owned, the creator can claim it through the
        // existing organizer ownership-claim flow instead of filing a duplicate event.
        return $duplicateEvents->map(function ($event) use ($mayOpen) {
            $organizer = $event->organizer;
            $open = $mayOpen($event);

            return [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $open ? $event->slug : null,
                'public_url' => $open ? $event->publicPath() : null,
                'status' => $open ? $event->status : null,
                'organizer' => $organizer ? [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'slug' => $organizer->slug,
                    'claimable' => $organizer->isClaimable(),
                ] : null,
            ];
        });
    }
}
