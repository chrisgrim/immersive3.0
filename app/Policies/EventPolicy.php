<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

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

        // If user doesn't belong to any teams, simply return false
        // to deny access without throwing an exception
        if (!$user->teams()->exists()) {
            // Log the occurrence with more detailed information
            \Log::info('User without teams attempted to access hosting features', [
                'user_id' => $user->id,
                'url' => Request::fullUrl(),
                'route' => Route::currentRouteName(),
                'method' => Request::method()
            ]);
            return false;
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
