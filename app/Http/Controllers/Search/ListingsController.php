<?php

namespace App\Http\Controllers\Search;

use App\Actions\Search\EventSearchFilterBuilder;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\Genre;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * "Which events match this search" is defined ONCE, in
 * EventSearchFilterBuilder, and this controller delegates to it. The saved-
 * search notifier (NotifySavedSearchMatchesCommand) uses the same class, so
 * an alert can no longer disagree with what the search page shows.
 *
 * They used to be two hand-maintained copies, and had already drifted: the
 * builder gated coordinates on isset() while this controller used
 * truthiness, so a coordinate of exactly 0 silently dropped the geo filter
 * here but not there. Unified 2026-08-26; the isset() behaviour won, so a
 * 0 coordinate now filters correctly on both paths.
 *
 * What stays here is everything that is genuinely this controller's job and
 * not the query's: parsing raw request input (slugs to ids, comma-separated
 * lists, JavaScript's 'NaN'), logging bad coordinates, and assembling the
 * category/tag/remote-location lists the search UI renders. Those three
 * concerns living inside the filter builders is what made the logic
 * un-shareable in the first place.
 */
class ListingsController extends Controller
{
    /**
     * Raw request -> the normalised criteria EventSearchFilterBuilder takes.
     * This is the adapter layer: slugs resolved to ids, comma-separated
     * lists split, JavaScript's literal 'NaN' dropped, coordinates validated
     * (and logged when they're junk, which the builder deliberately doesn't
     * do — it has no request to describe in a log line).
     *
     * `live` is cast to a real bool here. The request carries the string
     * 'true'; the builder works in booleans, same as a saved search's stored
     * criteria. Converting once, here, is what lets both callers share it.
     */
    protected function criteriaFromRequest(Request $request): array
    {
        // Memoised per request object. This is called by all three filter
        // builders and again by both the results query and the max-price
        // aggregation — five times for one search — and it does slug->id
        // lookups against categories, genres and remote_locations. Without
        // this the refactor would have turned one set of lookups into five on
        // the busiest page on the site, and logged the same bad-coordinate
        // warning five times over (caught in review).
        //
        // A WeakMap keyed on the request itself, not spl_object_id(). Object
        // ids are only unique among LIVE objects, so a short-lived request
        // that has already been collected can hand its id to the next one and
        // serve it the previous request's criteria — which is exactly what
        // happened, caught by the test below. A WeakMap keys on identity and
        // drops entries when the request is collected, so it can't confuse
        // two requests or hold one alive.
        $this->criteriaCache ??= new \WeakMap;

        return $this->criteriaCache[$request] ??= $this->buildCriteria($request);
    }

    /** @var \WeakMap<Request, array<string, mixed>>|null */
    private ?\WeakMap $criteriaCache = null;

    private function buildCriteria(Request $request): array
    {
        return [
            'searchType' => $request->searchType,
            ...$this->coordinates($request),
            'live' => $request->live === 'true',
            'NElat' => $request->NElat,
            'NElng' => $request->NElng,
            'SWlat' => $request->SWlat,
            'SWlng' => $request->SWlng,
            'categoryIds' => $this->resolveIds($request->category, Category::class),
            'tagIds' => $this->resolveIds($request->tag, Genre::class),
            'remoteLocationId' => $this->resolveRemoteLocationId($request),
            'priceMin' => $request->has('price0') ? (float) $request->price0 : null,
            'priceMax' => $request->has('price1') ? (float) $request->price1 : null,
            ...$this->dates($request),
        ];
    }

    /**
     * The search centre as floats the builder can use, or nulls.
     *
     * Validated as a PAIR, and logged at most once — a geocode that produced
     * junk produced one bad location, not two independent bad numbers, and
     * the log line names the city precisely because this is the only layer
     * that still knows it. The builder returns null silently by design; it
     * has no request to describe.
     *
     * "Numeric" is not enough on its own: Elasticsearch rejects an
     * out-of-range geo point with a 400 that fails every shard, so a lng of
     * 3.14326023E8 500s this page instead of returning nothing
     * (EI-LARAVEL-11). Hence the shared isValidLatitude/isValidLongitude,
     * which range-check as well.
     *
     * isset(), not truthiness. This controller used to gate on
     * `$request->lat && $request->lng`, under which a coordinate of exactly 0
     * — the equator, or the prime meridian through London — is falsy and
     * silently dropped the geo filter, quietly returning worldwide results
     * for a location search. EventSearchFilterBuilder already gated on
     * isset() and called that out as a bug here; unifying the two is what
     * fixes it.
     *
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordinates(Request $request): array
    {
        $lat = $request->lat;
        $lng = $request->lng;

        if (! isset($lat) || ! isset($lng)) {
            return ['lat' => null, 'lng' => null];
        }

        if (! EventSearchFilterBuilder::isValidLatitude($lat) || ! EventSearchFilterBuilder::isValidLongitude($lng)) {
            \Log::warning('Invalid lat/lng coordinates received', [
                'lat' => $lat,
                'lng' => $lng,
                'city' => $request->city,
            ]);

            return ['lat' => null, 'lng' => null];
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * The date range, or nulls if either end is not a date.
     *
     * Same treatment as coordinates above: validate here where there is a
     * request worth describing in a log line, and hand the builder something
     * it can trust. Junk is dropped rather than fatal — an unparseable date
     * used to throw out of the filter builder as a 500 (EI-LARAVEL-10, a
     * crawler requesting a double-encoded '2026-08-26 00%3A00%3A00').
     *
     * Both ends are dropped together because the filter needs the pair; half a
     * range would silently widen the search rather than narrow it.
     */
    private function dates(Request $request): array
    {
        $start = $request->start;
        $end = $request->end;

        $invalid = (filled($start) && ! EventSearchFilterBuilder::parseSearchDate($start))
            || (filled($end) && ! EventSearchFilterBuilder::parseSearchDate($end));

        if ($invalid) {
            \Log::warning('Invalid search date received', [
                'start' => $start,
                'end' => $end,
            ]);

            return ['start' => null, 'end' => null];
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Category/genre input arrives as ids, slugs, or a comma-separated mix of
     * both, and JavaScript sometimes sends the literal string 'NaN' for a
     * failed Number() conversion. Unknown slugs are dropped rather than
     * erroring — a stale bookmarked URL should return results, not a 500.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return int[]
     */
    private function resolveIds($input, string $model): array
    {
        if (! $input) {
            return [];
        }

        $ids = [];

        $items = is_array($input) ? $input : explode(',', $input);

        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
            } elseif ($item !== 'NaN') {
                if ($found = $model::where('slug', $item)->first()) {
                    $ids[] = $found->id;
                }
            }
        }

        return $ids;
    }

    private function resolveRemoteLocationId(Request $request): ?int
    {
        if (! $request->remoteLocation) {
            return null;
        }

        $remoteLocation = is_numeric($request->remoteLocation)
            ? RemoteLocation::find((int) $request->remoteLocation)
            : RemoteLocation::where('slug', $request->remoteLocation)->first();

        return $remoteLocation?->id;
    }

    /**
     * Attendance-type + geo filters from EventSearchFilterBuilder, plus the
     * category lists only the search UI needs (the picker shows which
     * categories apply to the chosen attendance type). The query shapes are
     * the builder's; the picker lists are this controller's.
     */
    protected function buildLocationFilter(Request $request)
    {
        $criteria = $this->criteriaFromRequest($request);
        $filters = $this->filterBuilder()->locationFilter($criteria);

        if ($request->searchType === 'inPerson') {
            $filters['inPersonCategories'] = $this->categoriesForAttendanceType(1);
        } elseif ($request->searchType === 'atHome') {
            $filters['remoteCategories'] = $this->categoriesForAttendanceType(2);
        }

        return $filters;
    }

    /**
     * Category/tag/remote-location/price/date filters from the builder, plus
     * the `searched*` models the UI echoes back as active-filter chips.
     */
    protected function buildSearchFilters(Request $request)
    {
        $criteria = $this->criteriaFromRequest($request);
        $filters = $this->filterBuilder()->searchFilters($criteria);

        if ($criteria['categoryIds']) {
            // Written back onto the request because downstream view code
            // still reads `category` expecting resolved ids, not the slugs
            // the URL may have carried.
            $request->request->add(['category' => $criteria['categoryIds']]);
            $filters['searchedCategories'] = Category::withCount('events')->find($criteria['categoryIds']);
        }

        if ($criteria['tagIds']) {
            $filters['searchedTags'] = Genre::find($criteria['tagIds']);
        }

        if ($criteria['remoteLocationId'] && $request->searchType === 'atHome') {
            $filters['searchedRemoteLocation'] = RemoteLocation::find($criteria['remoteLocationId']);
        }

        return $filters;
    }

    protected function buildMapBoundaryFilter(Request $request)
    {
        $criteria = $this->criteriaFromRequest($request);

        if (! $criteria['live']) {
            return null;
        }

        // Logged here, not in the builder, for the same reason as
        // numericCoord() above — the builder returns null silently.
        $required = ['NElat', 'NElng', 'SWlat', 'SWlng'];
        foreach ($required as $coord) {
            $valid = str_ends_with($coord, 'lat')
                ? EventSearchFilterBuilder::isValidLatitude($request->$coord)
                : EventSearchFilterBuilder::isValidLongitude($request->$coord);

            if (! $valid) {
                \Log::warning('Invalid geo boundary coordinates received', [
                    'coordinates' => $request->only($required),
                ]);

                return null;
            }
        }

        return $this->filterBuilder()->mapBoundaryFilter($criteria);
    }

    private function categoriesForAttendanceType(int $attendanceTypeId)
    {
        return Category::withCount('events')->where(function ($query) use ($attendanceTypeId) {
            $query->whereJsonContains('applicable_attendance_types', $attendanceTypeId)
                ->orWhereNull('applicable_attendance_types'); // categories with no restriction apply to both
        })->get();
    }

    private function filterBuilder(): EventSearchFilterBuilder
    {
        return app(EventSearchFilterBuilder::class);
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
        // Delegated so there is exactly one implementation of "attach these
        // filters to this query", shared with the saved-search notifier. The
        // geo gate is passed explicitly rather than derived, because which of
        // the two conditions applies is a property of the calling endpoint
        // (see this method's docblock above), not of the search itself.
        return $this->filterBuilder()->applyToQuery(
            $query,
            array_merge($locationFilters, $searchFilters, ['boundaryFilter' => $boundaryFilter]),
            $this->criteriaFromRequest($request),
            $applyGeoFilter,
        );
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
