<?php

namespace App\Models;

use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Genre;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class Organizer extends Model
{
    use Searchable;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organizer) {
            $organizer->slug = Str::slug($organizer->name);
        });

        static::updating(function ($organizer) {
            if ($organizer->isDirty('name')) {
                $organizer->slug = Str::slug($organizer->name);
            }
        });
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

}
