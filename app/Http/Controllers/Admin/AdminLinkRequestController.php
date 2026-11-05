<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\LinkReview;
use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use Illuminate\Http\Request;

class AdminLinkRequestController extends Controller
{
    /** Pending requests, escalated ones first. `escalated=1` for those only. */
    public function index(Request $request, LinkReview $review)
    {
        return $review->withRelations(ProjectLinkRequest::pending())
            ->when($request->boolean('escalated'), fn ($query) => $query->whereNotNull('escalated_at'))
            ->orderByRaw('escalated_at IS NULL')
            ->orderBy('submitted_at')
            ->paginate(20)
            ->through(fn (ProjectLinkRequest $linkRequest) => $review->present($linkRequest));
    }

    /** For a listing already live; one still in the queue is decided as it is approved. */
    public function approve(Request $request, ProjectLinkRequest $linkRequest, LinkEventToProject $links)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'source_event_id' => ['nullable', 'integer', 'exists:events,id'],
            'organizer_id' => ['nullable', 'integer', 'exists:organizers,id'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
        ]);

        $event = $links->decide(
            $linkRequest->event()->withTrashed()->firstOrFail(),
            $linkRequest,
            $links->namedTarget($validated['project_id'] ?? null, $validated['source_event_id'] ?? null),
            $request->user(),
            ! empty($validated['organizer_id']) ? Organizer::find($validated['organizer_id']) : null,
            $validated['slug'] ?? null,
        );

        return response()->json(['event' => $event->only(['id', 'slug', 'project_id', 'instance_slug', 'public_url'])]);
    }

    public function reject(Request $request, ProjectLinkRequest $linkRequest, LinkEventToProject $links)
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $links->reject($linkRequest, $request->user(), $validated['note'] ?? null);

        return response()->json(['message' => 'Request declined.']);
    }
}
