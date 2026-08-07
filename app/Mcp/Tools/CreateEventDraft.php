<?php

namespace App\Mcp\Tools;

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Events\UpdateEventAction;
use App\Mcp\Tools\Concerns\BuildsSyntheticRequests;
use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create an empty event draft under an organizer — one of your own, or (for moderators/admins) any existing organizer, including ones you do not belong to. Returns the event slug used by update-event / get-event / submit-event-for-review. Each organizer can hold at most 10 unpublished events (drafts count; admins exempt). If the name matches an existing event you must confirm with the user and retry with acknowledge_duplicate=true.')]
class CreateEventDraft extends Tool
{
    use BuildsSyntheticRequests;
    use FormatsEvents;

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'organizer_id' => 'required|integer|exists:organizers,id',
            'name' => 'nullable|string|max:100|regex:/[\p{L}\p{N}]/u',
            'acknowledge_duplicate' => 'nullable|boolean',
        ]);

        $organizer = Organizer::findOrFail($validated['organizer_id']);

        // Web parity: the create route sits behind can:host. On top of that we
        // require membership of the *target* organizer (moderators exempt) —
        // the event would be unmanageable by this user otherwise.
        if (! $user->can('host', Event::class)) {
            return Response::error('You need to belong to an organizer (team) before you can create events.');
        }

        if (! $user->isModerator() && ! $user->belongsToOrganization($organizer)) {
            return Response::error("You are not a member of \"{$organizer->name}\". Call whoami to see your organizers.");
        }

        // Unpublished-events cap, admins exempt (same as the web flow).
        $unpublishedCount = Event::countUnpublishedEvents($organizer->id);
        if ($unpublishedCount >= Event::MAX_UNPUBLISHED_EVENTS && ! $user->isAdmin()) {
            return Response::error("\"{$organizer->name}\" already has {$unpublishedCount} unpublished events (limit ".Event::MAX_UNPUBLISHED_EVENTS.'). Submit or delete one first (see list-my-events).');
        }

        // Duplicate-name guard, same as the web flow.
        if (! empty($validated['name']) && empty($validated['acknowledge_duplicate'])) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($validated['name']);
            if ($duplicates) {
                return Response::json([
                    'error' => 'duplicate_name',
                    'message' => 'An event with a similar name already exists. This may confuse attendees or be rejected during review. Ask the user how to proceed; to create it anyway, call this tool again with acknowledge_duplicate=true.',
                    'duplicateEvents' => $duplicates,
                ]);
            }
        }

        $event = Event::newEvent($organizer->id);

        if (! empty($validated['name'])) {
            $event = app(UpdateEventAction::class)->handle(
                $event,
                ['name' => $validated['name']],
                $this->syntheticRequest(['name' => $validated['name']], $user)
            );
        }

        return Response::json([
            'message' => 'Draft created. Fill it in with update-event, check progress with get-event, then submit-event-for-review.',
            'event' => $this->eventSummary($event->load('organizer')),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer this event belongs to (see whoami).')->required(),
            'name' => $schema->string()->description('Optional event name (max 100 chars); can also be set later with update-event.'),
            'acknowledge_duplicate' => $schema->boolean()->description('Set true only after the user confirms creating despite similarly-named existing events.'),
        ];
    }
}
