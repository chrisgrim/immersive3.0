<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Dock;
use App\Models\Curated\{Community, Shelf, Post}; // Group related models
use App\Actions\Admin\DockActions;
use Illuminate\Http\Request;

class AdminDocksController extends Controller
{
    protected $dockActions;

    public function __construct(DockActions $dockActions)
    {
        $this->dockActions = $dockActions;
    }

    /**
     * Display a listing of the docks.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $docks = Dock::with([
            'shelves.posts' => function($query) {
                $query->select('id', 'name', 'thumbImagePath', 'shelf_id', 'order', 'event_id')
                      ->with([
                          'featuredEventImage',
                          'images',
                          'limitedCards.event' => function($query) {
                              $query->select('id', 'thumbImagePath', 'largeImagePath');
                          }
                      ])
                      ->orderBy('order')
                      ->limit(4);
            },
            'posts' => function($query) {
                $query->select('posts.id', 'posts.name', 'posts.thumbImagePath', 'posts.shelf_id', 'posts.order', 'posts.event_id', 'posts.community_id')
                      ->with([
                          'community:id,name',
                          'shelf:id,name',
                          'featuredEventImage',
                          'images'
                      ])
                      ->orderBy('posts.order')
                      ->limit(4);
            },
            'cards' => function($query) {
                $query->select('cards.id', 'cards.name', 'cards.blurb', 'cards.type', 'cards.order', 'cards.post_id', 'cards.event_id', 'cards.button_text')
                      ->with([
                          'post:id,name,community_id',
                          'post.community:id,name',
                          'event:id,name,thumbImagePath,largeImagePath',
                          'images'
                      ])
                      ->orderBy('cards.order')
                      ->limit(4);
            }
        ])
        ->orderBy('order', 'ASC')
        ->get();
            
        return response()->json($docks);
    }

    /**
     * Store a new dock.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
            'type' => 'required|string|in:f,t,i,h,s,p',
            'location' => 'required|string|in:home,search,none',
            'order' => 'integer'
        ]);

        $dock = auth()->user()->docks()->create($validated);
        
        return $this->getOrderedDocks();
    }

    /**
     * Update the specified dock.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Admin\Dock  $dock
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Dock $dock)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
            'type' => 'required|string|in:f,t,i,h,s,p',
            'location' => 'required|string|in:home,search,none',
            'order' => 'integer'
        ]);

        $dock->update($validated);
        
        return $this->getOrderedDocks();
    }

    /**
     * Add a shelf to the specified dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @param  \App\Models\Curated\Shelf  $shelf
     * @return \Illuminate\Http\JsonResponse
     */
    public function addShelf(Dock $dock, Shelf $shelf)
    {
        $this->dockActions->addShelf($dock, $shelf);
        return $this->getOrderedDocks();
    }

    /**
     * Add a community to the specified dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @param  \App\Models\Curated\Community  $community
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCommunity(Dock $dock, Community $community)
    {
        $this->dockActions->addCommunity($dock, $community);
        return $this->getOrderedDocks();
    }



    /**
     * Remove the specified dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Dock $dock)
    {
        $this->dockActions->destroy($dock);
        return $this->getOrderedDocks();
    }

    /**
     * Get ordered docks with relationships.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getOrderedDocks()
    {
        return Dock::with([
            'shelves.posts' => function($query) {
                $query->select('id', 'name', 'thumbImagePath', 'shelf_id', 'order', 'event_id')
                      ->with([
                          'featuredEventImage',
                          'images',
                          'limitedCards.event' => function($query) {
                              $query->select('id', 'thumbImagePath', 'largeImagePath');
                          }
                      ])
                      ->orderBy('order')
                      ->limit(4);
            },
            'posts' => function($query) {
                $query->select('posts.id', 'posts.name', 'posts.thumbImagePath', 'posts.shelf_id', 'posts.order', 'posts.event_id', 'posts.community_id')
                      ->with([
                          'community:id,name',
                          'shelf:id,name',
                          'featuredEventImage',
                          'images'
                      ])
                      ->orderBy('posts.order')
                      ->limit(4);
            },
            'cards' => function($query) {
                $query->select('cards.id', 'cards.name', 'cards.blurb', 'cards.type', 'cards.order', 'cards.post_id', 'cards.event_id', 'cards.button_text')
                      ->with([
                          'post:id,name,community_id',
                          'post.community:id,name',
                          'event:id,name,thumbImagePath,largeImagePath',
                          'images'
                      ])
                      ->orderBy('cards.order')
                      ->limit(4);
            }
        ])
        ->orderBy('order', 'ASC')
        ->get();
    }

    public function getAvailableShelves()
    {
        return Shelf::with(['community', 'posts' => function($query) {
            $query->select('id', 'name', 'thumbImagePath', 'shelf_id', 'order', 'event_id')
                  ->with([
                      'featuredEventImage',
                      'images',
                      'limitedCards.event' => function($query) {
                          $query->select('id', 'thumbImagePath', 'largeImagePath');
                      }
                  ])
                  ->orderBy('order')
                  ->limit(4);
        }])
        ->orderBy('name')
        ->get();
    }

    public function toggleShelf(Request $request, Dock $dock)
    {
        $request->validate([
            'shelf_id' => 'required|exists:shelves,id',
            'action' => 'required|in:attach,detach'
        ]);

        return $this->dockActions->toggleShelf(
            $dock, 
            $request->shelf_id, 
            $request->action
        );
    }

    public function getAvailablePosts()
    {
        return Post::with([
            'community:id,name',
            'shelf:id,name',
            'featuredEventImage',
            'images',
            'cards' => function($query) {
                $query->select('id', 'name', 'blurb', 'type', 'order', 'post_id', 'event_id', 'button_text')
                      ->with(['event:id,name,thumbImagePath,largeImagePath', 'images'])
                      ->orderBy('order');
            }
        ])
        ->select('id', 'name', 'thumbImagePath', 'shelf_id', 'community_id', 'order', 'event_id')
        ->orderBy('name')
        ->get();
    }

    public function togglePost(Request $request, Dock $dock)
    {
        $request->validate([
            'post_id' => 'required|exists:posts,id',
            'action' => 'required|in:attach,detach'
        ]);

        return $this->dockActions->togglePost(
            $dock, 
            $request->post_id, 
            $request->action
        );
    }

    public function toggleCard(Request $request, Dock $dock)
    {
        $request->validate([
            'card_id' => 'required|exists:cards,id',
            'action' => 'required|in:attach,detach'
        ]);

        return $this->dockActions->toggleCard(
            $dock, 
            $request->card_id, 
            $request->action
        );
    }

    /**
     * Get all available communities for filtering shelves.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableCommunities()
    {
        return Community::orderBy('name')
            ->select('id', 'name')
            ->get();
    }
}
