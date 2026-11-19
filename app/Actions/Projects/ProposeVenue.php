<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;

/** Anyone on a team proposes a venue; a moderator approves it. It waits as pending. */
class ProposeVenue
{
    public const RULES = [
        'name' => ['required', 'string', 'max:80', 'regex:/[\p{L}\p{N}]/u'],
        'room' => ['nullable', 'string', 'max:255'],
        'home' => ['nullable', 'string', 'max:255'],
        'street' => ['required', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:255'],
        'region' => ['nullable', 'string', 'max:255'],
        'region_long' => ['nullable', 'string', 'max:255'],
        'country' => ['nullable', 'string', 'max:255'],
        'country_long' => ['nullable', 'string', 'max:255'],
        'postal_code' => ['nullable', 'string', 'max:20'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
    ];

    /**
     * $for: the listing the venue is for. Its company keeps the venue, not
     * whichever team the person last had selected (an assistant working
     * across many companies filed hundreds under the wrong one). The caller
     * has checked the person may manage that listing.
     */
    public function handle(array $validated, User $actor, ?Event $for = null): Venue
    {
        $venue = Venue::create([...$validated, 'status' => Venue::PENDING]);

        if ($for?->organizer_id) {
            $venue->organizer_id = $for->organizer_id;
            $venue->save();
            AuditLog::record($venue, 'venue.proposed', $actor, ['for_event_id' => $for->id]);

            return $venue;
        }

        // The team adding it keeps it until the venue itself claims it. Set by
        // hand, never from the request, so nobody can hand a venue to a team.
        $team = $actor->getCurrentOrganizer();
        if ($team && $actor->belongsToOrganization($team)) {
            $venue->organizer_id = $team->id;
            $venue->save();
        } else {
            // A person with no organization (adding a venue from Your venues)
            // keeps it themselves, until the real venue claims it.
            $venue->user_id = $actor->id;
            $venue->save();
        }

        AuditLog::record($venue, 'venue.proposed', $actor);

        return $venue;
    }
}
