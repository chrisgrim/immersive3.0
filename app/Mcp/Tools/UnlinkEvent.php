<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\UnlinkEvent as Unlink;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Take a run off its show. It becomes a plain listing at its own address and links to the run follow it; the show keeps its own address.')]
class UnlinkEvent extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        if (! $request->user()->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate(['event_slug' => 'required|string']);
        $event = Event::where('slug', $validated['event_slug'])->first();

        if ($event === null) {
            return Response::error('No such listing.');
        }

        return $this->attempt(fn () => ['event' => app(Unlink::class)->handle($event, $request->user())->only(['id', 'slug', 'public_url'])]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['event_slug' => $schema->string()->description('The run to take off its show.')->required()];
    }
}
