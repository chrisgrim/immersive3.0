<?php

namespace App\Actions\Projects;

use App\Actions\Admin\ModerateSubmission;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\ImageHandler;
use App\Support\Projects\InstanceAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The one way a listing becomes a run of a show, shared by web, admin and MCP.
 * Linking to a standalone listing makes the project, from that listing, and
 * both become runs in one transaction. Refusals are ValidationExceptions on
 * `project`, a denied actor an AuthorizationException.
 *
 * It bypasses Event::isEditLockedFor on purpose (old listings can be linked),
 * never goes through UpdateEventAction, never publishes anything and respects
 * Event::isHeldFrom. It may be called inside the approval's own transaction.
 *
 * Options, moderators only, when this makes a project:
 *  - maker: the Organizer that made the show (default: the first listing's)
 *  - slug:  the project's address (default: Project::addressFor)
 */
class LinkEventToProject
{
    public function __construct(private CreditLifecycle $credits) {}

    /**
     * Decide a request in its favour. A moderator may send it to a different
     * show than it asked for, with $target. If the listing it asked to join
     * has been linked since, that listing's project is used.
     */
    public function approve(ProjectLinkRequest $request, User $actor, array $options = [], Project|Event|null $target = null): Event
    {
        if ($target !== null && ! $actor->isModerator()) {
            $this->refuse('Only a moderator can send a request to a different show.');
        }

        return $this->run($request->event()->withTrashed()->firstOrFail(), $target ?? $this->requestedTarget($request), $actor, $options, $request);
    }

    /** A direct link, as a moderator does by hand or the page-keeper by approving. */
    public function link(Event $event, Project|Event $target, User $actor, array $options = []): Event
    {
        return $this->run($event, $target, $actor, $options, null);
    }

    /**
     * A moderator's whole decision: link $event to what it asked for or to
     * $target instead, and correct who made the show and its address. A new
     * project takes those as it is made; an existing one is updated first,
     * in the same transaction, and a moderator never has two steps to do.
     */
    public function decide(Event $event, ?ProjectLinkRequest $request, Project|Event|null $target, User $actor, ?Organizer $maker = null, ?string $slug = null): Event
    {
        $resolved = $target ?? ($request ? $this->requestedTarget($request) : $this->refuse('Choose a show to link this listing to.'));
        $project = $resolved instanceof Event ? $resolved->project : $resolved;

        return DB::transaction(function () use ($event, $request, $target, $resolved, $project, $actor, $maker, $slug) {
            $options = array_filter(['maker' => $maker, 'slug' => $slug]);

            if ($project && $options !== []) {
                app(UpdateProject::class)->handle($project, array_filter(['organizer_id' => $maker?->id, 'slug' => $slug]), $actor);
                $options = [];
            }

            return $request
                ? $this->approve($request, $actor, $options, $target)
                : $this->link($event, $resolved, $actor, $options);
        });
    }

    /**
     * The show a moderator named, or null if they named none. One that has
     * gone is refused here: read as "none", it would quietly confirm whatever
     * the listing asked for instead.
     */
    public function namedTarget(?int $projectId, ?int $sourceEventId): Project|Event|null
    {
        if (! $projectId && ! $sourceEventId) {
            return null;
        }

        return ($projectId ? Project::find($projectId) : Event::find($sourceEventId))
            ?? $this->refuse('That show no longer exists.');
    }

    public function reject(ProjectLinkRequest $request, User $actor, ?string $note = null): void
    {
        $this->authorizeDecision($this->requestedTarget($request), $actor);

        DB::transaction(function () use ($request, $actor, $note) {
            $fresh = ProjectLinkRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $fresh->isPending()) {
                $this->refuse('This request has already been decided.');
            }

            $fresh->update([
                'status' => ProjectLinkRequest::REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'note' => $note ?? $fresh->note,
            ]);

            AuditLog::record($fresh, 'link_request.rejected', $actor, ['event_id' => $fresh->event_id]);

            // Keeper or moderator, the presenter hears why.
            $show = $fresh->project?->name ?? $fresh->sourceEvent?->name;
            $body = "\"{$fresh->event?->name}\" will not be listed as part of \"{$show}\"."
                .($note ? "\n\nReason: {$note}" : '');

            DB::afterCommit(fn () => $this->tell($fresh->event?->organizer, $body));
        });

        $request->refresh();
    }

    private function requestedTarget(ProjectLinkRequest $request): Project|Event
    {
        $target = $request->project_id
            ? Project::withTrashed()->find($request->project_id)
            : Event::withTrashed()->find($request->source_event_id);

        return $target ?? $this->refuse('The show this request asked to join no longer exists.');
    }

    private function run(Event $event, Project|Event $target, User $actor, array $options, ?ProjectLinkRequest $request): Event
    {
        $this->authorizeDecision($target, $actor);

        if ($options !== [] && ! $actor->isModerator()) {
            $this->refuse('Only a moderator can set who made a show or its address.');
        }

        $outcome = Event::deferringSearchSync(fn () => DB::transaction(
            fn () => $this->perform($event, $target, $actor, $options, $request)
        ));

        // After commit, so a rolled-back link tells nobody. Runs at once
        // when no transaction is open around this one.
        DB::afterCommit(fn () => $this->notify($outcome['run'], $outcome['project'], $actor));

        return $event->refresh();
    }

    /** @return array{run: Event, project: Project} */
    private function perform(Event $event, Project|Event $target, User $actor, array $options, ?ProjectLinkRequest $request): array
    {
        if ($request !== null) {
            $request = ProjectLinkRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                $this->refuse('This request has already been decided.');
            }
        }

        // Ascending ids, so two approvals around the same listings cannot deadlock.
        $source = $target instanceof Event ? $target : null;
        $locked = $this->lockEvents(array_filter([$event->id, $source?->id]));
        $run = $locked[$event->id];
        $source = $source ? $locked[$source->id] : null;

        $this->assertCanJoin($run, $actor);

        // A source that has been linked since brings its project with it.
        $project = $source?->project_id
            ? Project::withTrashed()->find($source->project_id)
            : ($target instanceof Project ? $target : null);

        if ($project !== null) {
            $project = Project::withTrashed()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($project->trashed()) {
                $this->refuse('That show no longer exists.');
            }

            if (isset($options['maker']) || isset($options['slug'])) {
                $this->refuse('That show already exists; who made it and its address are changed on the show.');
            }
        } else {
            $this->assertCanBeFirst($source, $run);
            $project = $this->createProject($source, $actor, $options);
            $this->attach($source, $project, $actor);
        }

        $this->attach($run, $project, $actor);
        $this->credits->copyFromPreviousRun($run, $project);
        $this->closeRequests($run, $project, $source, $actor, $request);

        return ['run' => $run, 'project' => $project];
    }

    /** @return Collection<int, Event> keyed by id */
    private function lockEvents(array $ids): Collection
    {
        return Event::withTrashed()
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function authorizeDecision(Project|Event $target, User $actor): void
    {
        $project = $target instanceof Event ? $target->project : $target;

        // A standalone listing has no company keeping a page yet.
        $project
            ? Gate::forUser($actor)->authorize('decideLink', $project)
            : Gate::forUser($actor)->authorize('create', Project::class);
    }

    private function assertCanJoin(Event $run, User $actor): void
    {
        if ($run->isHeldFrom($actor)) {
            $this->refuse('This listing is not available.');
        }

        if ($run->trashed()) {
            $this->refuse('This listing has been deleted.');
        }

        // Approving a link never publishes a draft.
        if (! in_array($run->status, Event::LIVE_STATUSES, true)) {
            $this->refuse('A listing joins a show once it is approved.');
        }

        if ($run->isLinked()) {
            $this->refuse('This listing is already part of a show. Take it off that show first.');
        }
    }

    private function assertCanBeFirst(?Event $source, Event $run): void
    {
        if ($source === null) {
            $this->refuse('That show no longer exists.');
        }

        if ($source->is($run)) {
            $this->refuse('A listing cannot join itself.');
        }

        if ($source->trashed() || ! in_array($source->status, Event::LIVE_STATUSES, true)) {
            $this->refuse('The listing this asked to join is not live.');
        }
    }

    private function createProject(Event $first, User $actor, array $options): Project
    {
        $maker = $options['maker'] ?? $first->organizer;

        if (! $maker instanceof Organizer) {
            $this->refuse('A show needs a company that made it.');
        }

        $slug = $options['slug'] ?? Project::addressFor($first, $maker);

        if (Project::slugTaken($slug, $first)) {
            $this->refuse("The address \"{$slug}\" is taken.");
        }

        $project = Project::create([
            'slug' => $slug,
            'name' => $first->name,
            'description' => $first->description,
            'organizer_id' => $maker->id,
        ]);

        // The project's own files, never shared by path.
        ImageHandler::duplicateImages($first, $project, 'project');

        // The address is the project's now; an old alias of it would shadow nothing.
        SlugRedirect::where('path', 'events/'.$slug)->delete();

        AuditLog::record($project, 'project.created', $actor, [
            'first_event_id' => $first->id,
            'slug' => $slug,
            'organizer_id' => $maker->id,
        ]);

        return $project;
    }

    /**
     * The listing becomes a run: its writers go up, it takes an address under
     * the show, and its old address redirects there. The one exception is an
     * address the project took over: that is the project's from now on and
     * gets no row, and the listing's unchanged events.slug is the one
     * permitted collision between projects and events.
     */
    private function attach(Event $run, Project $project, User $actor): void
    {
        $from = $run->publicPath();

        $this->credits->moveWritersUp($run, $project);

        $run->instance_slug = InstanceAddress::allocate($run, $project);
        $run->project_id = $project->id;
        $run->setRelation('project', $project);
        $run->save();

        if (strcasecmp($run->slug, $project->slug) !== 0) {
            SlugRedirect::remember('events/'.$run->slug, $run);
        }

        SlugRedirect::forget($run->addressPath(), $run);

        AuditLog::record($run, 'project.linked', $actor, [
            'project_id' => $project->id,
            'from' => $from,
            'to' => $run->publicPath(),
        ]);
    }

    /** The listing's own requests are settled by this link, whatever they asked for. */
    private function closeRequests(Event $run, Project $project, ?Event $source, User $actor, ?ProjectLinkRequest $decided): void
    {
        if ($decided !== null) {
            $decided->update([
                'status' => ProjectLinkRequest::APPROVED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'project_id' => $project->id,
                'source_event_id' => null,
            ]);

            AuditLog::record($decided, 'link_request.approved', $actor, ['event_id' => $run->id, 'project_id' => $project->id]);
        }

        ProjectLinkRequest::pending()
            ->whereIn('event_id', array_filter([$run->id, $source?->id]))
            ->update(['status' => ProjectLinkRequest::WITHDRAWN]);

        // Anyone else who asked to join the first listing now asks to join
        // its show, and that show has a company to tell.
        if ($source !== null) {
            $waiting = ProjectLinkRequest::pending()->where('source_event_id', $source->id)->get();

            ProjectLinkRequest::whereKey($waiting->modelKeys())
                ->update(['project_id' => $project->id, 'source_event_id' => null]);

            $waiting->filter->submitted_at->each(fn (ProjectLinkRequest $other) => app(TellKeeper::class)->requestWaiting($other->refresh()));
        }
    }

    /**
     * On a moderator's link, the listing's organizer and the page-keeping
     * organizer are told. On a keeper's, the presenter: the keeper already knows.
     */
    private function notify(Event $run, Project $project, User $actor): void
    {
        $body = "\"{$run->name}\" is now listed as part of \"{$project->name}\".";

        $organizers = $actor->isModerator()
            ? collect([$run->organizer, $project->keeper()])
            : collect([$run->organizer]);

        $organizers->filter()->unique('id')->each(fn (Organizer $organizer) => $this->tell($organizer, $body));
    }

    private function tell(?Organizer $organizer, string $body): void
    {
        if ($organizer === null) {
            return;
        }

        try {
            app(ModerateSubmission::class)->notifyOwner($organizer, $organizer->owner, $body, $body, 'update');
        } catch (\Exception $e) {
            // The decision stands; a mail-server hiccup must not undo the click.
            report($e);
            Log::warning('Link notification failed: '.$e->getMessage());
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['project' => $message]);
    }
}
