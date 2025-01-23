<?php

namespace App\Models\Events;

use App\Scopes\RankScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MobilityAdvisory extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = [ 'name','admin', 'user_id', 'rank', 'slug' ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new RankScope);
    }
    
    /**
    * Each ContentAdvisory can belong to many events
    *
    * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
    */
    public function events() 
    {
        return $this->belongsToMany(Event::class);
    }

    public static function saveAdvisories($event, $advisories)
    {
        foreach ($advisories as $content) {
            // Ensure $content is treated as a string and normalize it
            $name = is_array($content) ? $content['name'] : $content;
            $name = trim(ucfirst(strtolower($name))); // Normalize case and trim spaces

            MobilityAdvisory::firstOrCreate([
                'slug' => Str::slug($name)
            ],
            [
                'user_id' => auth()->user()->id,
                'name' => $name,
            ]);
        }

        $newSync = MobilityAdvisory::whereIn('slug', collect($advisories)->map(function ($item) {
            $name = is_array($item) ? $item['name'] : $item;
            return Str::slug(trim(strtolower($name))); // Normalize for lookup
        })->toArray())->get();

        $event->mobilityadvisories()->sync($newSync);
    }

    public function updateMobilitiesLevel($request) 
    {
        $this->update([
            'rank' => $request->rank,
            'user_id' => auth()->user()->id,
            'mobilities' => $request->mobilities,
            'slug' => Str::slug($request->mobilities),
        ]);
    }
}
