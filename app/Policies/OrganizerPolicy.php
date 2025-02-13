<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class OrganizerPolicy
{
    /**
     * Determine whether the user can edit or manage the organizer.
     */
    public function edit(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganization($organizer) || 
               $user->isModerator();  // This includes both moderators and admins
    }

    /**
     * Determine whether the user can create an organizer.
     */
    public function create(User $user): bool
    {
        return true; // Anyone authenticated can create
    }

    /**
     * Determine whether the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        Log::info('viewAny called');
        return true;
    }

    /**
     * Determine whether the user can switch to a specific team.
     */
    public function switchTeam(User $user, Organizer $organizer): bool
    {
        return $user->belongsToOrganization($organizer) || 
               $user->isModerator() ||
               $user->ownsOrganization($organizer);
    }
}