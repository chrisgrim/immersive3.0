<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\ImageHandler;
use App\Http\Requests\StoreProfileRequest;

class ProfilesController extends Controller
{
    public function show(User $user)
    {
        $user->load('images');
        $user->makeHidden([
            'newsletter_type', 'type', 'hasMessages', 'hasCreatedOrganizers', 
            'current_team_id', 'card_brand', 'card_last_four', 'email', 'stripe_id'
        ]);
        $user->image = $user->images->first();
        return view('auth.user-profile', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        
        $user->load('images');
        // Make these fields visible for the edit view
        $user->makeVisible(['newsletter_type', 'silence']);
        
        return view('auth.user-edit', [
            'user' => $user,
            'owner' => $user, // Add owner data that includes newsletter settings
            'image' => $user->images->first()
        ]);
    }

    public function update(StoreProfileRequest $request, User $user)
    {
        try {
            \Log::info('Update request received', [
                'all' => $request->all(),
                'newsletter_type' => $request->input('newsletter_type'),
                'silence' => $request->input('silence')
            ]);

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
                               ->makeVisible(['newsletter_type', 'silence']);
                }
            }

            // Handle other profile updates
            $userData = $request->only('name', 'email', 'newsletter_type', 'silence') + [
                'newsletter_type' => $request->input('newsletter_type', 'n'),
                'silence' => $request->input('silence', 'y')
            ];

            if ($request->filled('email') && $request->email !== $user->email) {
                $userData['email_verified_at'] = null;
                $user->update($userData);
                $user->sendEmailVerificationNotification();
            } else {
                $user->update($userData);
            }

            $result = $user->fresh(['images'])->makeVisible(['newsletter_type', 'silence']);
            return $result;

        } catch (\Exception $e) {
            \Log::error('Update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update profile. ' . $e->getMessage()], 422);
        }
    }

    public function destroy(User $user)
    {
        $this->authorize('update', $user);
        $user->conversations()->detach();
        $user->delete();
    }

}
