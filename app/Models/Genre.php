<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\AdminScope;
use Illuminate\Support\Str;

class Genre extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
	protected $fillable = [
    	'name','admin', 'user_id', 'rank', 'slug'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new AdminScope);
    }
    
    /**
     * Each genre can belong to many Events
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function events() 
    {
    	return $this->belongsToMany(Event::class);
    }
    
    /**
     * This saves a new Genre type
     */
    public static function saveGenre($request) 
    {
        Genre::firstOrCreate([
            'slug' => Str::slug($request->name)
        ],
        [
            'name' => $request->name,
            'user_id' => auth()->user()->id,
            'admin' => true,
        ]);
    }

     /**
     * This updates a genre type
     */
    public function updateGenre($request) 
    {
        $this->update([
            'rank' => $request->rank,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'user_id' => auth()->user()->id,
            'admin' => $request->admin,
        ]);
    }  

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Save and sync genres for an event
     *
     * @param \App\Models\Event $event
     * @param array $genres
     * @return void
     */
    public static function saveGenres($event, $genres)
    {
        foreach ($genres as $content) {
            // Ensure $content is treated as a string and normalize it
            $name = is_array($content) ? $content['name'] : $content;
            $name = trim(ucfirst(strtolower($name))); // Normalize case and trim spaces

            Genre::firstOrCreate([
                'slug' => Str::slug($name)
            ],
            [
                'user_id' => auth()->user()->id,
                'name' => $name,
                'admin' => false,
            ]);
        }

        $newSync = Genre::whereIn('slug', collect($genres)->map(function ($item) {
            $name = is_array($item) ? $item['name'] : $item;
            return Str::slug(trim(strtolower($name))); // Normalize for lookup
        })->toArray())->get();

        $event->genres()->sync($newSync);
    }
}
