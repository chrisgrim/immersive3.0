<?php

namespace App\Models;

use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Genre;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\NameChangeRequest;


class Organizer extends Model
{
    use Searchable;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organizer) {
            $organizer->slug = static::generateUniqueSlug($organizer->name);
        });

        static::updating(function ($organizer) {
            if ($organizer->isDirty('name')) {
                $organizer->slug = static::generateUniqueSlug($organizer->name, $organizer->id);
            }
        });
    }

    /**
     * Generate a unique slug for the organizer
     */
    protected static function generateUniqueSlug($name, $excludeId = null)
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Keep checking until we find a unique slug
        while (static::slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug already exists
     */
    protected static function slugExists($slug, $excludeId = null)
    {
        $query = static::where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    protected $fillable = [
    	'user_id','name','website','slug','description','rating','largeImagePath','thumbImagePath','instagramHandle','twitterHandle','facebookHandle', 'email', 'status', 'patreon'
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function toSearchableArray()
    {
        return [
            "name" => $this->name ,
            "email" => $this->email,
            'priority' => 3,
            "published_at" => $this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function shouldBeSearchable()
    {
        return $this->status == 'p';
    }

    public function isPublished() {
        return $this->status == 'p';
    }

    public function events() 
    {
        return $this->hasMany(Event::class)->orderByDesc('updated_at');
    }

    public function user() 
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function listedEvents() 
    {
        return $this->hasMany(Event::class)->orderByDesc('updated_at')->where('archived', false);
    }

    public function archivedEvents() 
    {
        return $this->hasMany(Event::class)->orderByDesc('updated_at')->where('archived', true);
    }
    
    public function owner() 
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
                        ->withPivot('role')
                        ->withTimestamps()
                        ->as('membership');
    }

    public function allUsers()
    {
        return $this->users->merge([$this->owner]);
    }

    public function getHandles(){
        $result = [];
        if ($this->instagramHandle) {
            array_push($result, "https://www.instagram.com/{$this->instagramHandle}");
        }
        if ($this->facebookHandle) {
            array_push($result, "https://www.facebook.com/{$this->facebookHandle}");
        }
        if ($this->twitterHandle) {
            array_push($result, "https://www.twitter.com/{$this->twitterHandle}");
        }
        return $result;
    }

    public function deleteOrganizer($organizer) 
    {
        if ($organizer->users()->exists()) { $organizer->users()->detach(); }
        foreach ($organizer->events as $event) { $event->delete(); }
        $organizer->delete();
    }

    public function scopeWithPaginatedEvents($query)
    {
        return $query->with(['events' => function($query) {
            $query->where('status', 'p')
                ->where('archived', false)
                ->with(['category', 'genres']) // Add relationships needed for the listings
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }]);
    }

    public function scopeWithUserRole($query)
    {
        if (!auth()->check()) {
            return $query;
        }

        return $query
            ->addSelect(['user_role' => function($query) {
                $query->selectRaw("CASE 
                    WHEN organizers.user_id = ? THEN 'owner'
                    WHEN ? = 'a' THEN 'admin'
                    WHEN ? = 'm' THEN 'moderator'
                    ELSE (
                        SELECT role 
                        FROM organizer_user 
                        WHERE organizer_id = organizers.id 
                        AND user_id = ?
                    )
                    END", [
                        auth()->id(), 
                        auth()->user()->type,
                        auth()->user()->type,
                        auth()->id()
                    ]);
            }]);
    }

    public function scopeWithDetails($query)
    {
        return $query->withPaginatedEvents()
                    ->withUserRole();
    }

    public function nameChangeRequests()
    {
        return $this->morphMany(NameChangeRequest::class, 'requestable');
    }
}
