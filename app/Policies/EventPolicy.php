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
        // If user is a moderator, they can always access hosting features
        if ($user->isModerator()) {
            return true;
        }

        // If user doesn't belong to any teams, redirect to getting-started
        if (!$user->teams()->exists()) {
            // We can't redirect directly from a policy, so we'll throw a custom exception
            // that will be caught by the exception handler
            throw new \App\Exceptions\NoTeamsException();
        }

        return true;
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

    /**
     * Determine if the user can view click statistics for the event.
     */
    public function viewClickStats(User $user, Event $event): bool
    {
        return $user->belongsToOrganization($event->organizer) || 
               $user->isModerator() || 
               $user->isAdmin();
    }
}
