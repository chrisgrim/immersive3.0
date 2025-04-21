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
        // If searchType is null or not set, we should NOT filter by hasLocation
        // This allows the default search to include both in-person and remote events
        if (!$request->searchType || $request->searchType === 'null') {
            return [
                // No hasLocation filter means we'll return both types
                'geoFilter' => $request->lat ? 
                    Query::geoDistance()
                        ->field('location_latlon')
                        ->distance('40km')
                        ->lat((float)$request->lat)
                        ->lon((float)$request->lng) : null
            ];
        }

        if ($request->searchType === 'inPerson') {
            return [
                'hasLocation' => Query::term()->field('hasLocation')->value(true),
                'inPersonCategories' => Category::where('remote', false)->get(),
                'geoFilter' => $request->lat ? 
                    Query::geoDistance()
                        ->field('location_latlon')
                        ->distance('40km')
                        ->lat((float)$request->lat)
                        ->lon((float)$request->lng) : null
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
            $categoryIds = [];
            $inputCategories = is_array($request->category) 
                ? $request->category 
                : explode(",", $request->category);
            
            // Convert any string-based slugs to numeric IDs
            foreach ($inputCategories as $categoryInput) {
                // Check if it's a valid number first
                if (is_numeric($categoryInput)) {
                    // Already a numeric ID
                    $categoryIds[] = (int)$categoryInput;
                } else if ($categoryInput === "NaN") {
                    // Handle explicit NaN from JavaScript conversion
                    continue;
                } else {
                    // It's a slug or non-numeric value, find the corresponding category ID
                    $category = Category::where('slug', $categoryInput)->first();
                    if ($category) {
                        $categoryIds[] = $category->id;
                    }
                }
            }
            
            // Only proceed if we have valid IDs
            if (!empty($categoryIds)) {
                $request->request->add(['category' => $categoryIds]);
                $filters['searchedCategories'] = Category::find($categoryIds);
                $filters['categories'] = Query::terms()->field('category_id')->values($categoryIds);
            }
        }

        // Add tag filter
        if ($request->tag) {
            $tagIds = [];
            $inputTags = is_array($request->tag) 
                ? $request->tag 
                : explode(",", $request->tag);
            
            // Convert any string-based slugs to numeric IDs
            foreach ($inputTags as $tagInput) {
                // Check if it's a valid number first
                if (is_numeric($tagInput)) {
                    // Already a numeric ID
                    $tagIds[] = (int)$tagInput;
                } else if ($tagInput === "NaN") {
                    // Handle explicit NaN from JavaScript conversion
                    continue;
                } else {
                    // It's a slug or non-numeric value, find the corresponding genre ID
                    $genre = Genre::where('slug', $tagInput)->first();
                    if ($genre) {
                        $tagIds[] = $genre->id;
                    }
                }
            }
            
            // Only proceed if we have valid IDs
            if (!empty($tagIds)) {
                $filters['searchedTags'] = Genre::find($tagIds);
                $filters['tags'] = Query::bool()
                    ->must(
                        Query::terms()
                            ->field('genres.genre_id')
                            ->values($tagIds)
                    );
            }
        }

        // Price filters
        if ($request->has('price0') || $request->has('price1')) {
            $minPrice = $request->has('price0') ? (float) $request->price0 : 0;
            
            $filters['prices'] = Query::range()
                ->field('priceranges.price')
                ->gte($minPrice);
            
            // Only add upper bound if price1 is set (meaning we're not at max)
            if ($request->has('price1')) {
                $maxPrice = (float) $request->price1;
                $filters['prices']->lte($maxPrice);
            }

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
            ->load(['genres', 'category', 'location'])
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

        // Always return a consistent structure, even with no results
        $searchedEvents = [
            'data' => [],           // Empty array for no results
            'total' => 0,           // Zero total for no results
            'current_page' => 1,    // Default page
            'per_page' => 20,       // Default per page
            'from' => null,         // No starting record
            'to' => null,           // No ending record
            'last_page' => 1        // Default last page
        ];

        if ($results->total() > 0) {
            $searchedEvents = tap($results->toArray(), function (array &$content) {
                $content['data'] = Arr::pluck($content['data'], 'model');
            });
        }

        // Add maxPrice to response
        $searchedEvents['maxPrice'] = ceil($maxPrice);

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
            ? view('search.location', $viewData)
            : view('search.all', $viewData);
    }

    public function apiIndex(Request $request)
    {   
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
                ($request->searchType === 'inPerson' || !$request->searchType || $request->searchType === 'null') && isset($request->live),
                fn($q) => $q->filter($request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter'])
            );

        // Execute search
        $results = Event::searchQuery($query)
            ->load(['genres', 'category', 'location'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20);

        // Get max price from CURRENT filtered results only
        $maxPrice = Event::searchQuery($query)
            ->aggregate('max_price', [
                'max' => [
                    'field' => 'priceranges.price'
                ]
            ])
            ->execute()
            ->aggregations()
            ->get('max_price')['value'] ?? 0;

        // Always return a consistent structure
        $response = [
            'data' => [],
            'total' => 0,
            'current_page' => 1,
            'per_page' => 20,
            'from' => null,
            'to' => null,
            'last_page' => 1,
            'maxPrice' => ceil($maxPrice)
        ];

        if ($results->total() > 0) {
            $response = array_merge(
                $results->toArray(),
                ['maxPrice' => ceil($maxPrice)]
            );
            $response['data'] = Arr::pluck($response['data'], 'model');
        }

        return $response;
    }

}
