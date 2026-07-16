<?php

namespace App\Actions\Events;

use App\Models\Event;
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
    public function handle(?string $name, ?int $excludeId = null): ?Collection
    {
        if (empty($name)) {
            return null;
        }

        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->when($excludeId, function ($query) use ($excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->with(['organizer.owner', 'organizer.users'])
            ->select('id', 'name', 'slug', 'status', 'organizer_id')
            ->get();

        if ($duplicateEvents->isEmpty()) {
            return null;
        }

        // Surface the organizer behind each duplicate. A matching event title is often a
        // stronger duplicate-org signal than the org name itself ("Artechouse" vs
        // "Artechouse NYC" never match, but the show title does). When that organizer was
        // entered by staff and isn't externally owned, the creator can claim it through the
        // existing organizer ownership-claim flow instead of filing a duplicate event.
        return $duplicateEvents->map(function ($event) {
            $organizer = $event->organizer;

            return [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'status' => $event->status,
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
