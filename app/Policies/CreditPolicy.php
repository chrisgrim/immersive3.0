<?php

namespace App\Policies;

use App\Enums\CreditDepartment;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Credits sit outside can:manage,event, because curators do not pass it.
 * Called with the class: $user->can('editOnRun', [Credit::class, $event, 'cast']).
 */
class CreditPolicy
{
    /** Seeing a run's credits panel: anyone who may edit some row of it. */
    public function viewOnRun(User $user, Event $event): bool
    {
        return $this->curates($user, $event) || Gate::forUser($user)->allows('manage', $event);
    }

    /**
     * Adding or editing a row in $department on a run. A linked run never
     * holds writers, for anyone: those are on the show. Moderators may edit
     * any other row; curators may edit cast on any run, and nothing else; the
     * listing's team edits under today's rules for the listing.
     */
    public function editOnRun(User $user, Event $event, string $department): bool
    {
        if ($department === CreditDepartment::Writing->value && $event->isLinked()) {
            return false;
        }

        if ($user->isModerator()) {
            return true;
        }

        if ($department === CreditDepartment::Cast->value && $this->curates($user, $event)) {
            return true;
        }

        return $this->editsAsTeam($user, $event);
    }

    /** The show's writers belong to whoever keeps the page. */
    public function editOnProject(User $user, Project $project): bool
    {
        return Gate::forUser($user)->allows('edit', $project);
    }

    /**
     * "Copy this run's credits onto…": only someone who may edit the
     * destination run under its own rules, or a moderator. Keeping the project
     * page confers nothing over other presenters' runs.
     */
    public function copyOnto(User $user, Event $destination): bool
    {
        return $user->isModerator() || $this->editsAsTeam($user, $destination);
    }

    /**
     * Curators reach past their own organizers, so as with moderators an API
     * token has to carry that power: mcp:use is the owner's own organizers.
     * isCurator() alone does not ask.
     */
    private function curates(User $user, Event $event): bool
    {
        return $user->isCurator() && $user->credentialAllowsModeration() && ! $event->isHeldFrom($user);
    }

    private function editsAsTeam(User $user, Event $event): bool
    {
        return Gate::forUser($user)->allows('manage', $event)
            && ! $event->isInReviewFor($user)
            && ! $event->isEditLockedFor($user);
    }
}
