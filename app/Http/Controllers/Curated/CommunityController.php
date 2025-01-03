<?php

namespace App\Http\Controllers\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Community;
use App\Models\Featured\Section;
use App\Models\Featured\Feature;
use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreCommunityRequest;
use App\Actions\Curated\CommunityActions;
use App\Http\Controllers\Controller;

class CommunityController extends Controller
{

    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except('show');
    }
    
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $communities = auth()->user()->communities;
        return view('Curated.Communities.index', compact('communities', 'user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('Curated.Communities.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCommunityRequest $request, CommunityActions $communityActions)
    {
        return $communityActions->create($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function show(Community $community)
    {
        $shelves = $community->publishedShelves()->paginate(4)->through(function ($shelf, $key){
            return $shelf->setRelation('published_posts', $shelf->publishedPosts()->with('limitedCards')->paginate(8));
        });
        
        $community->load('curators', 'images');
        return view('Curated.Communities.show', compact('community', 'shelves'));
    }

    /**
     * Paginate the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function paginate(Request $request, Community $community)
    {
        $shelfOffset = $request->query('shelf_offset', 0);
        
        $shelves = $community->publishedShelves()
            ->orderBy('order', 'DESC')
            ->offset($shelfOffset)
            ->limit(4)
            ->with(['published_posts' => function($query) {
                $query->with('limitedCards')
                      ->orderBy('order', 'ASC')
                      ->limit(8);
            }])
            ->get();

        return $shelves;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function edit(Community $community)
    {
        $this->authorize('update', $community);
        
        $user = auth()->user();
        $shelves = $community->shelves()
            ->orderBy('status', 'DESC')
            ->orderBy('order', 'DESC')
            ->get()
            ->map(function($shelf) {
                // Load posts separately for each shelf with pagination
                $shelf->posts = $shelf->posts()
                    ->with('limitedCards')
                    ->orderBy('order', 'ASC')
                    ->paginate(8);
                return $shelf;
            });

        return view('Curated.Communities.edit', [
            'community' => $community->load('owner', 'curators', 'images'),
            'shelves' => $shelves,
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function update(StoreCommunityRequest $request, Community $community, CommunityActions $communityActions)
    {
        try {
            return $communityActions->update($request, $community);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Community $community
     * @return \Illuminate\Http\Response
     */
    public function destroy(Community $community, CommunityActions $communityActions)
    {
        return $communityActions->destroy($community);
    }

    /**
     * Returns user back to community index with a submitted popup
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function submitted()
    {
        return redirect('/communities')->with('submitted', 'Your community has been submitted for review');
    }

    /**
     * Adds a Curator to the Community
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function addCurator(Request $request, Community $community,  CommunityActions $communityActions)
    {
        $curator =  User::where('email', '=', $request->email)->first();
        if (!$curator) { throw ValidationException::withMessages(['user' => 'No User exists with that email']);}
        return $communityActions->addCurator($request, $community, $curator);
    }

    /**
     * Removes Curator from Community
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function removeCurator(Request $request, Community $community, CommunityActions $communityActions)
    {
        return $communityActions->removeCurator($request, $community);
    }

    /**
     * Makes a new owner of the community
     *
     * @param  \App\Event  $event
     * @return \Illuminate\Http\Response
     */
    public function updateOwner(Request $request, Community $community, CommunityActions $communityActions)
    {
        return $communityActions->updateOwner($request, $community);
    }


}
