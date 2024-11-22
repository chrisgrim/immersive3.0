<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Category;
use Illuminate\Support\Arr;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;

class ListingsController extends Controller
{
    protected function getBaseData()
    {
        return [
            'maxprice' => ceil(Event::getMostExpensive()),
            'categories' => Category::all(),
            'tags' => Genre::where('admin', 1)->orderBy('rank', 'desc')->get(),
        ];
    }

    protected function buildLocationFilter(Request $request)
    {
        if ($request->searchType === 'inPerson') {
            return [
                'hasLocation' => Query::term()->field('hasLocation')->value(true),
                'inPersonCategories' => Category::where('remote', false)->get(),
                'geoFilter' => $request->lat ? 
                    Query::geoDistance()
                        ->field('location_latlon')
                        ->distance('40km')
                        ->lat($request->lat)
                        ->lon($request->lng) : null
            ];
        }

        if ($request->searchType === 'atHome') {
            return [
                'hasLocation' => Query::term()->field('hasLocation')->value(false),
                'atHomeCategories' => Category::where('remote', true)->get()
            ];
        }

        return [];
    }

    protected function buildSearchFilters(Request $request)
    {
        \Log::info('Building search filters', [
            'all_params' => $request->all(),
            'price0' => $request->price0,
            'price1' => $request->price1
        ]);

        $filters = [];

        // Category filters
        if ($request->category) {
            $categoryIds = is_array($request->category) 
                ? $request->category 
                : explode(",", $request->category);
            
            $request->request->add(['category' => $categoryIds]);
            $filters['searchedCategories'] = Category::find($categoryIds);
            $filters['categories'] = Query::terms()->field('category_id')->values($categoryIds);
        }

        // Price filters
        if ($request->price0 !== null) {
            $minPrice = (float) $request->price0;
            $maxPrice = (float) $request->price1;
            
            // If max price is 670 (or your max value), treat it as no upper limit
            $top = $maxPrice === 670 ? 9999 : $maxPrice;
            
            \Log::info('Price Filter Values', [
                'minPrice' => $minPrice,
                'maxPrice' => $maxPrice,
                'top' => $top
            ]);
            
            $filters['prices'] = Query::range()
                ->field('priceranges.price')
                ->gte($minPrice)
                ->lte($top);
                
            \Log::info('Price Filter Object', [
                'filter' => json_encode($filters['prices'])
            ]);
        }

        return $filters;
    }

    protected function buildMapBoundaryFilter(Request $request)
    {
        if (!$request->live) {
            return null;
        }

        return Query::bool()->filterRaw([
            'geo_bounding_box' => [
                'location_latlon' => [
                    'top_right' => [
                        'lat' => $request->NElat,
                        'lon' => $request->NElng,
                    ],
                    'bottom_left' => [
                        'lat' => $request->SWlat,
                        'lon' => $request->SWlng,
                    ]
                ]
            ]
        ]);
    }

    public function index(Request $request)
    {
        \Log::info('Search Request Started', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_params' => $request->all()
        ]);

        // Get base data and filters
        $baseData = $this->getBaseData();
        $locationFilters = $this->buildLocationFilter($request);
        $searchFilters = $this->buildSearchFilters($request);
        $boundaryFilter = $this->buildMapBoundaryFilter($request);

        // Build query step by step
        $query = Query::bool()
            ->filter(Query::range()->field('closingDate')->gte('now/d'));

        // Add location filter
        if ($locationFilters['hasLocation'] ?? null) {
            $query->filter($locationFilters['hasLocation']);
        }

        // Add price filter
        if ($searchFilters['prices'] ?? null) {
            $query->filter($searchFilters['prices']);
        }

        // Add category filter
        if ($searchFilters['categories'] ?? null) {
            $query->filter($searchFilters['categories']);
        }

        // Add geo filter if needed
        if ($request->searchType === 'inPerson' && isset($request->live)) {
            $query->filter($request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter']);
        }

        \Log::info('Final Query Raw', [
            'query' => json_encode($query)
        ]);

        // Execute search
        $results = Event::searchQuery($query)
            ->load(['genres', 'category'])
            ->sortRaw(['published_at' => 'desc'])
            ->raw();

        \Log::info('Elasticsearch Response', [
            'total' => $results['hits']['total'] ?? 0,
            'max_score' => $results['hits']['max_score'] ?? 0,
            'first_hit' => $results['hits']['hits'][0] ?? null
        ]);

        // Then paginate
        $results = Event::searchQuery($query)
            ->load(['genres', 'category'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20);

        // Format results
        $searchedEvents = tap($results->toArray(), function (array &$content) {
            $content['data'] = Arr::pluck($content['data'], 'model');
        });

        // Prepare view data
        $viewData = array_merge($baseData, [
            'searchedEvents' => $searchedEvents,
            'searchedCategories' => $searchFilters['searchedCategories'] ?? [],
            'searchedTags' => $searchFilters['searchedTags'] ?? [],
        ], $locationFilters);

        // Return appropriate view
        if ($request->searchType === 'inPerson' && isset($request->live)) {
            return view('Search.location', $viewData);
        }
        
        return view('search.all', $viewData);
    }

    public function apiIndex(Request $request)
    {
        // Debug the incoming request
        \Log::info('API Request', [
            'price0' => $request->price0,
            'price1' => $request->price1,
            'all_parameters' => $request->all()
        ]);
        
        // Get location specific filters
        $locationFilters = $this->buildLocationFilter($request);
        
        // Get search filters
        $searchFilters = $this->buildSearchFilters($request);
        
        // Build map boundary filter if needed
        $boundaryFilter = $this->buildMapBoundaryFilter($request);

        // Build the main query
        $query = Query::bool()
            ->filter(Query::range()->field('closingDate')->gte('now/d'))
            ->when($locationFilters['hasLocation'] ?? null, fn($q) => $q->filter($locationFilters['hasLocation']))
            ->when($searchFilters['prices'] ?? null, fn($q) => $q->filter($searchFilters['prices']))
            ->when($searchFilters['categories'] ?? null, fn($q) => $q->filter($searchFilters['categories']))
            ->when($searchFilters['dates'] ?? null, fn($q) => $q->filter($searchFilters['dates']))
            ->when($searchFilters['tags'] ?? null, fn($q) => $q->filter($searchFilters['tags']))
            ->when(
                $request->searchType === 'inPerson' && isset($request->live),
                fn($q) => $q->filter($request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter'])
            );

        // Execute search
        $results = Event::searchQuery($query)
            ->load(['genres', 'category'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20);

        // Format results
        return tap($results->toArray(), function (array &$content) {
            $content['data'] = Arr::pluck($content['data'], 'model');
        });
    }

}
