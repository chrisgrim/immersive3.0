<?php

namespace App\Http\Controllers\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Community;
use App\Models\Curated\CuratorInvitation;
use App\Models\Featured\Section;
use App\Models\Featured\Feature;
use App\Models\Curated\Post;
use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreCommunityRequest;
use App\Actions\Curated\CommunityActions;
use App\Http\Controllers\Controller;
use App\Services\NameChangeRequestService;
use App\Services\ImageHandler;
use Illuminate\Support\Facades\Log;

class CommunityController extends Controller
{   
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $communities = auth()->user()->communities()->with(['images'])->get();
        return view('curated.communities.index', compact('communities', 'user'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('curated.communities.create');
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
        return view('curated.communities.show', compact('community', 'shelves'));
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
     * @param  \App\Models\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function edit(Community $community)
    {
        $this->authorize('update', $community);
        
        return view('curated.communities.edit', [
            'community' => $community->load([
                'owner',
                'curators',
                'images',
                'nameChangeRequests' => function($query) {
                    $query->where('status', 'pending')->latest();
                }
            ])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function listings(Community $community)
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

        return view('curated.communities.listings', [
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
            // Handle curator updates
            if ($request->has('curator_ids')) {
                // Handle ownership transfer first if requested
                if ($request->has('new_owner_id')) {
                    $communityActions->updateOwner(
                        new Request(['id' => $request->new_owner_id]), 
                        $community
                    );
                    
                    // Make sure the old owner is included in curator_ids if not already
                    if (!in_array($community->user_id, $request->curator_ids)) {
                        $request->merge([
                            'curator_ids' => array_merge($request->curator_ids, [$community->user_id])
                        ]);
                    }
                }

                // Remove all curators not in the new list
                $currentCuratorIds = $community->curators->pluck('id')->toArray();
                $newCuratorIds = $request->curator_ids;
                
                $curatorsToRemove = array_diff($currentCuratorIds, $newCuratorIds);
                foreach ($curatorsToRemove as $curatorId) {
                    $communityActions->removeCurator(
                        new Request(['id' => $curatorId]), 
                        $community
                    );
                }

                // Add any new curators
                $curatorsToAdd = array_diff($newCuratorIds, $currentCuratorIds);
                foreach ($curatorsToAdd as $curatorId) {
                    $curator = User::findOrFail($curatorId);
                    $communityActions->addCurator(
                        new Request(['email' => $curator->email]), 
                        $community, 
                        $curator
                    );
                }

                $community = $community->fresh()->load('curators', 'owner', 'images');
                return $community;
            }

            // Handle regular updates
            $data = $request->validated();
            
            // Only remove name from update if status is 'p' and name is different
            if ($community->status === 'p' && isset($data['name']) && $data['name'] !== $community->name) {
                // Create a name change request instead of direct update
                $nameChangeService = new NameChangeRequestService();
                $result = $nameChangeService->handleNameChange(
                    $community,
                    $data['name'],
                    'User requested name change'
                );
                unset($data['name']);
            }
            
            // Handle image updates
            if ($request->hasFile('image')) {
                // Delete existing images if there are any
                if ($community->images()->exists()) {
                    foreach ($community->images as $image) {
                        ImageHandler::deleteImage($image);
                    }
                }
                ImageHandler::saveImage($request->file('image'), $community, 1200, 675, 'community-images');
            } elseif ($request->has('remove_image')) {
                if ($community->images()->exists()) {
                    foreach ($community->images as $image) {
                        ImageHandler::deleteImage($image);
                    }
                }
            }
            
            // Remove image-related keys from data array
            unset($data['image']);
            unset($data['remove_image']);
            
            // Update other fields if any
            if (!empty($data)) {
                $community->update($data);
            }
            
            // Refresh community with relationships
            $community = $community->fresh()->load('curators', 'owner', 'images', 'nameChangeRequests');
            
            return response()->json([
                'message' => 'Community updated successfully',
                'community' => $community
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update community: ' . $e->getMessage());
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

    /**
     * Invite a curator to the community
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function inviteCurator(Request $request, Community $community, CommunityActions $communityActions)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        return $communityActions->inviteCurator($request, $community);
    }

    /**
     * Accept a curator invitation
     *
     * @param  string  $token
     * @return \Illuminate\Http\Response
     */
    public function acceptInvitation($token)
    {
        $invitation = CuratorInvitation::where('token', $token)->first();
        
        if (!$invitation) {
            abort(404, 'Invalid invitation token');
        }
        
        if ($invitation->accepted_at) {
            return redirect("/communities/{$invitation->community->slug}/edit")
                ->with('info', 'This invitation has already been accepted.');
        }
        
        if ($invitation->expires_at < now()) {
            abort(404, 'This invitation has expired. Please request a new invitation.');
        }

        if (!auth()->check()) {
            session(['pending_curator_invitation' => $token]);
            return redirect()->route('login')
                ->withErrors(['email' => 'Please log in with ' . $invitation->email . ' to accept the curator invitation.']);
        }

        // Verify the logged-in user's email matches the invitation
        if (auth()->user()->email !== $invitation->email) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Please log in with ' . $invitation->email . ' to accept the curator invitation.']);
        }

        // Add user as curator
        $invitation->community->curators()->attach(auth()->id());
        
        // Mark invitation as accepted
        $invitation->update([
            'accepted_at' => now()
        ]);

        return redirect("/communities/{$invitation->community->slug}/listings")
            ->with('success', 'You are now a curator of this community.');
    }

    /**
     * Update curator-related settings for the community
     */
    public function updateCurators(Request $request, Community $community, CommunityActions $communityActions)
    {
        $this->authorize('manageCurators', $community);
        
        if ($request->has('curator_ids')) {
            // Handle ownership transfer first if requested
            if ($request->has('new_owner_id')) {
                $this->updateOwner(
                    new Request(['id' => $request->new_owner_id]), 
                    $community,
                    $communityActions
                );
            }

            // Update curators
            $community->curators()->sync($request->curator_ids);
        }

        return $community->fresh()->load('curators', 'owner');
    }

    /**
     * Remove self from community curators
     */
    public function removeSelf(Community $community)
    {
        $this->authorize('removeSelf', $community);
        
        $community->curators()->detach(auth()->id());
        
        return $community->fresh()->load('curators', 'owner');
    }

    /**
     * Resubmit the community for review
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function submit(Request $request, Community $community)
    {
        $this->authorize('update', $community);

        // Update the status to 'r' (review)
        $community->update(['status' => 'r']);

        return $community->fresh();
    }

    public function requestNameChange(Request $request, Community $community)
    {
        try {
            $request->validate([
                'requested_name' => 'required|string|max:255',
                'current_name' => 'required|string'
            ]);

            $nameChangeService = new NameChangeRequestService();
            $result = $nameChangeService->handleNameChange(
                $community,
                $request->requested_name,
                'User requested name change'
            );

            // Refresh community with relationships
            $community = Community::with(['curators', 'owner', 'images', 'nameChangeRequests' => function($query) {
                $query->where('status', 'pending');
            }])->find($community->id);

            return response()->json([
                'message' => $result['message'] ?? 'Name change request submitted successfully',
                'community' => $community
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to submit name change request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to submit name change request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
