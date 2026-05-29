<?php

namespace App\Models;

use App\Scopes\RankScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = [
        'name', 'slug', 'description', 'largeImagePath', 'thumbImagePath', 'rank', 'remote', 'credit', 'type', 'applicable_attendance_types',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    // NOTE: hasEvent is intentionally NOT appended. It runs events()->count()
    // (or reads an eager-loaded events_count), which N+1s whenever a Category
    // collection is serialized. No client reads it; call $category->hasEvent
    // directly if ever needed. See EI-LARAVEL-K / appends-N+1 sweep.
    protected $appends = ['supportsAttendanceType'];

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
        'applicable_attendance_types' => 'array',
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
        // Prefer an eager-loaded count (->withCount('events')) when present to
        // avoid an N+1: this accessor is appended, so it fires on every
        // serialization — e.g. the category filters listed on /index/search.
        if (array_key_exists('events_count', $this->attributes)) {
            return (int) $this->attributes['events_count'] > 0;
        }

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
     * @param  int  $attendanceTypeId
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
     * @param  int  $attendanceTypeId
     * @return bool
     */
    public function getSupportsAttendanceTypeAttribute()
    {
        return function ($attendanceTypeId) {
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
        $request->name !== $category->name && ! $request->image ? MakeImage::renameImage($category, Str::slug($request->name), 'category', $request) : '';
        if ($request->image) {
            MakeImage::saveImage($request, $category, 600, 600, 'category');
        } else {
            $category->update([
                'credit' => $request->credit,
                'rank' => $request->rank,
                'description' => $request->description,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
            ]);
        }
    }

    /**
     * Deletes the category images and then deletes the catgory
     *
     * @return Nothing
     */
    public function deleteCategory($category)
    {
        foreach ($category->events as $event) {
            $event->update([
                'category_id' => 0,
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
