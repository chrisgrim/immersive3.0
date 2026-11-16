<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the authenticated user and the organizers (teams) they belong to. Call this first: event drafts are created under an organizer, and each organizer can hold at most 10 unfinished drafts at a time (EI staff exempt). Staff accounts belong to many organizers: the list is paged and searchable (organizer_query).')]
class Whoami extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'organizer_query' => 'nullable|string|max:100',
            'with_drafts_only' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_LIMIT,
            'offset' => 'nullable|integer|min:0',
        ]);
        $user = $request->user();

        // Staff accounts belong to hundreds of organizers: the list is
        // searchable and comes a page at a time (organizers with unfinished
        // drafts first), with the total so the client knows there is more.
        $teams = $user->teams()
            ->when($validated['organizer_query'] ?? null, fn ($query, $search) => $query->where('organizers.name', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->get();

        // One grouped query for every team's unpublished count instead of one
        // count query per organizer in the map() below (EI-LARAVEL-W).
        $unpublishedCounts = Event::countUnpublishedEventsForOrganizers($teams->pluck('id')->all());

        if ($validated['with_drafts_only'] ?? false) {
            $teams = $teams->filter(fn ($organizer) => $unpublishedCounts->get($organizer->id, 0) > 0);
        }
        $total = $teams->count();
        $offset = (int) ($validated['offset'] ?? 0);
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $teams = $teams->sortBy(fn ($organizer) => [-$unpublishedCounts->get($organizer->id, 0), mb_strtolower($organizer->name)])
            ->slice($offset, $limit)->values();

        $organizers = $teams->map(function ($organizer) use ($unpublishedCounts) {
            return [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'status' => $organizer->status,
                'status_label' => match ($organizer->status) {
                    'p' => 'published',
                    'r' => 'in review',
                    default => 'draft/inactive',
                },
                'your_role' => $organizer->membership->role ?? null,
                'unpublished_events' => $unpublishedCounts->get($organizer->id, 0),
                'unpublished_events_limit' => Event::MAX_UNPUBLISHED_EVENTS,
            ];
        });

        // Moderators and admins are exempt from the membership check in
        // create-event-draft (and from `host`, even with no teams at all), but
        // nothing else in this payload said so. A client that found an existing
        // organizer it could not "claim" had no way to learn it could simply
        // pass that organizer's id, so it stopped rather than creating the event.
        // The same blind spot then showed up on the read side: a client that
        // could only see this list concluded events under other organizers were
        // invisible to the API, when in fact every tool below reaches them.
        $moderatorAccount = $user->hasModeratorAccount();

        $hint = $user->tokenCan(User::READ_SCOPE)
            ? 'This is a read-only key (the /mcp/read door): list-all-events and get-event on published events, and nothing that changes anything.'
            : ($user->isModerator()
            ? 'As a moderator/admin you are NOT limited to the organizers listed here. Use list-all-events to search every event on the platform in any status, then get-event / update-event / attach-event-image / submit-event-for-review on any of them, and update-organizer on any organizer. Pass ANY existing organizer id to create-event-draft, even one you do not belong to — prefer an existing organizer over creating a duplicate.'
            : ($moderatorAccount
                ? 'This account is a moderator, but this connection carries the mcp:use scope only, so it acts on the user\'s own organizers like anyone else, except that it can still edit their events that are in review. For moderator actions the user must reconnect this assistant and tick "Include moderator powers" on the consent screen, or create an API key with moderator powers on the settings page.'
                : ($organizers->isEmpty()
                    ? 'This user has no organizer yet. Create one with create-organizer before creating events.'
                    : 'Pass an organizer id to create-event-draft to create an event under that organizer.')));

        return Response::json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Credential-aware: true only when the account is a moderator AND
                // this token carries mcp:moderate (User::credentialAllowsModeration).
                'is_moderator' => $user->isModerator(),
                'moderator_account' => $moderatorAccount,
                'email_verified' => $user->email_verified_at !== null,
            ],
            'organizers' => $organizers,
            'organizers_total' => $total,
            'organizers_shown' => ['offset' => $offset, 'limit' => $limit],
            'unpublished_events_note' => 'unpublished_events counts unfinished drafts (and rejected events); events waiting for review do not count, and EI staff are exempt from the limit.',
            'hint' => $hint.($total > $offset + $organizers->count() ? " Showing {$organizers->count()} of {$total} organizers: pass organizer_query to search by name, or offset for the next page." : ''),
        ]);
    }

    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_query' => $schema->string()->description('Optional: only organizers whose name contains this.'),
            'with_drafts_only' => $schema->boolean()->description('Optional: only organizers that have unfinished drafts.'),
            'limit' => $schema->integer()->description('Optional: how many organizers to return (default '.self::DEFAULT_LIMIT.', max '.self::MAX_LIMIT.').'),
            'offset' => $schema->integer()->description('Optional: skip this many (for the next page).'),
        ];
    }
}
