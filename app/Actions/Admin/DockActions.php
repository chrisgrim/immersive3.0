<?php

namespace App\Actions\Admin;

use App\Models\Admin\Dock;
use Illuminate\Http\Request;

class DockActions
{
    /**
     * Toggle a shelf relationship with a dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @param  int  $shelfId
     * @param  string  $action
     * @return \App\Models\Admin\Dock
     */
    public function toggleShelf(Dock $dock, $shelfId, $action)
    {
        if ($action === 'attach') {
            $this->detachAllRelations($dock);
            $dock->shelves()->syncWithoutDetaching([$shelfId]);
        } else {
            $dock->shelves()->detach($shelfId);
        }

        return $dock->fresh([
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
            }
        ]);
    }

    /**
     * Remove a dock and its relations.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @return void
     */
    public function destroy(Dock $dock)
    {
        $this->detachAllRelations($dock);
        $dock->delete();
    }

    /**
     * Reorder docks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function reorder(Request $request)
    {
        foreach ($request->all() as $item) {
            Dock::find($item['id'])->update(['order' => $item['order']]);
        }
    }

    /**
     * Toggle a post relationship with a dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @param  int  $postId
     * @param  string  $action
     * @return \App\Models\Admin\Dock
     */
    public function togglePost(Dock $dock, $postId, $action)
    {
        if ($action === 'attach') {
            $this->detachAllRelations($dock);
            $dock->posts()->syncWithoutDetaching([$postId]);
        } else {
            $dock->posts()->detach($postId);
        }

        return $dock->fresh([
            'posts' => function($query) {
                $query->select('posts.id', 'posts.name', 'posts.thumbImagePath', 'posts.shelf_id', 'posts.order', 'posts.event_id', 'posts.community_id')
                      ->with([
                          'community:id,name',
                          'shelf:id,name',
                          'featuredEventImage',
                          'images',
                          'limitedCards.event' => function($query) {
                              $query->select('id', 'thumbImagePath', 'largeImagePath');
                          }
                      ])
                      ->orderBy('posts.order')
                      ->limit(4);
            }
        ]);
    }

    /**
     * Toggle a card relationship with a dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @param  int  $cardId
     * @param  string  $action
     * @return \App\Models\Admin\Dock
     */
    public function toggleCard(Dock $dock, $cardId, $action)
    {
        if ($action === 'attach') {
            $this->detachAllRelations($dock);
            $dock->cards()->syncWithoutDetaching([$cardId]);
        } else {
            $dock->cards()->detach($cardId);
        }

        return $dock->fresh([
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
        ]);
    }



    /**
     * Detach all relations from a dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @return void
     */
    protected function detachAllRelations(Dock $dock)
    {
        $dock->shelves()->detach();
        $dock->posts()->detach();
        $dock->cards()->detach();
    }
}
