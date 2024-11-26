<?php

namespace App\Policies;

use App\Models\Curated\Community;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommunityPolicy
{
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

    // ... similar updates for other methods ...
}
