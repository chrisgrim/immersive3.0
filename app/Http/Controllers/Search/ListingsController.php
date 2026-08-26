<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\Genre;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * app/Actions/Search/EventSearchFilterBuilder.php intentionally mirrors this
 * controller's filter-matching semantics (buildLocationFilter/
 * buildSearchFilters/buildMapBoundaryFilter/applyNonPriceFilters below) for
 * a separate moderator/admin-only feature (saved-search "notify me about new
 * events" — see NotifySavedSearchMatchesCommand) rather than being wired in
 * here — see that class's own docblock for why. If you change matching
 * behavior in this controller, that class needs the same change by hand
 * until the two are formally unified.
 */
class ListingsController extends Controller
{
    protected function buildLocationFilter(Request $request)
    {
        // If searchType is null or not set, we should NOT filter by attendance_type_id
        // This allows the default search to include both in-person and remote events
        if (! $request->searchType || $request->searchType === 'null') {
            $geoFilter = null;

            if ($request->lat && $request->lng) {
                if (is_numeric($request->lat) && is_numeric($request->lng)) {
                    $geoFilter = Query::geoDistance()
                        ->field('location_latlon')
                        ->distance('40km')
                        ->lat((float) $request->lat)
                        ->lon((float) $request->lng);
                } else {
                    \Log::warning('Invalid lat/lng coordinates received', [
                        'lat' => $request->lat,
                        'lng' => $request->lng,
                        'city' => $request->city,
                    ]);
                }
            }

            return ['geoFilter' => $geoFilter];
        }

        if ($request->searchType === 'inPerson') {
            // Get in-person attendance type ID (should be 1 based on migration)
            $inPersonId = 1;

            $geoFilter = null;

            if ($request->lat && $request->lng) {
                if (is_numeric($request->lat) && is_numeric($request->lng)) {
                    $geoFilter = Query::geoDistance()
                        ->field('location_latlon')
                        ->distance('40km')
                        ->lat((float) $request->lat)
                        ->lon((float) $request->lng);
                } else {
                    \Log::warning('Invalid lat/lng coordinates received', [
                        'lat' => $request->lat,
                        'lng' => $request->lng,
                        'city' => $request->city,
                    ]);
                }
            }

            return [
                'attendanceType' => Query::term()->field('attendance_type_id')->value($inPersonId),
                'inPersonCategories' => Category::withCount('events')->where(function ($query) use ($inPersonId) {
                    $query->whereJsonContains('applicable_attendance_types', $inPersonId)
                        ->orWhereNull('applicable_attendance_types'); // Include categories without restrictions
                })->get(),
                'geoFilter' => $geoFilter,
            ];
        }

        if ($request->searchType === 'atHome') {
            // Get remote attendance type ID (should be 2 based on migration)
            $remoteId = 2;

            return [
                'attendanceType' => Query::term()->field('attendance_type_id')->value($remoteId),
                'remoteCategories' => Category::withCount('events')->where(function ($query) use ($remoteId) {
                    $query->whereJsonContains('applicable_attendance_types', $remoteId)
                        ->orWhereNull('applicable_attendance_types'); // Include categories without restrictions
                })->get(),
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
                : explode(',', $request->category);

            // Convert any string-based slugs to numeric IDs
            foreach ($inputCategories as $categoryInput) {
                // Check if it's a valid number first
                if (is_numeric($categoryInput)) {
                    // Already a numeric ID
                    $categoryIds[] = (int) $categoryInput;
                } elseif ($categoryInput === 'NaN') {
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
            if (! empty($categoryIds)) {
                $request->request->add(['category' => $categoryIds]);
                $filters['searchedCategories'] = Category::withCount('events')->find($categoryIds);
                $filters['categories'] = Query::terms()->field('category_id')->values($categoryIds);
            }
        }

        // Add tag filter
        if ($request->tag) {
            $tagIds = [];
            $inputTags = is_array($request->tag)
                ? $request->tag
                : explode(',', $request->tag);

            // Convert any string-based slugs to numeric IDs
            foreach ($inputTags as $tagInput) {
                // Check if it's a valid number first
                if (is_numeric($tagInput)) {
                    // Already a numeric ID
                    $tagIds[] = (int) $tagInput;
                } elseif ($tagInput === 'NaN') {
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
            if (! empty($tagIds)) {
                $filters['searchedTags'] = Genre::find($tagIds);
                $filters['tags'] = Query::bool()
                    ->must(
                        Query::terms()
                            ->field('genres.genre_id')
                            ->values($tagIds)
                    );
            }
        }

        // At Home remote-location-type filter (e.g. "Zoom", "Telephone") —
        // accepts either a numeric id or a slug, matching the pattern above.
        // Gated on searchType === 'atHome': a stray remoteLocation param left
        // over from switching tabs (see nav-search.vue's handleLocationSearch)
        // must not silently filter an in-person search down to zero results —
        // in-person events have no remote_location_ids at all.
        if ($request->remoteLocation && $request->searchType === 'atHome') {
            $remoteLocation = is_numeric($request->remoteLocation)
                ? RemoteLocation::find((int) $request->remoteLocation)
                : RemoteLocation::where('slug', $request->remoteLocation)->first();

            if ($remoteLocation) {
                $filters['searchedRemoteLocation'] = $remoteLocation;
                $filters['remoteLocation'] = Query::terms()->field('remote_location_ids')->values([$remoteLocation->id]);
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
            // Normalize range to cover the full days the user picked. The frontend
            // sends both bounds at 00:00:00, which would otherwise exclude any show
            // happening later in the day on the chosen end date.
            $start = \Carbon\Carbon::parse($request->start)->startOfDay()->format('Y-m-d H:i:s');
            $end = \Carbon\Carbon::parse($request->end)->endOfDay()->format('Y-m-d H:i:s');

            $dateFilter = Query::bool();

            $dateFilter->should(
                Query::nested()
                    ->path('shows')
                    ->query(
                        Query::range()
                            ->field('shows.date')
                            ->gte($start)
                            ->lte($end)
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
        // Only build boundary filter when live is explicitly 'true'
        if (! isset($request->live) || $request->live !== 'true') {
            return null;
        }

        // Validate that all required geo coordinates are present and numeric
        $requiredCoords = ['NElat', 'NElng', 'SWlat', 'SWlng'];
        foreach ($requiredCoords as $coord) {
            if (! isset($request->$coord) || ! is_numeric($request->$coord)) {
                \Log::warning('Invalid geo boundary coordinates received', [
                    'coordinates' => $request->only($requiredCoords),
                ]);

                return null;
            }
        }

        return Query::bool()->filterRaw([
            'geo_bounding_box' => [
                'location_latlon' => [
                    'top_right' => [
                        'lat' => (float) $request->NElat,
                        'lon' => (float) $request->NElng,
                    ],
                    'bottom_left' => [
                        'lat' => (float) $request->SWlat,
                        'lon' => (float) $request->SWlng,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Every filter EXCEPT price — shared by the actual results query and the
     * max-price aggregation below, which must never include the current
     * price filter itself. Aggregating max price over an already
     * price-filtered query is self-referential (the ceiling can never
     * exceed whatever was just searched for), which made the price
     * slider's own upper bound shrink to match the last-applied filter,
     * permanently capping it there.
     *
     * $applyGeoFilter is passed in rather than derived here because index()
     * and apiIndex() gate it on subtly different conditions — apiIndex()'s
     * map search also applies it when searchType is empty/'null' (a bare
     * map view with no explicit mode chosen yet), which index() never needs
     * to since its two view templates only render with an explicit
     * searchType. Filter clause order doesn't affect which documents an ES
     * bool query matches, so both callers sharing this one method (each
     * passing their own condition) is behavior-identical to their previous
     * separately-maintained filter lists.
     */
    private function applyNonPriceFilters($query, array $searchFilters, array $locationFilters, $boundaryFilter, Request $request, bool $applyGeoFilter)
    {
        if ($locationFilters['attendanceType'] ?? null) {
            $query->filter($locationFilters['attendanceType']);
        }

        if ($searchFilters['categories'] ?? null) {
            $query->filter($searchFilters['categories']);
        }

        if ($searchFilters['tags'] ?? null) {
            $query->filter($searchFilters['tags']);
        }

        if ($searchFilters['remoteLocation'] ?? null) {
            $query->filter($searchFilters['remoteLocation']);
        }

        if ($applyGeoFilter) {
            $geoFilter = $request->live === 'true' ? $boundaryFilter : $locationFilters['geoFilter'];
            if ($geoFilter !== null) {
                $query->filter($geoFilter);
            }
        }

        if ($searchFilters['dates'] ?? null) {
            $query->filter($searchFilters['dates']);
        }

        return $query;
    }

    private function maxPriceQuery(array $searchFilters, array $locationFilters, $boundaryFilter, Request $request, bool $applyGeoFilter)
    {
        $query = Query::bool()->filter(Query::range()->field('closingDate')->gte('now/d'));

        return $this->applyNonPriceFilters($query, $searchFilters, $locationFilters, $boundaryFilter, $request, $applyGeoFilter);
    }

    public function index(Request $request)
    {
        $locationFilters = $this->buildLocationFilter($request);
        $searchFilters = $this->buildSearchFilters($request);
        $boundaryFilter = $this->buildMapBoundaryFilter($request);

        $applyGeoFilter = $request->searchType === 'inPerson' && isset($request->live);

        // Build query step by step
        $query = Query::bool()
            ->filter(Query::range()->field('closingDate')->gte('now/d'));

        $this->applyNonPriceFilters($query, $searchFilters, $locationFilters, $boundaryFilter, $request, $applyGeoFilter);

        // Add price filter
        if ($searchFilters['prices'] ?? null) {
            $query->filter($searchFilters['prices']);
        }

        // Clamp the requested page so Elasticsearch's from+size stays within the
        // default 10,000 result window (perPage * page must be <= 10000). Without
        // this, crawlers requesting huge page numbers trigger a 400 (EI-LARAVEL-6).
        $page = min(max((int) $request->input('page', 1), 1), 500);

        // Execute search and paginate
        $results = Event::searchQuery($query)
            ->load(['genres', 'category', 'location', 'attendanceType', 'currentUserFavorite', 'remotelocations'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20, 'page', $page);

        // Get max price from the current filtered results, EXCLUDING the
        // price filter itself (see applyNonPriceFilters/maxPriceQuery).
        $maxPrice = Event::searchQuery($this->maxPriceQuery($searchFilters, $locationFilters, $boundaryFilter, $request, $applyGeoFilter))
            ->aggregate('max_price', [
                'max' => [
                    'field' => 'priceranges.price',
                ],
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
            'last_page' => 1,        // Default last page
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
            'categories' => $request->searchType === 'inPerson'
                ? ($locationFilters['inPersonCategories'] ?? Category::withCount('events')->whereJsonContains('applicable_attendance_types', 1)->orWhereNull('applicable_attendance_types')->get())
                : ($request->searchType === 'atHome'
                    ? ($locationFilters['remoteCategories'] ?? Category::withCount('events')->whereJsonContains('applicable_attendance_types', 2)->orWhereNull('applicable_attendance_types')->get())
                    : Category::withCount('events')->get()),
            'tags' => Genre::where('admin', 1)->orderBy('rank', 'desc')->get(),
            'maxprice' => ceil($maxPrice),
            'searchedEvents' => $searchedEvents,
            'searchedCategories' => $searchFilters['searchedCategories'] ?? [],
            'searchedTags' => $searchFilters['searchedTags'] ?? [],
            'searchedRemoteLocation' => $searchFilters['searchedRemoteLocation'] ?? null,
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

        // Broader than index()'s own geo condition — this endpoint's map
        // search also applies it when searchType is empty/'null' (a bare map
        // view with no explicit mode chosen yet).
        $applyGeoFilter = ($request->searchType === 'inPerson' || ! $request->searchType || $request->searchType === 'null') && isset($request->live);

        // Build the main query
        $query = Query::bool()->filter(Query::range()->field('closingDate')->gte('now/d'));
        $this->applyNonPriceFilters($query, $searchFilters, $locationFilters, $boundaryFilter, $request, $applyGeoFilter);

        if ($searchFilters['prices'] ?? null) {
            $query->filter($searchFilters['prices']);
        }

        // Clamp the requested page so Elasticsearch's from+size stays within the
        // default 10,000 result window (perPage * page must be <= 10000). Without
        // this, crawlers requesting huge page numbers trigger a 400 (EI-LARAVEL-6).
        $page = min(max((int) $request->input('page', 1), 1), 500);

        // Execute search
        $results = Event::searchQuery($query)
            ->load(['genres', 'category', 'location', 'attendanceType', 'currentUserFavorite', 'remotelocations'])
            ->sortRaw(['published_at' => 'desc'])
            ->paginate(20, 'page', $page);

        // Get max price from the current filtered results, EXCLUDING the
        // price filter itself (see applyNonPriceFilters/maxPriceQuery).
        $maxPrice = Event::searchQuery($this->maxPriceQuery($searchFilters, $locationFilters, $boundaryFilter, $request, $applyGeoFilter))
            ->aggregate('max_price', [
                'max' => [
                    'field' => 'priceranges.price',
                ],
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
            'maxPrice' => ceil($maxPrice),
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
