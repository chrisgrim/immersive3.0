<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;

class CachedDataController extends Controller
{
    public function getActiveCategories()
    {
        return Cache::remember('active-categories', 3600, function () {
            return Category::select('categories.*')
                ->join('events', 'events.category_id', '=', 'categories.id')
                ->where('events.status', 'published')
                ->where('events.embargo_date', '<=', now())
                ->groupBy('categories.id')
                ->with(['images' => function ($query) {
                    $query->where('rank', 1);
                }])
                ->get();
        });
    }

    public function getActiveGenres()
    {
        return Cache::remember('active-genres', 3600, function () {
            return Genre::select('genres.*')
                ->join('event_genre', 'event_genre.genre_id', '=', 'genres.id')
                ->join('events', 'events.id', '=', 'event_genre.event_id')
                ->where('events.status', 'published')
                ->where('events.embargo_date', '<=', now())
                ->groupBy('genres.id')
                ->get();
        });
    }
}