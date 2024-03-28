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
        $results = Event::searchQuery($searchActions->nameSearch($request))
            ->join(Organizer::class)
            ->size(6)
            ->execute();
        return $results->hits();
    }

    public function navGenres(Request $request, SearchActions $searchActions)
    {
        $results = Genre::searchQuery($searchActions->nameSearch($request))
            ->join(Category::class)
            ->size(6)
            ->execute();
        return $results->hits();
    }

    
}
