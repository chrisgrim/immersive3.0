<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    /** Anyone on a team can propose a venue. */
    public function propose(User $user): bool
    {
        // Staff, a member of an organization, or someone who already keeps a venue.
        return $user->isModerator() || $user->teams()->exists() || \App\Models\Venue::where('user_id', $user->id)->exists();
    }

    public function approve(User $user, Venue $venue): bool
    {
        return $user->isModerator();
    }

    /**
     * Admins and moderators always. Otherwise the person who owns it (an
     * approved claim), or, until someone claims it, the team that added it.
     */
    public function update(User $user, Venue $venue): bool
    {
        if ($user->isModerator()) {
            return true;
        }

        if ($venue->merged_into_id !== null) {
            return false;
        }

        if ($venue->user_id !== null) {
            return (int) $venue->user_id === (int) $user->id;
        }

        return $venue->owner !== null && $user->belongsToOrganization($venue->owner);
    }

    public function merge(User $user): bool
    {
        return $user->isModerator();
    }
}
