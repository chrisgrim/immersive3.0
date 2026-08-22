<?php

namespace App\Http\Controllers;

use App\Models\Organizer;

class FollowController extends Controller
{
    /**
     * Follow the given organizer for the current user. Idempotent —
     * Organizer::follow() uses insertOrIgnore, so a repeat call (or two
     * racing tabs) never creates a duplicate row or throws. 404s for a
     * deactivated organizer — the list already excludes those, so a direct
     * follow would create a relationship that can never surface anywhere.
     */
    public function store(Organizer $organizer)
    {
        abort_unless($organizer->status === 'p', 404);

        $organizer->follow();

        return response()->json(['isFollowed' => true]);
    }

    /**
     * Unfollow the given organizer for the current user. Idempotent — calling
     * this when nothing exists to remove is a no-op, not an error. Unlike
     * store(), this is NOT gated on status === 'p' — an organizer a user
     * already follows can be deactivated afterward (rejected, drafted), and
     * unfollowing must still work or the row becomes permanently stuck.
     */
    public function destroy(Organizer $organizer)
    {
        $organizer->unfollow();

        return response()->json(['isFollowed' => false]);
    }
}
