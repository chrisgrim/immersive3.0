<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\DissolveProject;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\MergeProjects;
use App\Actions\Projects\UpdateProject;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use Illuminate\Http\Request;

class AdminProjectController extends Controller
{
    /** For the review screen's "change": shows by name. */
    public function index(Request $request)
    {
        return Project::with(['organizer:id,name', 'editor:id,name'])
            ->withCount('runs')
            ->when($request->keywords, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'public_url' => $project->publicPath(),
                'runs_count' => $project->runs_count,
                'organizer' => $project->organizer?->only(['id', 'name']),
                'editor' => $project->editor?->only(['id', 'name']),
            ]);
    }

    /** Link a listing by hand, to a project or to a standalone listing (which makes the project). */
    public function assign(Request $request, Event $event, LinkEventToProject $links)
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id', 'required_without:source_event_id'],
            'source_event_id' => ['nullable', 'integer', 'exists:events,id'],
            'organizer_id' => ['nullable', 'integer', 'exists:organizers,id'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
        ]);

        $linked = $links->decide(
            $event,
            null,
            $links->namedTarget($validated['project_id'] ?? null, $validated['source_event_id'] ?? null),
            $request->user(),
            ! empty($validated['organizer_id']) ? Organizer::find($validated['organizer_id']) : null,
            $validated['slug'] ?? null,
        );

        return response()->json(['event' => $linked->only(['id', 'slug', 'project_id', 'instance_slug', 'public_url'])]);
    }

    public function update(Request $request, Project $project, UpdateProject $update)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u'],
            'organizer_id' => ['sometimes', 'required', 'integer', 'exists:organizers,id'],
            'editor_organizer_id' => ['sometimes', 'nullable', 'integer', 'exists:organizers,id'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
        ]);

        return response()->json(['project' => $update->handle($project, $validated, $request->user())]);
    }

    /** {project} is the duplicate: it folds into `into_project_id`. */
    public function merge(Request $request, Project $project, MergeProjects $merge)
    {
        $validated = $request->validate(['into_project_id' => ['required', 'integer', 'exists:projects,id']]);

        return response()->json(['project' => $merge->handle($project, Project::findOrFail($validated['into_project_id']), $request->user())]);
    }

    public function dissolve(Request $request, Project $project, DissolveProject $dissolve)
    {
        $run = $dissolve->handle($project, $request->user());

        return response()->json(['event' => $run->only(['id', 'slug', 'public_url'])]);
    }
}
