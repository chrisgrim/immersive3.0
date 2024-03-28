<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class AgeLimit extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = [];

    /**
     * Each age limit has many Events
     */
    public function events() 
    {
        return $this->hasMany(Event::class);
    }
}
