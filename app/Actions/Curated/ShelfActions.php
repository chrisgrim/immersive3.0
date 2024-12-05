<?php

namespace App\Actions\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Curated\Community;
use Illuminate\Support\Collection;

class ShelfActions
{
    /**
     * Create a new shelf.
     */
    public function create(Request $request, Community $community): Collection
    {
        // First, shift all existing shelves down by 1
        $community->shelves()->increment('order');

        // Create new shelf at order 0 (top)
        $community->shelves()->create([
            'user_id' => auth()->id(),
            'name' => 'New Shelf',
            'order' => 0
        ]);

        return $community->shelves()
            ->orderByDesc('status')
            ->orderByDesc('order')
            ->get()
            ->map(fn (Shelf $shelf) => $shelf->setRelation(
                'posts', 
                $shelf->posts()->paginate(8)
            ));
    }

    /**
     * Update an existing shelf.
     */
    public function update(Request $request, Shelf $shelf): Shelf
    {
        $shelf->update([
            'name' => $request->name
        ]);

        return $shelf->setRelation(
            'posts', 
            $shelf->posts()->paginate(4)
        );
    }

    /**
     * Delete a shelf.
     */
    public function destroy(Shelf $shelf): Collection
    {
        $shelf->delete();

        return $shelf->community->shelves()
            ->limit(3)
            ->get()
            ->map(fn (Shelf $shelf) => $shelf->setRelation(
                'posts', 
                $shelf->posts()->paginate(4)
            ));
    }

    /**
     * Reorder shelves.
     */
    public function reorder(Request $request): void
    {
        collect($request->all())->each(function (array $item) {
            Shelf::find($item['id'])->update([
                'order' => $item['order']
            ]);
        });
    }
}