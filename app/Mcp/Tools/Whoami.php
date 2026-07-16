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

        return Response::json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_moderator' => $user->isModerator(),
                'email_verified' => $user->email_verified_at !== null,
            ],
            'organizers' => $organizers,
            'hint' => $organizers->isEmpty()
                ? 'This user has no organizer yet. Create one with create-organizer before creating events.'
                : 'Pass an organizer id to create-event-draft to create an event under that organizer.',
        ]);
    }
}
