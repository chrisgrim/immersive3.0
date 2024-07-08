<?php

namespace App\Models\Events;

use App\Scopes\RankScope;
use Illuminate\Database\Eloquent\Model;

class ContactLevel extends Model
{
	/**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = [ 'name', 'rank', 'admin', 'user_id'];

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
	* Each ContactLevel can belong to many events
	*
	* @return \Illuminate\Database\Eloquent\Relations\belongsToMany
	*/
    public function events() 
    {
    	return $this->belongsToMany(Event::class);
    }

    /**
     * This saves a new Contact Level type
     *
     * @return  nothing
     */
    public static function saveContactLevel($request) 
    {
        ContactLevel::create([
            'name' => $request->name,
            'admin' => true,
            'user_id' => auth()->user()->id
        ]);
    }

     /**
     * This updates a ContactLevel type
     *
     * @return nothing
     */
    public function updateContactLevel($request) 
    {
        $this->update([
            'rank' => $request->rank,
            'name' => $request->name,
            'user_id' => auth()->user()->id
        ]);
    }
}
