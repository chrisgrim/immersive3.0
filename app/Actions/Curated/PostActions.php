<?php

namespace App\Actions\Curated;

use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Services\ImageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostActions
{
    /**
     * Create a newly registered post.
     *
     * @return \App\Models\Curated\Post
     */
    public function create(Request $request, Community $community)
    {
        $post = $community->posts()->create([
            'blurb' => $request->blurb,
            'name' => $request->name,
            'slug' => Str::slug($request->name).'-'.$community->id,
            'user_id' => auth()->user()->id,
            'shelf_id' => $request->shelf_id,
        ]);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            ]);
            ImageHandler::saveImage($request->file('image'), $post, 1000, 563, 'post-images');
        }

        return $post;
    }

    /**
     * Updates an existing post
     *
     * @return \App\Models\Curated\Post
     */
    public function update(Request $request, Post $post)
    {
        // Allow-list update fields. Excluding `community_id`, `user_id`, `status`,
        // `slug`, `largeImagePath`, `thumbImagePath`, `is_hidden`, `section_id` —
        // these are either set by the server, mutated through dedicated endpoints,
        // or would otherwise let a curator reparent / self-publish posts.
        $data = $request->only(['name', 'blurb', 'shelf_id', 'order', 'type', 'event_id', 'image_type']);

        if (isset($data['shelf_id']) && ($data['shelf_id'] === null || $data['shelf_id'] === 'null' || $data['shelf_id'] === '')) {
            $data['shelf_id'] = null;
        }

        // Store old slug for image path updates
        $oldSlug = $post->slug;

        // Update basic data
        $post->update($data);

        // Update slug if name changed
        if ($request->has('name')) {
            $newSlug = Str::slug($request->name).'-'.$post->community->id;
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
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            ]);
            $post->update(['event_id' => null]);
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
                'event_id' => null,
                'largeImagePath' => null,
                'thumbImagePath' => null,
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
     * @return \App\Models\Curated\Post
     */
    public function destroy(Post $post)
    {
        $post->delete();
    }

    /**
     * Re orders the posts
     *
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
