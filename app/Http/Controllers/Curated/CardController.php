<?php

namespace App\Http\Controllers\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Actions\Curated\CardActions;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\Curated\Community;

class CardController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @param  \App\Models\Curated\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Community $community, Post $post, Card $card, CardActions $cardActions)
    {
        return $cardActions->update($request, $card);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @param  \App\Models\Curated\Card  $card
     * @return \Illuminate\Http\Response
     */
    public function destroy(Community $community, Post $post, Card $card, CardActions $cardActions)
    {
        return $cardActions->destroy($card);
    }

    /**
     * Order the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Curated\Community  $community
     * @param  \App\Models\Curated\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function order(Request $request, Community $community, Post $post, CardActions $cardActions)
    {
        $cardActions->reorder($request);
    }
}
