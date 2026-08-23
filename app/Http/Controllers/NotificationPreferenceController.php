<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\ClearAllNotificationsAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceController extends Controller
{
    /**
     * Account Settings' Notifications tab. Counts, not the raw
     * saved-events/followed-organizers totals (that's ProfileExtrasController
     * ::stats()) — specifically how many currently have notifications ON
     * (no per-item override, or an explicit true), i.e. what "Clear all
     * notifications" would actually change. Showing the raw totals here read
     * as broken: clicking the button doesn't unsave or unfollow anything, so
     * those numbers never move, and it looked like nothing happened.
     */
    public function counts(Request $request)
    {
        return response()->json($this->notifyingCounts($request->user()->id));
    }

    /**
     * See ClearAllNotificationsAction for what this actually does (a
     * one-time bulk write, not a persistent switch). Returns the same
     * notifying counts as above — after this runs they're both 0, which is
     * the visible confirmation that the button worked.
     */
    public function clearAll(Request $request, ClearAllNotificationsAction $action)
    {
        $userId = $request->user()->id;
        $action->handle($userId);

        return response()->json($this->notifyingCounts($userId));
    }

    private function notifyingCounts(int $userId): array
    {
        $savedEventsCount = DB::table('favorites')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('notify_new_dates')->orWhere('notify_new_dates', true);
            })
            ->count();

        $followedOrganizersCount = DB::table('organizer_followers')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('notify_new_events')->orWhere('notify_new_events', true);
            })
            ->count();

        return [
            'saved_events_count' => $savedEventsCount,
            'followed_organizers_count' => $followedOrganizersCount,
        ];
    }
}
