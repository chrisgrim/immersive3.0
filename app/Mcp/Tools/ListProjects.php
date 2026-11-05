<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List shows on Everything Immersive: a show is the work itself, and its listings are its runs. Only shows with a published run appear, so an unannounced one stays out of sight. Pass a project id to update-event to ask to join one, or use the show\'s slug with set-credits.')]
class ListProjects extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'keywords' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $projects = Project::with('organizer:id,name')
            ->withCount(['runs' => fn ($query) => $query->where('status', Event::STATUS_PUBLISHED)])
            ->whereHas('runs', fn ($query) => $query->where('status', Event::STATUS_PUBLISHED))
            ->when($validated['keywords'] ?? null, fn ($query, $keywords) => $query->where('name', 'like', '%'.addcslashes($keywords, '\\%_').'%'))
            ->orderBy('name')
            ->limit($validated['limit'] ?? 20)
            ->get();

        return Response::json(['projects' => $projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'public_url' => $project->publicPath(),
            'made_by' => $project->organizer?->name,
            'published_runs' => $project->runs_count,
        ])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->string()->description('Part of the show\'s name.'),
            'limit' => $schema->integer()->description('How many, 1-50 (default 20).'),
        ];
    }
}
