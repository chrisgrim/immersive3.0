<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\SearchActions;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Support\Search\SearchGuard;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function navEvents(Request $request, SearchActions $searchActions)
    {
        $limit = $request->input('limit', 6);

        $query = Event::searchQuery($searchActions->nameSearch($request))
            ->load(['currentUserFavorite'])
            ->size($limit);

        // Only sort by published_at when not performing a keyword search
        if (! $request->keywords) {
            $query->sort('published_at', 'desc');
        } else {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }

        return SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect());
    }

    public function navOrganizers(Request $request, SearchActions $searchActions)
    {
        $limit = $request->input('limit', 6);

        $query = Organizer::searchQuery($searchActions->nameSearch($request))
            ->size($limit);

        // Only sort by published_at when not performing a keyword search
        if (! $request->keywords) {
            $query->sort('published_at', 'desc');
        } else {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }

        return SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect());
    }

    public function navNames(Request $request, SearchActions $searchActions)
    {
        $query = Event::searchQuery($searchActions->eventSearch($request))
            ->join(Organizer::class)
            ->load(['currentUserFavorite'])
            ->size(6);

        // Only track scores for keyword searches
        if ($request->keywords) {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        } else {
            // When no keywords, show newest events first
            $query->sort('published_at', 'desc');
        }

        return SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect());
    }
}
