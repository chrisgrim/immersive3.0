<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfileRequest;
use App\Models\User;
use App\Services\ImageHandler;

class ProfilesController extends Controller
{
    public function show(User $user)
    {
        $user->load('images');
        $user->makeHidden([
            'newsletter_type', 'type', 'hasMessages', 'hasCreatedOrganizers',
            'current_team_id', 'card_brand', 'card_last_four', 'email', 'stripe_id',
        ]);
        $user->image = $user->images->first();

        return view('auth.user-profile', compact('user'));
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
                $userData['notification_preferences'] = $request->input('notification_preferences');
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

    public function destroy(User $user)
    {
        $this->authorize('update', $user);
        $user->conversations()->detach();
        $user->delete();
    }
}
