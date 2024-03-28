<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = [
    	'hiddenLocation','home','street','city','region','country','postal_code','longitude','latitude','event_id', 'hiddenLocationToggle', 'venue'
    ];
    
    /**
     * Each Location belongs to an Event Model.
     */

    public function event() 
    {
        return $this->belongsTo(Event::class);
    }

    /**
        Store Event Physical Location to Database
     */

    public static function storeEventLocation($request, $event)
    {
        $event->location->update($request->all());
        $event->update([
            'location_latlon' => [
                'lat' => $request->latitude,
                'lon' => $request->longitude,
            ],
            'hasLocation' => true,
        ]);
    }

    /**
        Store Event Remote Location
     */

    public static function storeRemoteLocation($request, $event)
    {
        foreach ($request->remote as $loc) {
            RemoteLocation::firstOrCreate([
                'slug' => Str::slug($loc)
            ],
            [
                'name' => $loc,
                'user_id' => auth()->user()->id,
            ]);
        };
        $newSync = RemoteLocation::whereIn('slug', collect($request->remote)->map(function ($item) {
            return Str::slug($item);
        })->toArray())->get();
        $event->remotelocations()->sync($newSync);

        $event->update([
            'hasLocation' => false,
            'location_latlon' => null,
            'remote_description' => $request->description,
        ]);

        
    }
}
