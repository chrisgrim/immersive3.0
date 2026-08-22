<?php

namespace App\Actions\Curated;

use App\Models\Curated\Card;
use App\Models\Curated\Post;
use App\Services\ImageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardActions
{
    /**
     * Create a new card
     */
    public function create(Request $request, Post $post)
    {
        // Validate the image (if any) BEFORE creating the card / shifting orders, so a bad
        // upload 422s cleanly without leaving an orphaned card or shifted siblings behind.
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            ]);
        }

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
            'order' => $request->order ?? ($post->cards()->exists() ? $post->cards->last()->order + 1 : 0),
        ]);

        if ($request->hasFile('image')) {
            ImageHandler::saveImage($request->file('image'), $card, 800, 500, 'card-images');
        }

        return $post->load('cards.images', 'user');
    }

    /**
     * Update an existing card
     */
    public function update(Request $request, Card $card)
    {
        // Allow-list. Notably excluding `post_id` — without this, a curator could
        // POST a different post_id and yank any card into their own post.
        $card->update($request->only(['name', 'blurb', 'url', 'button_text', 'order', 'event_id', 'type']));

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,png,jpg,webp|max:8192',
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
        // Card::find()->update() per row cost 2 queries plus Card's $with
        // (event, images) eager load on every find — one drag-and-drop reorder
        // of N cards was 3-4N queries (EI-LARAVEL-S: 160 queries for 40 cards).
        // A single CASE-WHEN UPDATE does it in one query. (Not Card::upsert():
        // its INSERT branch needs every NOT NULL column — post_id included —
        // even though the row always already exists.) Ids/orders are cast to
        // int before going into the raw CASE string, so nothing user-supplied
        // reaches the query unescaped.
        //
        // A row missing id/order is dropped rather than fataling on an
        // undefined array key — the old loop had the same exposure, this just
        // doesn't repeat it. keyBy('id') also makes a duplicate id last-wins,
        // matching the old loop's overwrite order (SQL's CASE otherwise
        // resolves duplicate WHENs first-match).
        $cards = collect($request->all())
            ->filter(fn ($card) => is_array($card) && isset($card['id'], $card['order']))
            ->keyBy(fn ($card) => (int) $card['id']);

        if ($cards->isEmpty()) {
            return;
        }

        $cases = $cards
            ->map(fn ($card) => sprintf('WHEN %d THEN %d', (int) $card['id'], (int) $card['order']))
            ->implode(' ');

        Card::whereIn('id', $cards->keys())
            ->update([
                'order' => DB::raw("CASE id {$cases} END"),
                'updated_at' => now(),
            ]);
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
