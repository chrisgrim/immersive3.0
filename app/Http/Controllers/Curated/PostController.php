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

    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except('show');
    }

    /**
     * Show the form for editing a resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Community $community)
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Community $community)
    {
        return view('communities.posts.create', compact('community'));
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
        $post->load('cards', 'user');
        return view('communities.posts.show', compact('post','community'));
    }

    /**
     * Edit the specified resource.
     *
     * @param  \App\Curated\post  $post
     * @return \Illuminate\Http\Response
     */
    public function edit(Community $community, Post $post)
    {
        $post->load('cards', 'user', 'shelf');
        
        return view('Curated.Posts.edit', [
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
     * Remove the specified resource from storage.
     *
     * @param  \App\post $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post, PostActions $postActions)
    {
        return $postActions->destroy($post);
    }
}
