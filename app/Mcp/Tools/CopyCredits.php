<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\CopyCredits as Copy;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Copy one run\'s credits onto another. It adds to the destination and skips anyone already credited the same way; it never replaces.')]
class CopyCredits extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate(['from_event_slug' => 'required|string', 'to_event_slug' => 'required|string']);

        $from = Event::where('slug', $validated['from_event_slug'])->first();
        $to = Event::where('slug', $validated['to_event_slug'])->first();

        if ($from === null || $to === null) {
            return Response::error('No such listing.');
        }

        return $this->attempt(fn () => ['added' => app(Copy::class)->handle($from, $to, $request->user())]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from_event_slug' => $schema->string()->description('Copy from this run.')->required(),
            'to_event_slug' => $schema->string()->description('Onto this one.')->required(),
        ];
    }
}
