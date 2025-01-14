<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Genre;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use Carbon\Carbon;

class CachedDataController extends Controller
{
    public function getActiveCategories()
    {
        return Cache::rememberForever('active-categories', function () {
            return Category::with(['images' => function($query) {
                    $query->where('rank', 1);
                }])
                ->whereHas('events', function($query) {
                    $query->where('status', 'p')
                          ->where(function ($q) {
                              $q->where('closingDate', '>=', now()->startOfDay())
                                ->orWhereNull('closingDate');
                          });
                })
                ->orderBy('rank', 'desc')
                ->get();
        });
    }

    public function getActiveGenres()
    {
        return Cache::rememberForever('active-genres', function () {
            return Genre::where('admin', true)
                ->whereHas('events', function($query) {
                    $query->where('status', 'p')
                          ->where(function ($q) {
                              $q->where('closingDate', '>=', now()->startOfDay())
                                ->orWhereNull('closingDate');
                          });
                })
                ->orderBy('name')
                ->get();
        });
    }

}