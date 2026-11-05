<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Scopes\LatestPublishedFirstScope;
use App\Support\Projects\InstanceAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Folds a duplicate project into another. The loser is kept, soft-deleted with
 * its own writers, and the log records what was linked to each, so a mistaken
 * merge can be undone. Every old address leads on: the loser's goes to the
 * winner, and each run's goes to where the run is now.
 */
class MergeProjects
{
    public function __construct(private CreditLifecycle $credits) {}

    public function handle(Project $loser, Project $winner, User $actor): Project
    {
        Gate::forUser($actor)->authorize('merge', Project::class);

        if ($loser->is($winner)) {
            throw ValidationException::withMessages(['project' => 'A show cannot be merged into itself.']);
        }

        Event::deferringSearchSync(fn () => DB::transaction(function () use ($loser, $winner, $actor) {
            $locked = Project::withTrashed()->whereKey([$loser->id, $winner->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $from = $locked[$loser->id];
            $into = $locked[$winner->id];

            if ($from->trashed() || $into->trashed()) {
                throw ValidationException::withMessages(['project' => 'That show no longer exists.']);
            }

            $address = $from->slug;
            $formerPaths = SlugRedirect::where('redirectable_type', $from->getMorphClass())
                ->where('redirectable_id', $from->id)
                ->pluck('path');

            $runs = Event::withTrashed()
                ->withoutGlobalScope(LatestPublishedFirstScope::class)
                ->where('project_id', $from->id)
                ->lockForUpdate()
                ->get();

            $moved = [];

            foreach ($runs as $run) {
                if ($run->trashed()) {
                    // A restore finds the show that survived.
                    $run->forceFill(['project_id' => $into->id])->save();

                    continue;
                }

                $run->setRelation('project', $from);
                $oldPath = $run->addressPath();
                $oldInstance = $run->instance_slug;

                $run->instance_slug = InstanceAddress::allocate($run, $into, $oldInstance);
                $run->project_id = $into->id;
                $run->setRelation('project', $into);
                $run->save();

                SlugRedirect::remember($oldPath, $run);

                // The route reads a former slug's nested path by swapping the
                // show's address, which only holds while the instance is unchanged.
                foreach ($formerPaths as $path) {
                    SlugRedirect::remember("{$path}/{$oldInstance}", $run);
                }

                SlugRedirect::forget($run->addressPath(), $run);

                $moved[] = ['event_id' => $run->id, 'from' => '/'.$oldPath, 'to' => $run->publicPath()];
            }

            $this->credits->addMissing($from->writers()->approved()->orderBy('rank')->orderBy('id')->get(), $into);

            SlugRedirect::repoint($from, $into);
            SlugRedirect::remember('events/'.$address, $into);

            $waiting = ProjectLinkRequest::pending()->where('project_id', $from->id)->get();
            ProjectLinkRequest::whereKey($waiting->modelKeys())->update(['project_id' => $into->id]);
            $waiting->filter->submitted_at->each(fn (ProjectLinkRequest $request) => app(TellKeeper::class)->requestWaiting($request->refresh()));

            AuditLog::record($from, 'project.merged', $actor, [
                'into_project_id' => $into->id,
                'address' => $address,
                'organizer_id' => $from->organizer_id,
                'editor_organizer_id' => $from->editor_organizer_id,
                'runs' => $moved,
            ]);

            $from->delete();
        }));

        $loser->refresh();

        return $winner->refresh();
    }
}
