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

    protected $fillable = [ 'name', 'blurb', 'url', 'order', 'post_id', 'event_id', 'type' ];

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
    * Deletes the card images and then deletes card
    *
    * @return void
    */
    public function destroyCard($card) 
    {
        if ($card->images()->exists()) {
            foreach ($card->images as $image) {
                ImageHandler::deleteImage($image);
            }
        }
        $card->delete();
    }
}
