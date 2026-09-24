<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;

class OrganizerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, ?string $type = null): bool
    {
        // Allow moderators and admins to view any organizers
        if ($user->isModerator()) {
            return true;
        }

        // Regular users can view if they own OR are a member of an organizer.
        // organizers() is only the ones they created (organizers.user_id); a
        // person an admin added to a team exists only in organizer_user, and
        // checking ownership alone gave them a 403 on the Organizations page.
        return $user->teams()->exists() || $user->organizers()->exists();
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
     *
     * Moderators can switch into any organizer. This is intentional — moderators
     * routinely need to assume an organizer's identity for support and content
     * review. Confirmed 2026-05-24.
     */
    public function switchTeam(User $user, Organizer $organizer): bool
    {
        return $user->belongsToOrganization($organizer) ||
               $user->isModerator() ||
               $user->ownsOrganization($organizer);
    }
}
