<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Genre;
use Illuminate\Support\Facades\Cache;

class CachedDataController extends Controller
{
    public function getActiveCategories()
    {
        return Cache::rememberForever('active-categories', function () {
            return Category::with(['images' => function ($query) {
                $query->where('rank', 1);
            }])
                ->whereHas('events', function ($query) {
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
                ->whereHas('events', function ($query) {
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

    /**
     * The highest price across currently-eligible published events — bounds
     * the Hub saved-search editor's price slider (no live search-results
     * response to derive it from there, unlike the search page's own
     * SearchStore-fed value). Delegates to Event::getMostExpensive() rather
     * than a second, slightly different eligibility query of its own. A
     * 1-hour TTL rather than rememberForever like the sibling methods above:
     * those two get an explicit Cache::forget() from half a dozen
     * event-publish/edit call sites, and wiring this new key into all of
     * them too is more rippling risk than a route that's merely up to an
     * hour stale is worth.
     */
    public function getMaxPrice()
    {
        return response()->json([
            // Cast outside the closure, not just inside it — a cache HIT
            // (unlike the first, computing request) round-trips through
            // whatever the active store actually is, and at least one of
            // those (Redis) returns cached ints back as strings.
            'maxPrice' => (int) Cache::remember('max-price', now()->addHour(), function () {
                return (int) ceil(Event::getMostExpensive() ?? 0);
            }),
        ]);
    }
}
