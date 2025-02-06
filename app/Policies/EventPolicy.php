<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can moderate events.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function moderate(User $user)
    {
        return $user->isAdmin() || $user->isModerator();
    }

}
