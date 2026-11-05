<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\SetCredits as SetCreditsAction;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Event;
use App\Models\Project;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Replace a credits list: give event_slug for a run (everyone but its writers, once it is part of a show) or project_slug for a show (its writers), with the full list you want. Rows are matched by id when you send one; anything left out is removed. Get artist ids from search-artists or propose-artist. Departments: writing, creative, cast, production. A game master is cast.')]
class SetCredits extends Tool
{
    use AnswersShowActions;

    private const NOT_YOURS = 'No event or show with that slug whose credits you can edit.';

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate(SetCreditsAction::RULES + [
            'event_slug' => 'required_without:project_slug|nullable|string',
            'project_slug' => 'nullable|string',
        ]);

        $target = ! empty($validated['event_slug'])
            ? Event::withoutGlobalScope(LatestPublishedFirstScope::class)->where('slug', $validated['event_slug'])->first()
            : Project::where('slug', $validated['project_slug'])->first();

        // One message whether it does not exist or is someone else's.
        if ($target === null || ($target instanceof Event && ! $user->can('viewOnRun', [\App\Models\Credit::class, $target]))) {
            return Response::error(self::NOT_YOURS);
        }

        return $this->attempt(fn () => ['credits' => app(SetCreditsAction::class)->handle($target, $validated['credits'], $user)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The run whose list this is.'),
            'project_slug' => $schema->string()->description('Or the show, for its writers.'),
            'credits' => $schema->array()->description('The whole list: objects of {id?, artist_id, department, role?, character?, kind? (regular, alternate, understudy, standby, swing), covers?, credited_as?, starts_on?, ends_on?, note?}.')->required(),
        ];
    }
}
