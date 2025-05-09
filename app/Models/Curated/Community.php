<?php

namespace App\Models\Curated;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ImageFile;
use App\Models\User;
use App\Models\Featured\Feature;
use App\Models\Image;
use App\Models\Featured\Section;
use Illuminate\Database\Eloquent\Model;
use App\Models\NameChangeRequest;
use Illuminate\Support\Str;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [ 'name', 'user_id', 'slug', 'blurb', 'description', 'thumbImagePath', 'largeImagePath', 'instagramHandle', 'twitterHandle', 'facebookHandle', 'patreon', 'status' ];

    /**
     * Delete any posts with the community
     */
    public static function boot() {
        parent::boot();
        self::deleting(function($community) { 
            $community->posts()->each(function($post) {
                ImageFile::deletePreviousImages($post);
                $post->delete();
            });
        });

        static::creating(function ($community) {
            $community->slug = Str::slug($community->name);
        });

        static::updating(function ($community) {
            if ($community->isDirty('name')) {
                $community->slug = Str::slug($community->name);
            }
        });
    }

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
     * Get the posts for the community .
     */
    public function posts()
    {
        return $this->hasMany(Post::class)->orderBy('order', 'ASC');
    }

    /**
     * Get the Docks of the Communities.
     */
    public function docks()
    {
        return $this->morphToMany('\App\Models\Admin\Dock', 'association')->using('App\Models\Featured\Association');
    }

    /**
     * Returns limited posts for the community .
     */
    public function limitedPosts()
    {
        return $this->hasMany(Post::class)->orderBy('order', 'ASC')->limit(3);
    }

    /**
     * Returns community Shelves.
     */
    public function shelves()
    {
        return $this->hasMany(Shelf::class)->orderBy('order', 'ASC');
    }

    /**
     * Returns community Shelves.
     */
    public function publishedShelves()
    {
        return $this->hasMany(Shelf::class)->orderBy('order', 'ASC')->where('status', 'p');
    }

    /**
     * Returns community Shelves.
     */
    public function limitedShelves()
    {
        return $this->hasMany(Shelf::class)->orderBy('order', 'ASC')->limit(3);
    }

    /**
     * Get the owner of the community .
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the curators for the community .
     */
    public function curators()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get all of the communities featureds.
     */
    public function featured()
    {
        return $this->morphOne(Feature::class, 'featureable');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function curatorInvitations()
    {
        return $this->hasMany(CuratorInvitation::class);
    }

    /**
     * Get the name change requests for the community.
     */
    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }

}
