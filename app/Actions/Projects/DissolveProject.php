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
    /** Recorded by LinkEventToProject::startFrom. */
    public const STARTED = 'project.started_from_duplicate';

    public function __construct(private CreditLifecycle $credits) {}

    public function handle(Project $project, User $actor): Event
    {
        Gate::forUser($actor)->authorize('dissolve', $project);

        return $this->dissolve($project, $actor);
    }

    /**
     * A show run a company started by duplicating (LinkEventToProject::startFrom)
     * whose copy never joined: deleted, or taken back while it was still
     * asking. With its first listing alone in it and nobody else asking, it
     * was never really a show run, so it is undone and that listing is a
     * plain one again. Any other show run is left as it is.
     */
    public function undoUnusedStart(int $projectId, ?User $actor): void
    {
        $project = Project::find($projectId);

        if (! $project || ! AuditLog::where('subject_type', $project->getMorphClass())->where('subject_id', $project->id)->where('action', self::STARTED)->exists()) {
            return;
        }

        $runs = Event::withoutGlobalScope(LatestPublishedFirstScope::class)->where('project_id', $project->id)->count();
        $asking = ProjectLinkRequest::pending()->where('project_id', $project->id)
            ->whereHas('event', fn ($query) => $query->withoutGlobalScope(LatestPublishedFirstScope::class))
            ->exists();
        // Only a start nothing ever came of: once any listing has joined it (the
        // copy, or anyone since), or a run of it was deleted, it is a real show
        // run, and nobody else's request ending undoes it.
        $first = Event::withoutGlobalScope(LatestPublishedFirstScope::class)->where('project_id', $project->id)->value('id');
        $used = ProjectLinkRequest::where('project_id', $project->id)->where('status', ProjectLinkRequest::APPROVED)->exists()
            || Event::onlyTrashed()->withoutGlobalScope(LatestPublishedFirstScope::class)->where('project_id', $project->id)->exists()
            // Linked by staff directly (no approved request): any other listing ever linked to it.
            || AuditLog::where('subject_type', (new Event)->getMorphClass())->where('action', 'project.linked')
                ->where('details->project_id', $project->id)->where('subject_id', '!=', $first)->exists();

        if ($runs !== 1 || $asking || $used) {
            return;
        }

        try {
            $this->dissolve($project, $actor);
        } catch (ValidationException) {
            // Something changed meanwhile (a second run joined): it stays.
        }
    }

    /**
     * After a listing is deleted (by its team or by staff): what it was asking
     * to join is withdrawn, a show run started by duplicating it that nothing
     * came of is undone, and a show run with nothing left goes.
     */
    public function afterEventDeleted(Event $event, ?User $actor): void
    {
        $pending = ProjectLinkRequest::pending()->where('event_id', $event->id);
        $askedTo = (clone $pending)->whereNotNull('project_id')->pluck('project_id');
        $pending->update(['status' => ProjectLinkRequest::WITHDRAWN]);
        // Others asking to join this listing itself (not a show run yet) have nothing left to join.
        ProjectLinkRequest::pending()->where('source_event_id', $event->id)->update(['status' => ProjectLinkRequest::WITHDRAWN]);

        foreach ($askedTo->unique() as $projectId) {
            $this->afterRequestEnded($projectId, $actor);
        }

        if ($event->project_id) {
            $this->removeIfEmpty($event->project_id, $actor);
        }
    }

    /**
     * A request to join this show run ended without joining (withdrawn,
     * turned down, switched to another show, or its listing deleted): a start
     * nothing came of is undone, and a show run with nothing left goes.
     */
    public function afterRequestEnded(int $projectId, ?User $actor): void
    {
        $this->undoUnusedStart($projectId, $actor);
        $this->removeIfEmpty($projectId, $actor);
    }

    /**
     * A show run with nothing left: its listings all deleted and nobody asking
     * to join. Its page would be empty, so it goes (soft-deleted, its address
     * released). One with a copy still waiting stays: the copy joins it and the
     * show carries on. A deleted run restored later comes back as a plain listing.
     */
    public function removeIfEmpty(int $projectId, ?User $actor): void
    {
        DB::transaction(function () use ($projectId, $actor) {
            $project = Project::whereKey($projectId)->lockForUpdate()->first();

            if (! $project) {
                return;
            }

            $runs = Event::withoutGlobalScope(LatestPublishedFirstScope::class)->where('project_id', $project->id)->exists();
            $asking = ProjectLinkRequest::pending()->where('project_id', $project->id)
                ->whereHas('event', fn ($query) => $query->withoutGlobalScope(LatestPublishedFirstScope::class))
                ->exists();

            if ($runs || $asking) {
                return;
            }

            AuditLog::record($project, 'project.emptied', $actor);
            $project->delete();
        });
    }

    private function dissolve(Project $project, ?User $actor): Event
    {
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
