<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can update the profile.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isModerator();
    }
}