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
        if ($user->id === $model->id) {
            return true;
        }

        // Moderators may edit ordinary users but never admins: this path can
        // change the email, and email is the login credential. Mirrors the
        // guard in AdminUserController::update.
        if ($model->type === 'a') {
            return $user->isAdmin();
        }

        return $user->isModerator();
    }
}
