<?php

namespace App\Actions\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Services\ImageHandler;

class CardActions
{

    /**
     * Create a newly registered card.
     *
     * @param  array  $input
     * @return \App\Models\Curated\Card
     */
    public function create(Request $request, Post $post)
    {
        $card = Card::create([
            'blurb' => $request->blurb,
            'name' => $request->name ? $request->name : null,
            'url' => $request->url ? $request->url : null,
            'event_id' => $request->event_id ? $request->event_id : null,
            'post_id' => $post->id,
            'type' => $request->type ? $request->type : 'b',
            'order' => $post->cards()->exists() ? $post->cards->last()->order + 1 : 0
        ]);

        if ($request->hasFile('image')) {
            ImageHandler::saveImage($request->file('image'), $card, 800, 450, 'card');
        }

        return $post->load('cards.event', 'cards.images', 'user');
    }

    /**
     * Updates an existing card
     *
     * @param  array  $input
     * @return \App\Models\Curated\Card
     */
    public function update(Request $request, Card $card)
    {
        $data = $request->except(['image', 'deleteImage']);
        $card->update($data);

        if ($request->hasFile('image')) {
            if ($card->images()->exists()) {
                foreach ($card->images as $image) {
                    ImageHandler::deleteImage($image);
                }
            }
            ImageHandler::saveImage($request->file('image'), $card, 800, 450, 'card');
            $card->touch();
        }

        return $card;
    }

    /**
     * Destroys an existing card
     *
     * @param  array  $input
     * @return \App\Models\Curated\Card
     */
    public function destroy(Card $card)
    {
        $post = $card->post_id;
        $card->destroyCard($card);
        return Post::with('cards','user')->find($post);
    }

    /**
     * Destroys an existing card
     *
     * @param  array  $input
     * @return \App\Models\Curated\Card
     */
    public function reorder(Request $request)
    {
        foreach ($request->all() as $card) {
            Card::find($card['id'])->update([
                'order' => $card['order'],
            ]);
        }
    }

}
