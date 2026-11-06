<?php

namespace App\Actions\Projects;

use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a moderator needs to decide a link: the request and what it asked to
 * join (who made it, who keeps it), and shows with a similar name. Plain
 * arrays: Event's appended accessors query per row.
 */
class LinkReview
{
    public function __construct(private SuggestShows $suggest) {}

    /**
     * Sets link_request on each event, for its JSON, and name_matches for the
     * review screen. The queue's cards use neither, and a search per card is
     * twenty searches a page.
     */
    public function attachTo(Collection $events, ?User $viewer = null, bool $withMatches = false): void
    {
        $requests = $this->withRelations(ProjectLinkRequest::pending()->whereIn('event_id', $events->pluck('id')))
            ->get()
            ->keyBy('event_id');

        foreach ($events as $event) {
            $event->setAttribute('link_request', isset($requests[$event->id]) ? $this->present($requests[$event->id]) : null);

            if ($withMatches) {
                $event->setAttribute('name_matches', $this->suggest->handle((string) $event->name, $event->id, $viewer));
            }
        }
    }

    public function withRelations($query)
    {
        return $query->with(['project.organizer:id,name', 'project.editor:id,name', 'sourceEvent.organizer:id,name', 'requester:id,name', 'event.organizer:id,name']);
    }

    /** @return array<string, mixed> */
    public function present(ProjectLinkRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'note' => $request->note,
            'submitted_at' => $request->submitted_at,
            'escalated_at' => $request->escalated_at,
            'requested_by' => $request->requester?->only(['id', 'name']),
            'event' => $this->listing($request->event),
            'project' => $this->project($request->project),
            'source_event' => $this->listing($request->sourceEvent),
        ];
    }

    private function project(?Project $project): ?array
    {
        if ($project === null) {
            return null;
        }

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'public_url' => $project->publicPath(),
            'organizer' => $project->organizer?->only(['id', 'name']),
            'keeper' => $project->keeper()?->only(['id', 'name']),
        ];
    }

    private function listing(?Event $event): ?array
    {
        if ($event === null) {
            return null;
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'status' => $event->status,
            'public_url' => $event->publicPath(),
            'organizer' => $event->organizer?->only(['id', 'name']),
        ];
    }
}
