<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Takes a run off its show. It becomes a plain listing at its own address and
 * links to the run follow it; the show keeps every address it owns, and keeps
 * going, even with one run or none left.
 */
class UnlinkEvent
{
    public function __construct(private CreditLifecycle $credits) {}

    public function handle(Event $event, User $actor): Event
    {
        $project = $event->project_id ? Project::find($event->project_id) : null;

        if ($project === null) {
            throw ValidationException::withMessages(['project' => 'This listing is not part of a show run.']);
        }

        Gate::forUser($actor)->authorize('decideLink', $project);

        Event::deferringSearchSync(fn () => DB::transaction(function () use ($event, $project, $actor) {
            // Listing first, then project: the order linking takes them in.
            $run = Event::withTrashed()
                ->withoutGlobalScope(LatestPublishedFirstScope::class)
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($run->project_id !== $project->id || $run->trashed() || $run->isHeldFrom($actor)) {
                throw ValidationException::withMessages(['project' => 'This listing is not part of that show run.']);
            }

            $from = $run->addressPath();
            $instance = $run->instance_slug;

            $run->forceFill(['project_id' => null, 'instance_slug' => null, 'edition' => null]);
            $run->setRelation('project', null);
            // Never a project-owned address: a first listing's slug is the
            // project's, and gets a fresh one. Picture files stay where they
            // are: rows store their paths, and the folder name is only a name.
            $run->slug = $run->plainSlug();
            $run->save();

            $this->credits->copyWritersDown($project, $run);

            SlugRedirect::remember($from, $run);
            $project->reserveFormerPaths($run, $instance);
            SlugRedirect::forget($run->addressPath(), $run);

            AuditLog::record($run, 'project.unlinked', $actor, [
                'project_id' => $project->id,
                'from' => '/'.$from,
                'to' => $run->publicPath(),
            ]);
        }));

        return $event->refresh();
    }
}
