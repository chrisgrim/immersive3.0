<?php

namespace App\Actions\Curated;

use Illuminate\Http\Request;
use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Services\ImageHandler;

class CardActions
{
    /**
     * Create a new card
     */
    public function create(Request $request, Post $post)
    {
        // If order is specified, make room for the new card
        if ($request->order !== null) {
            $this->shiftCardsOrder($post, $request->order);
        }

        $card = Card::create([
            'post_id' => $post->id,
            'event_id' => $request->event_id,
            'name' => $request->name,
            'blurb' => $request->blurb,
            'url' => $request->url,
            'button_text' => $request->button_text,
            'type' => $request->type,
            'order' => $request->order ?? ($post->cards()->exists() ? $post->cards->last()->order + 1 : 0)
        ]);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
            ]);
            ImageHandler::saveImage($request->file('image'), $card, 800, 500, 'card-images');
        }
        
        return $post->load('cards.images', 'user');
    }

    /**
     * Update an existing card
     */
    public function update(Request $request, Card $card)
    {
        $card->update($request->except(['image', 'deleteImage']));

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192'
            ]);
            if ($card->images()->exists()) {
                foreach ($card->images as $image) {
                    ImageHandler::deleteImage($image);
                }
            }
            ImageHandler::saveImage($request->file('image'), $card, 800, 500, 'card-images');
            $card->touch();
        }

        return $card->fresh()->load('event', 'images');
    }

    /**
     * Delete a card
     */
    public function destroy(Card $card)
    {
        $post = $card->post;
        $deletedOrder = $card->order;
        
        // Delete the card
        $card->destroyCard($card);
        
        // Shift remaining cards up
        $this->shiftCardsOrder($post, $deletedOrder + 1, -1);
        
        return $post->load('cards.images', 'user');
    }

    /**
     * Reorder cards
     */
    public function reorder(Request $request)
    {
        foreach ($request->all() as $card) {
            Card::find($card['id'])->update(['order' => $card['order']]);
        }
    }

    private function shiftCardsOrder(Post $post, int $position, int $shift = 1)
    {
        // Get all cards that need to be shifted
        $cardsToShift = Card::where('post_id', $post->id)
            ->where('order', '>=', $position)
            ->get();
        
        // Update their order
        foreach ($cardsToShift as $card) {
            $card->update(['order' => $card->order + $shift]);
        }
    }
}
