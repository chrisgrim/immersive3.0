<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\RankScope;
use Illuminate\Support\Str;

class ContentAdvisory extends Model
{
    protected $fillable = [ 'name','admin', 'user_id', 'rank', 'slug' ];

    protected static function booted()
    {
        static::addGlobalScope(new RankScope);
    }

    public function events() 
    {
    	return $this->belongsToMany(Event::class);
    }

    public static function saveAdvisories($event, $advisories)
    {
        foreach ($advisories as $content) {
            // Ensure $content is treated as a string
            $name = is_array($content) ? $content['name'] : $content;

            ContentAdvisory::firstOrCreate([
                'slug' => Str::slug($name)
            ],
            [
                'user_id' => auth()->user()->id,
                'name' => $name,
            ]);
        }

        $newSync = ContentAdvisory::whereIn('slug', collect($advisories)->map(function ($item) {
            return Str::slug(is_array($item) ? $item['name'] : $item); 
        })->toArray())->get();

        $event->contentadvisories()->sync($newSync);
    }

    public function updateAdvisories($request) 
    {
        $this->update([
            'rank' => $request->rank,
            'user_id' => auth()->user()->id,
            'advisories' => $request->advisories,
            'slug' => Str::slug($request->advisories),
        ]);
    }
}
