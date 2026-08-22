<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Unfavorites the event for the given user, and — if that was their last
 * saved event from this organizer — also unfollows the organizer, so a
 * follow (and any per-organizer "Get updates" preference on it) doesn't
 * silently outlive every saved event that led to it. Keeps the follow if
 * any other saved event from the same organizer remains.
 */
class RemoveFavoriteAction
{
    public function handle(Event $event, int $userId): void
    {
        $event->favorites()->where('user_id', $userId)->get()->each->delete();

        if (! $event->organizer_id) {
            return;
        }

        $hasOtherFavoriteFromOrganizer = Event::where('organizer_id', $event->organizer_id)
            ->where('id', '!=', $event->id)
            ->whereHas('favorites', fn ($query) => $query->where('user_id', $userId))
            ->exists();

        if ($hasOtherFavoriteFromOrganizer) {
            return;
        }

        // Not Organizer::unfollow() — that detaches auth()->id(), which only
        // happens to match $userId when this runs inside the current request.
        DB::table('organizer_followers')
            ->where('organizer_id', $event->organizer_id)
            ->where('user_id', $userId)
            ->delete();
    }
}
