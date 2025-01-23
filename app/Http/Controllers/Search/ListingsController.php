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

        // Add tag filter
        if ($request->tag) {
            $tagIds = is_array($request->tag) 
                ? $request->tag 
                : explode(",", $request->tag);
            
            $filters['searchedTags'] = Genre::find($tagIds);
            $filters['tags'] = Query::bool()
                ->must(
                    Query::terms()
                        ->field('genres.genre_id')
                        ->values($tagIds)
                );
        }

        // Price filters
        if ($request->price0 !== null) {
            $minPrice = (float) $request->price0;
            $maxPrice = (float) $request->price1;
            
            $top = $maxPrice === 670 ? 9999 : $maxPrice;
            
            $filters['prices'] = Query::range()
                ->field('priceranges.price')
                ->gte($minPrice)
                ->lte($top);
        }

        // Date filters
        if ($request->start && $request->end) {
            $dateFilter = Query::bool();
            
            // Add the nested date range query
            $dateFilter->should(
                Query::nested()
                    ->path('shows')
                    ->query(
                        Query::range()
                            ->field('shows.date')
                            ->gte($request->start)
                            ->lte($request->end)
                    )
            );
            
            // Add the always-available condition
            $dateFilter->should(
                Query::term()
                    ->field('showtype')
                    ->value('a')
            );
            
            // Set minimum matches
            $dateFilter->minimumShouldMatch(1);

            $filters['dates'] = $dateFilter;
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

        // Add tag filter
        if ($searchFilters['tags'] ?? null) {
            $query->filter($searchFilters['tags']);
        }

        // Add geo filter if needed
        if ($request->searchType === 'inPerson' && isset($request->live)) {
            $query->filter($request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter']);
        }

        // Add date filter
        if ($searchFilters['dates'] ?? null) {
            $query->filter($searchFilters['dates']);
        }

        // Execute search and paginate
        $results = Event::searchQuery($query)
            ->load(['genres', 'category'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20);

        // Get max price from current filtered results
        $maxPrice = Event::searchQuery($query)
            ->aggregate('max_price', [
                'max' => [
                    'field' => 'priceranges.price'
                ]
            ])
            ->execute()
            ->aggregations()
            ->get('max_price')['value'] ?? 0;

        // Format results
        $searchedEvents = tap($results->toArray(), function (array &$content) {
            $content['data'] = Arr::pluck($content['data'], 'model');
        });

        // Prepare view data
        $viewData = [
            'categories' => Category::all(),
            'tags' => Genre::where('admin', 1)->orderBy('rank', 'desc')->get(),
            'maxprice' => ceil($maxPrice),
            'searchedEvents' => $searchedEvents,
            'searchedCategories' => $searchFilters['searchedCategories'] ?? [],
            'searchedTags' => $searchFilters['searchedTags'] ?? [],
        ];

        $viewData = array_merge($viewData, $locationFilters);

        return $request->searchType === 'inPerson' && isset($request->live)
            ? view('Search.location', $viewData)
            : view('search.all', $viewData);
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

        // Add debug logging for the filters
        \Log::info('Search Filters', [
            'tag_filter' => $searchFilters['tags'] ?? null,
            'query' => $query
        ]);

        // Execute search
        $results = Event::searchQuery($query)
            ->load(['genres', 'category'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20);

        // Get max price from current filtered results
        $maxPrice = Event::searchQuery($query)
            ->aggregate('max_price', [
                'max' => [
                    'field' => 'priceranges.price'
                ]
            ])
            ->execute()
            ->aggregations()
            ->get('max_price')['value'] ?? 0;

        // Format results and include maxPrice
        return tap($results->toArray(), function (array &$content) use ($maxPrice) {
            $content['data'] = Arr::pluck($content['data'], 'model');
            $content['maxPrice'] = ceil($maxPrice);
        });
    }

}
