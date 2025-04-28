<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\RankScope;

class AttendanceType extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug', 'icon', 'rank'
    ];

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
     * Get the events that use this attendance type.
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Set the route key name to slug.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }
} 