<?php

namespace App\Actions\Notifications;

use Illuminate\Support\Facades\DB;

/**
 * Account Settings > Notifications' "Clear all notifications" button — a
 * one-time bulk action, not a persistent preference. Sets every existing
 * saved event's notify_new_dates and every existing followed organizer's
 * notify_new_events to false in one go, so a user who's saved/followed
 * dozens of things doesn't have to hunt down each one's own "Get updates"
 * toggle individually (see UpdateFavoriteNotifyAction, the per-item version
 * of this same flag). A favorite/follow made AFTER this still starts out
 * notifying by default (see the null-means-notify fallback in
 * FollowedOrganizerNewEventNotification::via() / SavedEventNewDatesNotification
 * ::via()) — this only touches what already existed at the moment it ran.
 */
class ClearAllNotificationsAction
{
    public function handle(int $userId): void
    {
        DB::table('favorites')->where('user_id', $userId)->update(['notify_new_dates' => false]);
        DB::table('organizer_followers')->where('user_id', $userId)->update(['notify_new_events' => false]);
    }
}
