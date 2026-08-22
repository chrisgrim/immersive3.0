<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * The "Get updates" toggle on a saved event's detail page — genuinely scoped
 * to THIS event/organizer, not the global Account Settings preference (see
 * FollowedOrganizerNewEventNotification::via() and SavedEventNewDatesNotification
 * ::via(), which read these same rows).
 *
 * Enabling also follows the organizer if not already followed, since the
 * organizer_followers row is what the notify_new_events override lives on —
 * without a row to set it on, the preference would silently do nothing.
 * Disabling never unfollows; it only turns the two notify flags off.
 */
class UpdateFavoriteNotifyAction
{
    public function handle(Event $event, int $userId, bool $enabled): void
    {
        $favorite = $event->favorites()->where('user_id', $userId)->firstOrFail();
        $favorite->update(['notify_new_dates' => $enabled]);

        if (! $event->organizer_id) {
            return;
        }

        if ($enabled) {
            // Not Organizer::follow() — that follows auth()->id(), which only
            // happens to match $userId when this runs inside the current
            // request. Same idempotent insertOrIgnore, scoped to the
            // explicit $userId this action was actually given.
            DB::table('organizer_followers')->insertOrIgnore([
                'organizer_id' => $event->organizer_id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('organizer_followers')
            ->where('organizer_id', $event->organizer_id)
            ->where('user_id', $userId)
            ->update(['notify_new_events' => $enabled]);
    }
}
