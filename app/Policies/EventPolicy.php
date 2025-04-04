<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can access hosting features.
     */
    public function host(User $user): bool
    {
        return $user->teams()->exists() || $user->isModerator();
    }

    /**
     * Determine if the user can manage the event.
     */
    public function manage(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organizer) || 
               $user->isModerator();
    }

    /**
     * Determine if the user can moderate events.
     */
    public function moderate(User $user): bool
    {
        return $user->isAdmin() || $user->isModerator();
    }

    /**
     * Determine if the user can duplicate the event.
     */
    public function duplicate(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organizer) || 
               $user->isModerator() || 
               $user->isAdmin();
    }
}
