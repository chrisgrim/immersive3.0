<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A listing asks to join a show: an existing project, or a standalone listing
 * (approval then makes the project). A listing has one pending request at a
 * time; asking again replaces it. LinkEventToProject decides them.
 */
class RequestLink
{
    public function handle(Event $event, Project|Event $target, User $actor, ?string $note = null): ProjectLinkRequest
    {
        Gate::forUser($actor)->authorize('requestLink', $event);

        if ($event->isLinked()) {
            $this->refuse('This listing is already part of a show.');
        }

        if ($target instanceof Event) {
            if ($target->is($event)) {
                $this->refuse('A listing cannot join itself.');
            }

            if ($target->trashed()) {
                $this->refuse('That listing has been deleted.');
            }
        }

        if ($target instanceof Project && $target->trashed()) {
            $this->refuse('That show no longer exists.');
        }

        return $this->open($event, $target, $actor->id, $note, $actor);
    }

    /**
     * What Event::duplicate() asks for on its copy: the source's show if it
     * has one, otherwise the published source itself. A copy of anything else
     * asks for nothing. It survives renames, and the copy's team can remove it.
     */
    public function forDuplicate(Event $copy, Event $source, ?int $requestedBy): ?ProjectLinkRequest
    {
        $target = match (true) {
            $source->project !== null => $source->project,
            $source->status === Event::STATUS_PUBLISHED => $source,
            default => null,
        };

        return $target ? $this->open($copy, $target, $requestedBy) : null;
    }

    /** The listing's team takes its request back. */
    public function withdraw(Event $event, User $actor): void
    {
        Gate::forUser($actor)->authorize('requestLink', $event);

        DB::transaction(function () use ($event, $actor) {
            foreach (ProjectLinkRequest::pending()->where('event_id', $event->id)->lockForUpdate()->get() as $request) {
                $request->update(['status' => ProjectLinkRequest::WITHDRAWN]);
                AuditLog::record($request, 'link_request.withdrawn', $actor, ['event_id' => $event->id]);
            }
        });
    }

    private function open(Event $event, Project|Event $target, ?int $requestedBy, ?string $note = null, ?User $actor = null): ProjectLinkRequest
    {
        return DB::transaction(function () use ($event, $target, $requestedBy, $note, $actor) {
            // Serialises a double click: the second replaces the first.
            Event::withTrashed()->whereKey($event->id)->reorder()->lockForUpdate()->first();

            ProjectLinkRequest::pending()->where('event_id', $event->id)
                ->update(['status' => ProjectLinkRequest::WITHDRAWN]);

            // A linked source means its show.
            $project = $target instanceof Project ? $target : $target->project;

            $request = ProjectLinkRequest::create([
                'event_id' => $event->id,
                'project_id' => $project?->id,
                'source_event_id' => $project ? null : $target->id,
                'requested_by' => $requestedBy,
                'status' => ProjectLinkRequest::PENDING,
                // A draft's clock starts when it is submitted (Event's saved hook).
                'submitted_at' => $this->isSubmitted($event) ? now() : null,
                'note' => $note,
            ]);

            AuditLog::record($request, 'link_request.created', $actor ?? ($requestedBy ? User::find($requestedBy) : null), [
                'event_id' => $event->id,
                'project_id' => $project?->id,
                'source_event_id' => $request->source_event_id,
            ]);

            return $request;
        });
    }

    private function isSubmitted(Event $event): bool
    {
        return in_array($event->status, [Event::STATUS_IN_REVIEW, ...Event::LIVE_STATUSES], true);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['project' => $message]);
    }
}
