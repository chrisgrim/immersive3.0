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
     * Detach all relations from a dock.
     *
     * @param  \App\Models\Admin\Dock  $dock
     * @return void
     */
    protected function detachAllRelations(Dock $dock)
    {
        $dock->shelves()->detach();
    }
}
