<?php

namespace App\Actions\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Models\Curated\Community;
use App\Services\ImageHandler;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PostActions
{
    /**
     * Create a newly registered post.
     *
     * @param  Request  $request
     * @param  Community  $community
     * @return \App\Models\Curated\Post
     */
    public function create(Request $request, Community $community)
    {
        $post = $community->posts()->create([
            'blurb' => $request->blurb,
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . $community->id,
            'user_id' => auth()->user()->id,
            'shelf_id' => $request->shelf_id,
        ]);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
            ]);
            ImageHandler::saveImage($request->file('image'), $post, 1000, 563, 'post-images');
        }

        return $post;
    }

    /**
     * Updates an existing post
     *
     * @param  Request  $request
     * @param  Post  $post
     * @return \App\Models\Curated\Post
     */
    public function update(Request $request, Post $post)
    {
        $data = $request->except(['image', 'deleteImage']);
        if (isset($data['shelf_id']) && ($data['shelf_id'] === null || $data['shelf_id'] === 'null' || $data['shelf_id'] === '')) {
            $data['shelf_id'] = null;
        }

        // Store old slug for image path updates
        $oldSlug = $post->slug;
        
        // Update basic data
        $post->update($data);
        
        // Update slug if name changed
        if ($request->has('name')) {
            $newSlug = Str::slug($request->name) . '-' . $post->community->id;
            if ($oldSlug !== $newSlug) {
                $post->update(['slug' => $newSlug]);
                
                // Use ImageHandler to move images if they exist
                if ($post->images()->exists()) {
                    ImageHandler::moveImagesForNewSlug($post, $oldSlug, $newSlug, 'post-images');
                }
            }
        }

        // Handle image upload/deletion as before
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
            ]);
            $post->update(['event_id' => NULL]);
            if ($post->images()->exists()) {
                foreach ($post->images as $image) {
                    ImageHandler::deleteImage($image);
                }
            }
            ImageHandler::saveImage($request->file('image'), $post, 1000, 563, 'post-images');
            $post->touch();
        }

        if ($request->deleteImage) {
            if ($post->images()->exists()) {
                foreach ($post->images as $image) {
                    ImageHandler::deleteImage($image);
                }
            }
            $post->update([
                'event_id' => NULL,
                'largeImagePath' => NULL,
                'thumbImagePath' => NULL
            ]);
            $post->touch();
        }
        
        // Return updated post with flag indicating slug change
        $response = $post->load('cards', 'user', 'featuredEventImage', 'images');
        $response->slug_changed = $oldSlug !== $post->slug;
        
        return $response;
    }

    /**
     * Destroys an existing post
     *
     * @param  Post  $post
     * @return \App\Models\Curated\Post
     */
    public function destroy(Post $post)
    {
        $post->delete();
    }

    /**
     * Re orders the posts
     *
     * @param  Request  $request
     * @return void
     */
    public function reorder(Request $request)
    {
        foreach ($request->all() as $list) {
            Post::find($list['id'])->update([
                'order' => $list['order'],
            ]);
        }
    }
}
