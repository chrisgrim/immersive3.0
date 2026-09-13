<?php

namespace App\Actions\Curated;

use App\Models\Curated\Community;
use App\Models\Curated\Shelf;
use Illuminate\Http\Request;
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
            'order' => 0,
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
            'name' => $request->name,
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
    public function reorder(Request $request, Community $community): void
    {
        // Ids come from the request body, which scopeBindings() cannot check,
        // so constrain every update to this community's own shelves.
        collect($request->all())
            ->filter(fn ($item) => is_array($item) && isset($item['id'], $item['order']))
            ->each(function (array $item) use ($community) {
                $community->shelves()->whereKey((int) $item['id'])->update([
                    'order' => (int) $item['order'],
                ]);
            });
    }
}
