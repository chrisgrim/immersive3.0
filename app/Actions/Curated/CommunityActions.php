<?php

namespace App\Actions\Curated;

use Illuminate\Http\Request;
use App\Models\ImageFile;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Models\Curated\Community;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\ImageHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CuratorInvitation;

class CommunityActions
{

    /**
     * Create a newly registered community.
     *
     * @param  array  $input
     * @return \App\Models\Curated\Community
     */
    public function create(Request $request)
    {
        $community = Community::create([
            'blurb' => $request->blurb,
            'description' => $request->description ? $request->description : null,
            'name' => $request->name,
            'user_id' => auth()->id(),
            'slug' => Str::slug($request->name),
        ]);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
            ]);
            ImageHandler::saveImage($request->file('image'), $community, 800, 500, 'community-images', 0);
        }

        $community->shelves()->create(['user_id' => auth()->id()]);
        $community->shelves()->create(['user_id' => auth()->id(), 'name' => 'Archived', 'status' => 'a']);
        $community->curators()->attach(auth()->user()->id);

        return $community;
    }

    /**
     * Updates an existing community
     *
     * @param  array  $input
     * @return \App\Models\Curated\Community
     */
    public function update(Request $request, Community $community)
    {
        $data = $request->except(['image', 'deleteImage']);
        $community->update($data);

        if ($request->hasFile('image')) {
            try {
                $request->validate([
                    'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
                ]);
                if ($community->images()->exists()) {
                    foreach ($community->images as $image) {
                        if ($image->large_image_path || $image->thumb_image_path) {
                            ImageHandler::deleteImage($image);
                            $image->delete();
                        }
                    }
                    $community->unsetRelation('images');
                }

                ImageHandler::saveImage($request->file('image'), $community, 800, 500, 'community-images');
                $community->touch();
            } catch (\Exception $e) {
                throw $e;
            }
        }

        if ($request->deleteImage) {
            if ($community->images()->exists()) {
                foreach ($community->images as $image) {
                    if ($image->large_image_path || $image->thumb_image_path) {
                        ImageHandler::deleteImage($image);
                        $image->delete();
                    }
                }
                $community->unsetRelation('images');
            }
            
            $community->update([
                'largeImagePath' => NULL,
                'thumbImagePath' => NULL
            ]);
            $community->touch();
        }

        return $community->fresh()->load('curators', 'images');
    }

    /**
     * Destroys an existing community
     *
     * @param  Community  $community
     * @return \App\Models\Curated\Community
     */
    public function destroy(Community $community)
    {
        if ($community->images()->exists()) {
            foreach ($community->images as $image) {
                ImageHandler::deleteImage($image);
            }
        }
        
        $community->delete();
        return auth()->user()->communities;
    }

    /**
     * Adds a curator to a community
     *
     * @param  array  $input
     * @return \App\Models\Curated\Community
     */
    public function addCurator(Request $request, Community $community, $curator)
    {
        $community->curators()->attach($curator->id);
        return $community->fresh()->load('curators', 'owner');
    }

    /**
     * Removes the curator of a community
     *
     * @param  array  $input
     * @return \App\Models\Curated\Community
     */
    public function removeCurator(Request $request, Community $community)
    {
        $community->curators()->detach($request->id);
        return $community->fresh()->load('curators', 'owner');
    }

    /**
     * Updates the owner of a community
     *
     * @param  array  $input
     * @return \App\Models\Curated\Community
     */
    public function updateOwner(Request $request, Community $community)
    {
        $community->update([ 'user_id' => $request->id ]);
        return $community->fresh()->load('curators', 'owner');
    }

    /**
     * Invite a curator to the community
     *
     * @param  Request  $request
     * @param  Community  $community
     * @return \Illuminate\Http\Response
     */
    public function inviteCurator(Request $request, Community $community)
    {
        // Check if user exists in EI
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'This email is not registered with EI. Users must have an EI account to be a curator.'
            ]);
        }

        // Check if user is already a curator
        $existingCurator = $community->curators()
            ->where('id', $user->id)
            ->exists();

        if ($existingCurator) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a curator of this community.'
            ]);
        }

        // Check if there's already a pending invitation
        $existingInvitation = $community->curatorInvitations()
            ->where('email', $request->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($existingInvitation) {
            throw ValidationException::withMessages([
                'email' => 'An invitation has already been sent to this email address.'
            ]);
        }

        // Create invitation record
        $invitation = $community->curatorInvitations()->create([
            'email' => $request->email,
            'token' => Str::random(32),
            'expires_at' => now()->addDays(7)
        ]);

        // Send invitation email
        Mail::to($request->email)->send(new CuratorInvitation($community, $invitation));

        return response()->json([
            'message' => 'Invitation sent successfully'
        ]);
    }

}
