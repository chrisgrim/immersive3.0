<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait ClearsFilterCache
{
    public static function bootClearsFilterCache()
    {
        // Called when model is saved (created or updated)
        static::saved(function ($model) {
            self::clearFilterCaches();
        });

        // Called when model is deleted
        static::deleted(function ($model) {
            self::clearFilterCaches();
        });
    }

    protected static function clearFilterCaches()
    {
        Cache::forget('active-categories');
        Cache::forget('active-genres');
    }
}