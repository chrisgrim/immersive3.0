<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\SearchActions;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Genre;
use App\Models\Category;

use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;

class SearchController extends Controller
{

    public function navEvents(Request $request, SearchActions $searchActions)
    {
        $limit = $request->input('limit', 6);
        
        $query = Event::searchQuery($searchActions->nameSearch($request))
            ->size($limit);
            
        // Only sort by published_at when not performing a keyword search
        if (!$request->keywords) {
            $query->sort('published_at', 'desc');
        } else {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }
        
        $results = $query->execute();
        
        return $results->hits();
    }

    public function navOrganizers(Request $request, SearchActions $searchActions)
    {
        $query = Organizer::searchQuery($searchActions->nameSearch($request))
            ->size(6);
            
        // Only sort by published_at when not performing a keyword search
        if (!$request->keywords) {
            $query->sort('published_at', 'desc');
        } else {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }
        
        $results = $query->execute();
        return $results->hits();
    }

    public function navNames(Request $request, SearchActions $searchActions)
    {
        $query = Event::searchQuery($searchActions->eventSearch($request))
            ->join(Organizer::class)
            ->size(6);
            
        // Only track scores for keyword searches
        if ($request->keywords) {
            // When searching, rely on relevance scoring
            $query->trackScores(true);
        }
        // Remove sorting by published_at which is causing fielddata errors
        
        $results = $query->execute();
        return $results->hits();
    }

    public function navGenres(Request $request, SearchActions $searchActions)
    {
        $query = Genre::searchQuery($searchActions->nameSearch($request))
            ->join(Category::class)
            ->size(6);
            
        // Only sort by relevance when performing a keyword search
        if ($request->keywords) {
            $query->trackScores(true);
        }
        
        $results = $query->execute();
        return $results->hits();
    }

    
}