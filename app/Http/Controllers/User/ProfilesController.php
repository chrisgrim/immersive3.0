<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfileRequest;
use App\Models\User;
use App\Services\ImageHandler;
use App\Services\UserDeletionService;

class ProfilesController extends Controller
{
    /**
     * $tab/$itemKey are unused here (same as HubController::index() and
     * AccountSettingsController::index()) — they only exist so the route's
     * optional /{tab?}/{itemKey?} segments bind; the Vue shell reads them
     * from window.location.pathname client-side so a hard refresh or a cold
     * visit to e.g. /users/1/events/some-slug still lands correctly.
     */
    public function show(User $user, $tab = null, $itemKey = null)
    {
        $user->load('images');
        $user->makeHidden([
            'newsletter_type', 'type', 'hasMessages', 'hasCreatedOrganizers',
            'current_team_id', 'card_brand', 'card_last_four', 'email', 'stripe_id',
            // notification_preferences/privacy_preferences are this user's own
            // settings (mail opt-ins, profile-visibility toggles) — Account
            // Settings' Privacy/Notifications tabs fetch them from their own
            // dedicated endpoints, nothing on the Profile page reads them off
            // this blob, so there's no reason for them to ride along here even
            // for the owner. isAdmin/isModerator/isCurator/isUser are $appends
            // (included in every serialization by default, unlike `type`
            // above, which only hides the raw role code, not these derived
            // booleans) — same leak class as the fields above, just via a
            // different attribute.
            'notification_preferences', 'privacy_preferences',
            'isAdmin', 'isModerator', 'isCurator', 'isUser',
        ]);
        $user->image = $user->images->first();

        $isOwner = auth()->check() && auth()->id() === $user->id;

        return view('auth.user-profile', compact('user', 'isOwner'));
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $user->load('images');
        // Make these fields visible for the edit view
        $user->makeVisible(['newsletter_type', 'silence', 'notification_preferences']);

        return view('auth.user-edit', [
            'user' => $user,
            'owner' => $user, // Add owner data that includes newsletter settings
            'image' => $user->images->first(),
        ]);
    }

    public function update(StoreProfileRequest $request, User $user)
    {
        try {
            if ($request->hasFile('image')) {
                // Delete existing images
                foreach ($user->images as $image) {
                    try {
                        ImageHandler::deleteImage($image);
                    } catch (\Exception $e) {
                        // Continue with the upload even if deletion fails
                    }
                }

                // Save new image with correct type parameter
                ImageHandler::saveImage(
                    $request->file('image'),
                    $user,
                    600,  // width
                    600,  // height
                    'user-images'  // type parameter to match expected path structure
                );

                // If this is just an image upload, return early
                if (count($request->allFiles()) === 1 && count($request->all()) === 1) {
                    return $user->fresh(['images'])
                        ->makeVisible(['newsletter_type', 'silence', 'notification_preferences']);
                }
            }

            // Handle other profile updates. Only persist the fields actually present so a
            // partial edit (e.g. name only) does not clobber the user's saved
            // newsletter_type / silence preferences.
            $userData = $request->only('name', 'email', 'newsletter_type', 'silence');

            // Admin notification opt-outs are personal to each admin — only persist them when
            // an admin is editing their OWN account (not a moderator editing someone else, and
            // not a non-admin pre-seeding opt-outs before a future promotion).
            if ($request->has('notification_preferences') && $request->user()->is($user) && $user->isAdmin()) {
                // Merge, don't replace — this column also holds keys this endpoint never
                // sees (e.g. the Hub's saved_event_new_dates/followed_organizer_new_event),
                // and a plain assignment here would silently wipe them on every profile save.
                $userData['notification_preferences'] = array_merge(
                    $user->notification_preferences ?? [],
                    $request->input('notification_preferences')
                );
            }

            if ($request->filled('email') && $request->email !== $user->email) {
                $userData['email_verified_at'] = null;
                $user->update($userData);
                $user->sendEmailVerificationNotification();
            } else {
                $user->update($userData);
            }

            $result = $user->fresh(['images'])->makeVisible(['newsletter_type', 'silence', 'notification_preferences']);

            return $result;

        } catch (\Exception $e) {
            \Log::error('Update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to update profile. '.$e->getMessage()], 422);
        }
    }

    /**
     * Dormant — its route is intentionally commented out in web.php pending
     * a real "delete my account" UI (see AccountSettings\AccountDeletionController,
     * which is that UI's actual endpoint). Kept safe via UserDeletionService
     * regardless, so re-enabling this route later can't reintroduce the
     * unguarded-deletion bug that method used to have (no check at all for
     * Organizer/Event/Community ownership before a raw delete()).
     */
    public function destroy(User $user, UserDeletionService $deletions)
    {
        $this->authorize('update', $user);

        if ($reason = $deletions->blockingReason($user)) {
            return response()->json(['message' => $reason], 422);
        }

        $deletions->delete($user);
    }
}
