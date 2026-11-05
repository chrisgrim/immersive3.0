<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    /** Anyone on a team can propose a venue. */
    public function propose(User $user): bool
    {
        return $user->isModerator() || $user->teams()->exists();
    }

    public function approve(User $user, Venue $venue): bool
    {
        return $user->isModerator();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->isModerator();
    }

    public function merge(User $user): bool
    {
        return $user->isModerator();
    }
}
