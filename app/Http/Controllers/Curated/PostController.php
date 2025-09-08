<?php

namespace App\Http\Controllers\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Post;
use App\Models\Curated\Community;
use App\Models\Curated\Card;
use App\Models\ImageFile;
use Illuminate\Support\Str;
use App\Actions\Curated\PostActions;
use App\Models\Featured\Section;
use App\Models\Featured\Feature;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;

class PostController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Community $community)
    {
        return view('curated.posts.create', [
            'community' => $community,
            'shelves' => $community->shelves()->where('status', '!=', 'a')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Community $community, PostActions $postActions)
    {
        return $postActions->create($request, $community);
    }
    
    /**
     * Display the specified resource.
     *
     * @param  \App\Curated\post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Community $community, Post $post)
    {
        // Get authenticated user or null for guests
        $user = auth()->user();
        $isCurator = $user ? $user->can('curator', $community) : false;
        
        // Check if post is hidden and user is not a curator
        if ($post->is_hidden && !$isCurator) {
            abort(404);
        }
        
        $post->load([
            'cards.images',
            'cards.event',
            'user',
            'images',
            'featuredEventImage.images'
        ]);
        
        return view('curated.posts.show', [
            'post' => $post,
            'curator' => $isCurator,
            'community' => $community,
            'user' => $user,
        ]);
    }

    /**
     * Edit the specified resource.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(Community $community, Post $post)
    {
        $post->load([
            'cards', 
            'user', 
            'shelf', 
            'images', 
            'community.shelves' // Nested eager loading for community and its shelves
        ]);
        
        return view('curated.posts.edit', [
            'post' => $post,
            'community' => $community,
            'user' => auth()->user(),
            'curator' => auth()->user()->can('curator', $community),
            'shelves' => $community->shelves
        ]);
    }

    /**
     * Pagin ate the specified resource.
     *
     * @param  \App\Curated\post  $post
     * @return \Illuminate\Http\Response
     */
    public function paginate(Community $community)
    {
        return $community->posts()->latest()->paginate(10);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\post $post
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Community $community, Post $post, PostActions $postActions)
    {
        if ($request->has('deleteImage')) {
            $request->merge(['deleteImage' => true]);
        }
        return $postActions->update($request, $post);
    }

    /**
     * Order the specified resource.
     *
     * @param  \App\Curated\post  $post
     * @return \Illuminate\Http\Response
     */
    public function order(Request $request, Community $community, PostActions $postActions)
    {
        return $postActions->reorder($request);
    }

    /**
     * Toggle the hidden status of the specified post.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function toggleHidden(Community $community, Post $post)
    {
        $post->update(['is_hidden' => !$post->is_hidden]);
        
        return response()->json([
            'success' => true,
            'is_hidden' => $post->is_hidden,
            'message' => $post->is_hidden ? 'Post hidden successfully' : 'Post shown successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Community $community, Post $post, PostActions $postActions)
    {
        return $postActions->destroy($post);
    }
}
