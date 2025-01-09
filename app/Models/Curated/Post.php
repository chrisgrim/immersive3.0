<?php

namespace App\Models\Curated;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Event;
use App\Models\Featured\Feature;
use App\Models\Featured\Section;
use Illuminate\Database\Eloquent\Model;
use App\Models\Image;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [ 'name', 'slug', 'blurb', 'thumbImagePath', 'shelf_id', 'largeImagePath', 'user_id', 'community_id', 'event_id', 'section_id', 'status', 'type', 'image_type', 'order' ];

    protected $with = ['community', 'featuredEventImage'];

    /**
    * Sets the Route Key to slug instead of ID
    *
    * @return Route Key Name
    */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
    * Helpful command to see published listings
    *
    * @return bool
    */
    public function isPublished() {
        return $this->status == 'p';
    }

    /**
     * Get the Community that owns the Post.
     */
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the Shelf that owns the Post.
     */
    public function shelf()
    {
        return $this->belongsTo(Shelf::class);
    }

    /**
     * Get the Docks of the Posts.
     */
    public function docks()
    {
        return $this->morphToMany('\App\Models\Admin\Dock', 'association')->using('App\Models\Featured\Association');
    }

    /**
     * Get all of the posts featureds.
     */
    public function featured()
    {
        return $this->morphOne(Feature::class, 'featureable');
    }

    /**
     * Get the user that owns the post.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that owns the post.
     */
    public function featuredEventImage()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    /**
     * Get the cards for the collection .
     */
    public function cards()
    {
        return $this->hasMany(Card::class)->orderBy('order', 'ASC');
    }

    /**
     * Get the cards for the collection .
     */
    public function limitedCards()
    {
        return $this->hasMany(Card::class)->orderBy('order', 'ASC')->limit(5);
    }

    /**
     * Handle cascade deletes
     */
    protected static function boot()
    {
        parent::boot();
        
        // When force deleting (permanent deletion)
        static::forceDeleting(function($post) { 
            $post->cards()->each(function($card) {
                $card->destroyCard($card);
            });
        });
    }

    /**
     * Get all of the post's images.
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
