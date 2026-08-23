<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileExtrasController extends Controller
{
    /**
     * Owner-only stats for the About-me card — the profile page itself is
     * public, but these numbers (and the followed-organizers carousel below)
     * are only ever fetched for the logged-in viewer looking at their OWN
     * profile, mirroring how Airbnb's Wishlists/Trips never appear on
     * someone else's public profile. "Years on EI" is public and computed
     * client-side from the already-visible created_at, so it isn't here.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'saved_events_count' => $user->favouritedEvents()->count(),
            'followed_organizers_count' => $user->followedOrganizers()->where('status', 'p')->count(),
        ]);
    }

    public function followedOrganizers(Request $request)
    {
        return response()->json(['organizers' => $this->followedOrganizersDto($request->user())]);
    }

    public function updateBio(Request $request)
    {
        $validated = $request->validate([
            'blurb' => 'nullable|string|max:500',
        ]);

        $request->user()->update($validated);

        return response()->json($validated);
    }

    /**
     * The other side of the owner-only endpoints above — what a viewer who
     * is NOT the profile owner is allowed to see, gated by that user's own
     * Privacy toggles (see User::showsPublicly()). Everything defaults to
     * public, so a user who's never touched their Privacy settings shows
     * both by default — they have to explicitly opt out per field to hide
     * it from other viewers.
     */
    public function publicExtras(User $user)
    {
        return response()->json([
            'followed_organizers' => $user->showsPublicly('followed_organizers')
                ? $this->followedOrganizersDto($user)
                : null,
            'saved_events_count' => $user->showsPublicly('saved_events_count')
                ? $user->favouritedEvents()->count()
                : null,
        ]);
    }

    private function followedOrganizersDto(User $user)
    {
        return $user->followedOrganizers()
            // A followed organizer can be deactivated (rejected, drafted)
            // after the follow was created — it stays unfollowable (see
            // FollowController::destroy) but shouldn't keep appearing here.
            ->where('status', 'p')
            ->orderBy('organizer_followers.created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($organizer) => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'thumbImagePath' => $organizer->thumbImagePath,
            ]);
    }
}
