<?php

namespace App\Models;

use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Curated\Community;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dock extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location', 'status'];

    /**
     * Get all of the posts that are assigned this dock.
     */
    public function posts()
    {
        return $this->morphedByMany(Post::class, 'association');
    }

    /**
     * Get all of the shelves that are assigned this dock.
     */
    public function shelves()
    {
        return $this->morphedByMany(Shelf::class, 'association');
    }

    /**
     * Get all of the communities that are assigned this dock.
     */
    public function communities()
    {
        return $this->morphedByMany(Community::class, 'association');
    }

    /**
    * Helpful command to see published listings
    *
    * @return bool
    */
    public function isPublished() 
    {
        return $this->status === 'p';
    }
}
