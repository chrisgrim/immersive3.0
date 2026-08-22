<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationFeedController extends Controller
{
    /**
     * The current user's in-app notification feed — every notification a
     * trigger created for them lands here regardless of their mail
     * preference (see SavedEventNewDatesNotification::via() and
     * FollowedOrganizerNewEventNotification::via()), so this always
     * reflects the full set, not just the ones they'd also get emailed.
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20);

        return response()->json(['notifications' => $notifications]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markRead(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }
}
