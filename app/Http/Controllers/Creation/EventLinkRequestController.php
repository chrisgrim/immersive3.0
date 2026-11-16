<?php

namespace App\Http\Controllers\Creation;

use App\Actions\Projects\DissolveProject;
use App\Actions\Projects\PrefillFromShow;
use App\Actions\Projects\RequestLink;
use App\Actions\Projects\SuggestShows;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** A listing's team asks to join a show, or takes the request back. */
class EventLinkRequestController extends Controller
{
    public function store(Request $request, Event $event, RequestLink $link, PrefillFromShow $prefill)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'required_without:source_event_id'],
            'source_event_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // No exists rule: a missing id and a hidden one answer alike.
        $target = ! empty($validated['project_id'])
            ? Project::find($validated['project_id'])
            : Event::find($validated['source_event_id']);

        if ($target === null) {
            throw ValidationException::withMessages(['project' => RequestLink::UNAVAILABLE]);
        }

        $linkRequest = $link->handle($event, $target, $request->user(), $validated['note'] ?? null);
        $filled = $prefill->handle($event, $target, $request->user());

        return response()->json([
            'link_request' => $linkRequest->only(['id', 'status', 'project_id', 'source_event_id']),
            'filled' => $filled,
        ], 201);
    }

    /** The team has looked at the details that differ by run. */
    public function confirm(Request $request, Event $event)
    {
        $linkRequest = $event->pendingLinkRequest();

        abort_unless($linkRequest !== null, 404);

        $linkRequest->update(['details_confirmed_at' => now()]);

        AuditLog::record($linkRequest, 'link_request.details_confirmed', $request->user());

        return response()->json(['message' => 'Confirmed.']);
    }

    /**
     * The show runs this listing's company could join from the bottom bar of
     * the form: the ones it made or keeps, and the ones it already has runs
     * of. With a picture each: the show's own, or its first run's.
     */
    public function choices(Request $request, Event $event)
    {
        $organizerId = $event->organizer_id;

        // Besides its own company's: another company's show run this one is
        // named after (picked on the name step), so switching away and back
        // finds it again, and the one it is asking to join now.
        $alsoIds = collect(app(SuggestShows::class)->handle((string) $event->name, $event->id, $request->user()))
            ->where('type', 'project')->pluck('id')
            ->merge(ProjectLinkRequest::pending()->where('event_id', $event->id)->whereNotNull('project_id')->pluck('project_id'))
            ->unique()->values();

        // Only shows asking to join would accept (Project::visibleTo, as RequestLink checks).
        $projects = Project::visibleTo($request->user())
            ->where(fn ($query) => $query
                ->where('organizer_id', $organizerId)
                ->orWhere('editor_organizer_id', $organizerId)
                ->orWhereHas('runs', fn ($runs) => $runs->where('organizer_id', $organizerId))
                ->orWhereIn('id', $alsoIds))
            ->select(['id', 'name', 'slug', 'largeImagePath'])
            ->addSelect(['first_run_image' => Event::select('largeImagePath')
                ->whereColumn('events.project_id', 'projects.id')
                ->where('status', Event::STATUS_PUBLISHED) // never an unannounced run's picture
                ->whereNotNull('largeImagePath')
                ->reorder('id')
                ->limit(1)])
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'image' => $project->largeImagePath ?? $project->first_run_image,
            ]);

        return response()->json(['choices' => $projects->values()]);
    }

    public function destroy(Request $request, Event $event, RequestLink $link)
    {
        $askedTo = ProjectLinkRequest::pending()->where('event_id', $event->id)->whereNotNull('project_id')->pluck('project_id');
        $link->withdraw($event, $request->user());
        // A show run started by duplicating this one is undone if it is now alone.
        foreach ($askedTo->unique() as $projectId) {
            app(DissolveProject::class)->afterRequestEnded($projectId, $request->user());
        }

        return response()->json(['message' => 'Request removed.']);
    }
}
