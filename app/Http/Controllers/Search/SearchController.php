<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\SearchActions;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Support\Analytics\Analytics;
use App\Support\Search\SearchGuard;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function navEvents(Request $request, SearchActions $searchActions)
    {
        $limit = $request->input('limit', 6);

        $query = Event::searchQuery(Query::bool()->must($searchActions->nameSearch($request))->filter(Event::publishedSearchFilter()))
            ->load(['currentUserFavorite'])
            ->size($limit);

        // Only sort by published_at when not performing a keyword search
        if (! $request->keywords) {
            $query->sort('published_at', 'desc');
        } else {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }

        return $this->recordNav($request, 'events', SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect()));
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

        return $this->recordNav($request, 'organizers', SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect()));
    }

    public function navNames(Request $request, SearchActions $searchActions)
    {
        $query = Event::searchQuery(Query::bool()->must($searchActions->eventSearch($request))->filter(Event::publishedSearchFilter()))
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

        return $this->recordNav($request, 'names', SearchGuard::run(fn () => $query->execute()->hits(), fn () => collect()));
    }

    /**
     * What a visitor typed into the nav search (Analytics::NAV_SEARCH), when
     * the nav_search capture is on: the text, which box, and how many names
     * came back. Only calls from the public nav (`nav=1`); the same endpoints
     * also serve the admin and the post editor. Every debounced keystroke
     * arrives, so reports keep the longest text per visitor and minute.
     */
    private function recordNav(Request $request, string $kind, $hits)
    {
        $text = is_string($request->keywords) ? trim(preg_replace('/[\p{C}]+/u', ' ', $request->keywords)) : '';

        if ($request->boolean('nav') && mb_strlen($text) >= 2 && Analytics::captures('nav_search')) {
            Analytics::record(Analytics::NAV_SEARCH, [
                'source' => $kind,
                // Same treatment as a typed place: no emails or phone numbers.
                'query' => preg_replace(['/\S+@\S+/u', '/\+?\d[\d\s().-]{5,}\d/u'], '[removed]', mb_substr($text, 0, 100)),
                'results' => is_countable($hits) ? count($hits) : 0,
            ], $request);
        }

        return $hits;
    }
}
