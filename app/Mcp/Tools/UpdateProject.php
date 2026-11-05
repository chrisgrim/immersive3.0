<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\UpdateProject as Update;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Rename a show, correct who made it, hand its page to another organizer (null gives it back to the maker), or change its address. An old address keeps redirecting.')]
class UpdateProject extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate([
            'project_slug' => 'required|string',
            'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/[\p{L}\p{N}]/u'],
            'organizer_id' => 'sometimes|required|integer|exists:organizers,id',
            'editor_organizer_id' => 'sometimes|nullable|integer|exists:organizers,id',
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
        ]);

        $project = Project::where('slug', $validated['project_slug'])->first();

        if ($project === null) {
            return Response::error('No such show.');
        }

        return $this->attempt(fn () => ['project' => app(Update::class)->handle($project, $validated, $request->user())->only(['id', 'name', 'slug', 'organizer_id', 'editor_organizer_id'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_slug' => $schema->string()->description('The show.')->required(),
            'name' => $schema->string()->description('A new name. The address stays.'),
            'organizer_id' => $schema->integer()->description('Who made it.'),
            'editor_organizer_id' => $schema->integer()->description('Who keeps the page; null for the maker.'),
            'slug' => $schema->string()->description('A new address.'),
        ];
    }
}
