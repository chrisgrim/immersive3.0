<?php

namespace App\Mcp\Tools;

use App\Models\Event;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the authenticated user and the organizers (teams) they belong to. Call this first: event drafts are created under an organizer, and each organizer can hold at most 5 unpublished events at a time.')]
class Whoami extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        $organizers = $user->teams()->get()->map(function ($organizer) {
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
                'unpublished_events' => Event::countUnpublishedEvents($organizer->id),
                'unpublished_events_limit' => 5,
            ];
        });

        // Moderators and admins are exempt from the membership check in
        // create-event-draft (and from `host`, even with no teams at all), but
        // nothing else in this payload said so. A client that found an existing
        // organizer it could not "claim" had no way to learn it could simply
        // pass that organizer's id, so it stopped rather than creating the event.
        $hint = $user->isModerator()
            ? 'As a moderator/admin you are NOT limited to the organizers listed here: pass ANY existing organizer id to create-event-draft, even one you do not belong to. Prefer an existing organizer over creating a duplicate — search by name first and use its id.'
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
