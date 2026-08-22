<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * The two account-wide notification defaults — used whenever a specific
     * saved event/followed organizer has no per-item override (see
     * favorites.notify_new_dates / organizer_followers.notify_new_events,
     * and FavoriteController::updateNotify()). Both keys are opt-in (default
     * false), unlike the admin-alert keys elsewhere on this same JSON
     * column, which default true — see User::wantsNotification().
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'notification_preferences' => [
                'saved_event_new_dates' => $user->wantsNotification('saved_event_new_dates', false),
                'followed_organizer_new_event' => $user->wantsNotification('followed_organizer_new_event', false),
            ],
        ]);
    }

    /**
     * Both keys are required, not "sometimes" — the frontend always submits the
     * complete two-key state (with whichever one was just toggled), never a
     * lone key. Combined with the frontend disabling both switches during any
     * in-flight save, this closes the race between the two toggles in THIS
     * component. It does NOT close the read-modify-write race against
     * ProfilesController::update(), which writes this same column via a
     * separate, independent request (e.g. the admin Account Settings page
     * open in another tab) — a save from each within the same instant could
     * still lose one side's change. Fixing that fully would mean adding
     * row-level locking to both write paths, including retrofitting it into
     * ProfilesController::update()'s larger, more complex method (image
     * uploads, email-change branches). Accepted as a known, narrow risk for
     * now rather than restructuring that shared method for it.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'saved_event_new_dates' => 'required|boolean',
            'followed_organizer_new_event' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->update([
            'notification_preferences' => array_merge($user->notification_preferences ?? [], $validated),
        ]);

        return response()->json(['notification_preferences' => $validated]);
    }
}
