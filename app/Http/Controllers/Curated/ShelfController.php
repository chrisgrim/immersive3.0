<?php

namespace App\Http\Controllers\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Community;
use App\Models\Curated\Shelf;
use App\Http\Controllers\Controller;
use App\Actions\Curated\ShelfActions;

class ShelfController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Community $community, ShelfActions $shelfActions)
    {
        return $shelfActions->create($request, $community);
    }

    /**
     * update an existing model
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Shelf  $shelf
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Community $community, Shelf $shelf, ShelfActions $shelfActions)
    {
        return $shelfActions->update($request, $shelf);
    }

    /**
     * Destroy the specified resource.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Shelf  $shelf
     * @return \Illuminate\Http\Response
     */
    public function destroy(Community $community, Shelf $shelf, ShelfActions $shelfActions)
    {
        return $shelfActions->destroy($shelf);
    }

    /**
     * Paginate the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Shelf  $shelf
     * @return \Illuminate\Http\Response
     */
    public function paginate(Request $request, Community $community, Shelf $shelf, ShelfActions $shelfActions)
    {
        if ($request->type === 'published') {
            return $shelf->publishedPosts()->paginate(8);
        }
        return $shelf->posts()->paginate(8);
    }

    /**
     * Order the specified resource.
     *
     * @param  \App\Curated\Community  $community
     * @return \Illuminate\Http\Response
     */
    public function order(Request $request, Community $community, ShelfActions $shelfActions)
    {
        return $shelfActions->reorder($request);
    }

    /**
     * Toggle the hidden status of the specified shelf.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Shelf  $shelf
     * @return \Illuminate\Http\Response
     */
    public function toggleHidden(Community $community, Shelf $shelf)
    {
        $shelf->update(['is_hidden' => !$shelf->is_hidden]);
        
        return response()->json([
            'success' => true,
            'is_hidden' => $shelf->is_hidden,
            'message' => $shelf->is_hidden ? 'Shelf hidden successfully' : 'Shelf shown successfully'
        ]);
    }
}