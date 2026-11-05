<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A moderator ends a project with exactly one run left. That run, whichever
 * it is, takes the project's live address and every old address the project
 * owned; the project is soft-deleted and releases its slug, so a later second
 * run re-creates the show by the normal takeover.
 */
class DissolveProject
{
    public function __construct(private CreditLifecycle $credits) {}

    public function handle(Project $project, User $actor): Event
    {
        Gate::forUser($actor)->authorize('dissolve', $project);

        return Event::deferringSearchSync(fn () => DB::transaction(function () use ($project, $actor) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->first();

            $runs = $locked
                ? Event::withoutGlobalScope(LatestPublishedFirstScope::class)->where('project_id', $locked->id)->lockForUpdate()->get()
                : collect();

            if ($runs->count() !== 1) {
                throw ValidationException::withMessages(['project' => 'A show can be dissolved only with exactly one run left.']);
            }

            $run = $runs->first();
            $address = $locked->slug;
            $from = $run->addressPath();

            $run->forceFill(['project_id' => null, 'instance_slug' => null, 'edition' => null, 'slug' => $address]);
            $run->setRelation('project', null);
            $run->save();

            $this->credits->copyWritersDown($locked, $run);

            // Its old addresses (former slugs, old instance paths) are the run's now.
            SlugRedirect::repoint($locked, $run);

            SlugRedirect::remember($from, $run);
            SlugRedirect::forget($run->addressPath(), $run);

            // Whoever was waiting to join this show now asks the listing it became.
            ProjectLinkRequest::pending()->where('project_id', $locked->id)
                ->update(['project_id' => null, 'source_event_id' => $run->id]);

            AuditLog::record($locked, 'project.dissolved', $actor, [
                'event_id' => $run->id,
                'address' => $address,
                'from' => '/'.$from,
            ]);

            $locked->delete();
            $project->refresh();

            return $run;
        }));
    }
}
