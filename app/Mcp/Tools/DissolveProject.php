<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\DissolveProject as Dissolve;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. End a show with exactly one run left: that run takes the show\'s address and the show\'s page goes.')]
class DissolveProject extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate(['project_slug' => 'required|string']);
        $project = Project::where('slug', $validated['project_slug'])->first();

        if ($project === null) {
            return Response::error('No such show.');
        }

        return $this->attempt(fn () => ['event' => app(Dissolve::class)->handle($project, $request->user())->only(['id', 'slug', 'public_url'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['project_slug' => $schema->string()->description('The show to end.')->required()];
    }
}
