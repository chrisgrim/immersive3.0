<?php

namespace App\Mcp\Tools;

use App\Actions\Projects\LinkEventToProject as Link;
use App\Mcp\Tools\Concerns\AnswersShowActions;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Moderators only. Make a published or embargoed listing a run of a show: give project_slug, or source_event_slug to make the show from a standalone listing (the second run makes the project). Confirm who made the show with the user. It moves public addresses and tells the organizers involved.')]
class LinkEventToProject extends Tool
{
    use AnswersShowActions;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isModerator()) {
            return Response::error(self::MODERATORS_ONLY);
        }

        $validated = $request->validate([
            'event_slug' => 'required|string',
            'project_slug' => 'required_without:source_event_slug|nullable|string',
            'source_event_slug' => 'nullable|string',
            'maker_organizer_id' => 'nullable|integer|exists:organizers,id',
            'slug' => ['nullable', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
        ]);

        $event = Event::where('slug', $validated['event_slug'])->first();
        $target = ! empty($validated['project_slug'])
            ? Project::where('slug', $validated['project_slug'])->first()
            : Event::where('slug', $validated['source_event_slug'])->first();

        if ($event === null || $target === null) {
            return Response::error('No such listing or show.');
        }

        return $this->attempt(function () use ($event, $target, $user, $validated) {
            $linked = app(Link::class)->decide(
                $event,
                $event->pendingLinkRequest(),
                $target,
                $user,
                ! empty($validated['maker_organizer_id']) ? Organizer::find($validated['maker_organizer_id']) : null,
                $validated['slug'] ?? null,
            );

            return ['event' => $linked->only(['id', 'slug', 'project_id', 'instance_slug', 'public_url'])];
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The listing to link.')->required(),
            'project_slug' => $schema->string()->description('The show to join.'),
            'source_event_slug' => $schema->string()->description('Or the standalone listing that becomes the show\'s first run.'),
            'maker_organizer_id' => $schema->integer()->description('Who made the show, if not the first listing\'s organizer.'),
            'slug' => $schema->string()->description('The show\'s address, if not the first listing\'s.'),
        ];
    }
}
