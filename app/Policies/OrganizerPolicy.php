<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class OrganizerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, ?string $type = null): bool
    {
        return $user->organizers()->exists();
    }

    /**
     * Determine whether the user can edit or manage the organizer.
     */
    public function edit(User $user, Organizer $organizer): bool
    {
        return $user->ownsOrganization($organizer) || 
               $user->belongsToOrganization($organizer) ||
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
     * Determine whether the user can switch to a specific team.
     */
    public function switchTeam(User $user, Organizer $organizer): bool
    {
        return $user->belongsToOrganization($organizer) || 
               $user->isModerator() ||
               $user->ownsOrganization($organizer);
    }
}