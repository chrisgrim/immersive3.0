<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\User;

class ArtistPolicy
{
    /** Anyone on a team can propose an artist. */
    public function propose(User $user): bool
    {
        return $user->isModerator() || $user->teams()->exists();
    }

    public function approve(User $user, Artist $artist): bool
    {
        return $user->isModerator();
    }

    public function update(User $user, Artist $artist): bool
    {
        return $user->isModerator();
    }

    public function merge(User $user): bool
    {
        return $user->isModerator();
    }
}
