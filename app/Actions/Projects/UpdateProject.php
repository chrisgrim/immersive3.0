<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A moderator corrects who made a show, hands its page to another company, or
 * gives it another address. The model's hook keeps the old address as a
 * redirect. Refused if the new address is anybody else's.
 *
 * @param  array{organizer_id?: int, editor_organizer_id?: int|null, slug?: string}  $changes
 */
class UpdateProject
{
    public function handle(Project $project, array $changes, User $actor): Project
    {
        Gate::forUser($actor)->authorize('update', $project);

        $changes = Arr::only($changes, ['organizer_id', 'editor_organizer_id', 'slug']);

        return DB::transaction(function () use ($project, $changes, $actor) {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (isset($changes['slug']) && $changes['slug'] !== $project->slug && Project::slugTaken($changes['slug'], null, $project->id)) {
                throw ValidationException::withMessages(['slug' => "The address \"{$changes['slug']}\" is taken."]);
            }

            if (isset($changes['organizer_id']) && ! Organizer::whereKey($changes['organizer_id'])->exists()) {
                throw ValidationException::withMessages(['organizer_id' => 'That organizer does not exist.']);
            }

            $before = $project->only(array_keys($changes));
            $project->update($changes);

            if ($project->wasChanged()) {
                AuditLog::record($project, 'project.updated', $actor, ['before' => $before, 'after' => $project->only(array_keys($changes))]);
            }

            return $project;
        });
    }
}
