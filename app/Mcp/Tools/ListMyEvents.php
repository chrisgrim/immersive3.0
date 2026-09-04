<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the events belonging to the organizers the authenticated user is a member of, including drafts. Use this to resume work on an existing draft instead of creating a duplicate. This is deliberately scoped to your own teams even for moderators and admins — to reach events under organizers you do not belong to, use list-all-events.')]
class ListMyEvents extends Tool
{
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'organizer_id' => 'nullable|integer|exists:organizers,id',
        ]);

        $user = $request->user();
        $teamIds = $user->teams()->pluck('organizers.id');

        if (isset($validated['organizer_id'])) {
            if (! $teamIds->contains($validated['organizer_id']) && ! $user->isModerator()) {
                return Response::error('You are not a member of that organizer.');
            }
            $teamIds = collect([$validated['organizer_id']]);
        }

        $events = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->whereIn('organizer_id', $teamIds)
            ->with('organizer:id,name')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return Response::json([
            'events' => $events->map(fn (Event $event) => $this->eventSummary($event) + [
                'updated_at' => $event->updated_at?->toIso8601String(),
                'archived' => (bool) $event->archived,
                // See GetEvent: a long-finished published event is read-only
                // to its organizers.
                'edit_locked' => $event->isEditLockedFor($request->user()),
            ]),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()
                ->description('Optional: only list events for this organizer.'),
        ];
    }
}
