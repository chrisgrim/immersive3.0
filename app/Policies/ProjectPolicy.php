<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /** Whoever keeps the page, or a moderator. */
    public function edit(User $user, Project $project): bool
    {
        if ($user->isModerator()) {
            return true;
        }

        $keeper = $project->keeper();

        return $keeper !== null && $user->belongsToOrganization($keeper);
    }

    /** Approving or refusing a link, and taking a run off the show. */
    public function decideLink(User $user, Project $project): bool
    {
        return $this->edit($user, $project);
    }

    /**
     * Making one by hand is staff's. A company also starts its own from a live
     * listing with Duplicate's "Same show, new run?" (LinkEventToProject::startFrom).
     */
    public function create(User $user): bool
    {
        return $user->isModerator();
    }

    public function dissolve(User $user, Project $project): bool
    {
        return $user->isModerator();
    }

    /** Who made it, its address and who keeps the page are a moderator's to change. */
    public function update(User $user, Project $project): bool
    {
        return $user->isModerator();
    }

    public function merge(User $user): bool
    {
        return $user->isModerator();
    }
}
