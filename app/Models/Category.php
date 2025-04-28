<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Scopes\RankScope;
use Illuminate\Support\Str;

class Category extends Model
{
    /**
    * What protected variables are allowed to be passed to the database
    *
    * @var array
    */
    protected $fillable = [
    	'name', 'slug','description','largeImagePath', 'thumbImagePath', 'rank', 'remote','credit', 'type', 'applicable_attendance_types'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['hasEvent', 'supportsAttendanceType'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new RankScope);
    }

    protected $casts = [
        'remote' => 'boolean',
        'applicable_attendance_types' => 'array'
    ];
    
    /**
    * Each Category has many events
    *
    * @return \Illuminate\Database\Eloquent\Relations\hasMany
    */
    public function events() 
    {
        return $this->hasMany(Event::class);
    }

    /**
    * Determine if the current user has created events
    *
    * @return bool
    */
    public function getHasEventAttribute()
    {
        return $this->events()->count() ? true : false;    
    }
    
    /**
    * Sets the Route Key to slug instead of ID
    *
    * @return Route Key Name
    */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Check if this category supports a specific attendance type
     *
     * @param int $attendanceTypeId
     * @return bool
     */
    public function supportsAttendanceType($attendanceTypeId)
    {
        // If no applicable types are set, category supports all attendance types
        if (empty($this->applicable_attendance_types)) {
            return true;
        }
        
        // Otherwise, check if the specified type is in the array
        return in_array($attendanceTypeId, $this->applicable_attendance_types);
    }

    /**
     * Get whether this category supports a given attendance type
     * 
     * @param int $attendanceTypeId
     * @return bool
     */
    public function getSupportsAttendanceTypeAttribute()
    {
        return function($attendanceTypeId) {
            return $this->supportsAttendanceType($attendanceTypeId);
        };
    }

    /**
    * Updates the different elements of the model depending on the request
    *
    * @return nothing
    */
    public function updateElements($request, $category) 
    {
        $request->name !== $category->name && !$request->image ? MakeImage::renameImage($category, Str::slug($request->name), 'category', $request) : '';
        if ($request->image) {
            MakeImage::saveImage($request, $category, 600, 600, 'category');
        } else {
            $category->update([
                'credit' => $request->credit,
                'rank' => $request->rank,
                'description' => $request->description,
                'name' => $request->name,
                'slug' => Str::slug($request->name)
            ]);
        }
    }

    /**
    * Deletes the category images and then deletes the catgory
    *
    * @return Nothing
    */
    public function deleteCategory($category) {
        foreach ($category->events as $event) {
            $event->update([
                'category_id' => 0
            ]);
        }
        $category->delete();
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Get all attendance types that can be used with this category
     */
    public function attendanceTypes()
    {
        if (empty($this->applicable_attendance_types)) {
            return AttendanceType::all();
        }
        
        return AttendanceType::whereIn('id', $this->applicable_attendance_types)->get();
    }
}
