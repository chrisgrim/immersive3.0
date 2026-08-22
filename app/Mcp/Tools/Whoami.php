<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the authenticated user and the organizers (teams) they belong to. Call this first: event drafts are created under an organizer, and each organizer can hold at most 10 unpublished events at a time.')]
class Whoami extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        $teams = $user->teams()->get();

        // One grouped query for every team's unpublished count instead of one
        // count query per organizer in the map() below (EI-LARAVEL-W).
        $unpublishedCounts = Event::countUnpublishedEventsForOrganizers($teams->pluck('id')->all());

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
        $hint = $user->isModerator()
            ? 'As a moderator/admin you are NOT limited to the organizers listed here. Use list-all-events to search every event on the platform in any status, then get-event / update-event / attach-event-image / submit-event-for-review on any of them, and update-organizer on any organizer. Pass ANY existing organizer id to create-event-draft, even one you do not belong to — prefer an existing organizer over creating a duplicate.'
            : ($organizers->isEmpty()
                ? 'This user has no organizer yet. Create one with create-organizer before creating events.'
                : 'Pass an organizer id to create-event-draft to create an event under that organizer.');

        return Response::json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_moderator' => $user->isModerator(),
                'email_verified' => $user->email_verified_at !== null,
            ],
            'organizers' => $organizers,
            'hint' => $hint,
        ]);
    }
}
