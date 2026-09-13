<?php

namespace App\Http\Controllers\Curated;

use App\Actions\Curated\CardActions;
use App\Http\Controllers\Controller;
use App\Models\Curated\Card;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Community $community, Post $post, CardActions $cardActions)
    {
        return $cardActions->create($request, $post);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Curated\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function show(Card $card)
    {
        return $card;
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Community $community, Post $post, Card $card, CardActions $cardActions)
    {
        return $cardActions->update($request, $card);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Community $community, Post $post, Card $card, CardActions $cardActions)
    {
        return $cardActions->destroy($card);
    }

    /**
     * Order the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function order(Request $request, Community $community, Post $post, CardActions $cardActions)
    {
        $cardActions->reorder($request, $post);
    }
}
