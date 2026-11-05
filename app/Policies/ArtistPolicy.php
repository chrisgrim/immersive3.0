<?php

namespace App\Policies;

use App\Models\Artist;
use App\Models\User;

class ArtistPolicy
{
    /** Anyone on a team can propose an artist, and curators, who add cast to any run. */
    public function propose(User $user): bool
    {
        return $user->isModerator()
            || $user->teams()->exists()
            || ($user->isCurator() && $user->credentialAllowsModeration());
    }

    public function approve(User $user, Artist $artist): bool
    {
        return $user->isModerator();
    }

    public function update(User $user, Artist $artist): bool
    {
        return $user->isModerator();
    }

    public function delete(User $user, Artist $artist): bool
    {
        return $user->isModerator();
    }

    public function merge(User $user): bool
    {
        return $user->isModerator();
    }
}
