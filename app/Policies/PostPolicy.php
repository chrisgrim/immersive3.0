<?php

namespace App\Policies;

use App\Models\Curated\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy
{
    /**
     * Determine whether the user can update the post.
     */
    public function update(User $user, Post $post): Response
    {
        return $this->isCuratorOrAdmin($user, $post)
            ? Response::allow()
            : Response::deny('You do not have permission to update this post.');
    }

    /**
     * Determine whether the user can see the post.
     */
    public function preview(?User $user, Post $post): Response
    {
        if ($post->status === 'p') {
            return Response::allow();
        }

        return $user && $this->isCuratorOrAdmin($user, $post)
            ? Response::allow()
            : Response::deny('This post is not publicly available.');
    }

    /**
     * Determine whether the user can delete the post.
     */
    public function destroy(User $user, Post $post): Response
    {
        return $this->isCuratorOrAdmin($user, $post)
            ? Response::allow()
            : Response::deny('You do not have permission to delete this post.');
    }

    /**
     * Check if user is a curator or admin.
     */
    private function isCuratorOrAdmin(User $user, Post $post): bool
    {
        return $post->community->curators->contains('id', $user->id) 
            || in_array($user->type, ['a', 'm']);
    }
}
