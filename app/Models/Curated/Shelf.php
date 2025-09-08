<?php

namespace App\Models\Curated;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Featured\Feature;

class Shelf extends Model
{
    use HasFactory;

    protected $fillable = [ 'name', 'order', 'user_id', 'community_id', 'parent_id', 'status', 'is_hidden'];

    protected $with = ['community'];

    /**
    * Helpful command to see published listings
    *
    * @return bool
    */
    public function isPublished() {
        return $this->status === 'p';
    }

    /**
     * Get the posts for the shelf .
     */
    public function posts()
    {
        return $this->hasMany(Post::class)->orderBy('order', 'ASC');
    }

    /**
     * Get the docks for the shelf .
     */
    public function docks()
    {
        return $this->morphToMany('\App\Models\Admin\Dock', 'association')->using('App\Models\Featured\Association');
    }

    /**
     * Get the Community that owns the Shelf.
     */
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Show the posts that are published
     */
    public function publishedPosts()
    {
        return $this->hasMany(Post::class)->orderBy('order', 'ASC')->where('status', 'p')->where('is_hidden', false)->limit(4);
    }

    /**
     * Get all of the communities featureds.
     */
    public function featured()
    {
        return $this->morphOne(Feature::class, 'featureable');
    }
}