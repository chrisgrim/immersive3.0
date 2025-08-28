<?php

namespace App\Models\Curated;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Event;
use App\Models\Image;
use App\Services\ImageHandler;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [ 'name', 'blurb', 'url', 'button_text', 'order', 'post_id', 'event_id', 'type' ];

    /**
    * The relations to eager load on every query.
    *
    * @var array
    */
    protected $with = ['event', 'images'];

    /**
     * Get the post that owns the Card.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Card belongs to an event.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get all of the card's images.
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Get the Docks of the Cards.
     */
    public function docks()
    {
        return $this->morphToMany('\App\Models\Admin\Dock', 'association')->using('App\Models\Featured\Association');
    }

    /**
    * Deletes a card and its associated images
    *
    * @param Card $card
    * @return void
    * @throws \Exception
    */
    public function destroyCard($card) 
    {
        try {
            $this->deleteCardImages($card);
            $card->delete();
        } catch (\Exception $e) {
            \Log::error('Failed to delete card:', [
                'card_id' => $card->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
    * Deletes all valid images associated with a card
    *
    * @param Card $card
    * @return void
    */
    private function deleteCardImages($card)
    {
        if (!$card->images()->exists()) return;

        foreach ($card->images as $image) {
            ImageHandler::deleteImage($image);
        }
    }
}
