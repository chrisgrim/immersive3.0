<?php

namespace App\Policies;

use App\Models\Curated\Community;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommunityPolicy
{
    /**
     * Determine whether the user can manage curators (add/remove/change owner).
     */
    public function manageCurators(User $user, Community $community): Response
    {
        return ($user->id === $community->user_id || 
                in_array($user->type, ['a', 'm']))
                ? Response::allow()
                : Response::deny('Only the community owner can manage curators.');
    }

    /**
     * Determine whether the user is a curator of the community.
     */
    public function curator(User $user, Community $community): Response
    {
        return ($community->curators->contains('id', $user->id) || 
                in_array($user->type, ['a', 'm']))
                ? Response::allow()
                : Response::deny('You must be a curator to perform this action.');
    }

    /**
     * Determine whether the user can update the community.
     */
    public function update(User $user, Community $community): Response
    {
        return ($community->curators->contains('id', $user->id) || 
                in_array($user->type, ['a', 'm']))
                ? Response::allow()
                : Response::deny('You do not have permission to update this community.');
    }

    /**
     * Determine whether the user can see the community.
     */
    public function preview(?User $user, Community $community): Response
    {
        if ($community->status === 'p') {
            return Response::allow();
        }

        return ($user && ($community->curators->contains('id', $user->id) || 
                in_array($user->type, ['a', 'm'])))
                ? Response::allow()
                : Response::deny('You do not have permission to preview this community.');
    }

    /**
     * Determine whether the user is the owner of the community.
     */
    public function owner(User $user, Community $community): Response
    {
        return ($user->id === $community->user_id || 
                in_array($user->type, ['a', 'm']))
                ? Response::allow()
                : Response::deny('You must be the owner to perform this action.');
    }

    /**
     * Determine whether the user can remove themselves as a curator.
     */
    public function removeSelf(User $user, Community $community): Response
    {
        // Don't allow owner to remove themselves
        if ($user->id === $community->user_id) {
            return Response::deny('The owner cannot remove themselves from the community.');
        }

        return $community->curators->contains('id', $user->id)
            ? Response::allow()
            : Response::deny('You are not a curator of this community.');
    }

}
