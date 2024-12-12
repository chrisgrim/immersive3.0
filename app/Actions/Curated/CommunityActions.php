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
use Illuminate\Foundation\Validation\ValidationException;
use App\Services\ImageHandler;
use Illuminate\Support\Facades\Log;

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
            ImageHandler::saveImage($request->file('image'), $community, 800, 500, 'community');
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

}
