<?php

namespace App\Actions\Search;

use Elastic\ScoutDriverPlus\Support\Query;

/**
 * "Does this event match these search criteria" — the single definition,
 * used by BOTH the live search page (ListingsController, which adapts the
 * request into the criteria array below) and the saved-search "notify me
 * about new events" command (NotifySavedSearchMatchesCommand, which passes a
 * saved search's stored `criteria` column directly).
 *
 * It was originally a deliberate hand-maintained copy of ListingsController's
 * filter methods, on the reasoning that the controller was live, untested,
 * and too risky to refactor. Both halves of that turned out to be wrong:
 * ListingsFilterTest already covered the controller's filter construction
 * through reflection, and the two copies had ALREADY drifted — this class
 * gated coordinates on isset() while the controller used truthiness, so a
 * coordinate of exactly 0 built a geo filter here and silently didn't there.
 * Unified 2026-08-26.
 *
 * Deliberately takes a plain array, not an Illuminate\Http\Request — a saved
 * search has no request to hand it, and keeping requests out is what lets one
 * implementation serve both callers. Everything request-shaped stays in the
 * controller: resolving slugs to ids, splitting comma-separated lists,
 * dropping JavaScript's 'NaN', logging junk coordinates, and building the
 * category/tag lists the search UI renders. Category, tag and remote-location
 * values must arrive here already resolved to integer ids.
 *
 * Two-step API (buildFilters() then applyToQuery()), not one combined
 * build(): ListingsController's max-price aggregation runs the same query
 * MINUS the price filter, since aggregating a maximum over an already
 * price-filtered query is self-referential and permanently caps the slider at
 * whatever was last searched. Keeping price separable is what makes that
 * possible.
 */
class EventSearchFilterBuilder
{
    /**
     * Builds every individual filter piece, unattached to any query — apply
     * them with applyToQuery() below. Returns only whichever keys are
     * actually applicable to these criteria (a $searchType of 'atHome' never
     * returns 'geoFilter', for instance) — callers should null-coalesce.
     *
     * @param  array{
     *     searchType?: ?string,
     *     lat?: float|null,
     *     lng?: float|null,
     *     live?: bool,
     *     NElat?: float|null, NElng?: float|null, SWlat?: float|null, SWlng?: float|null,
     *     categoryIds?: int[],
     *     tagIds?: int[],
     *     remoteLocationId?: int|null,
     *     priceMin?: float|null,
     *     priceMax?: float|null,
     *     start?: string|null,
     *     end?: string|null,
     * } $criteria
     */
    public function buildFilters(array $criteria): array
    {
        return array_merge(
            $this->locationFilter($criteria),
            $this->searchFilters($criteria),
            ['boundaryFilter' => $this->mapBoundaryFilter($criteria)],
        );
    }

    /**
     * Applies every filter EXCEPT price to $query, mirroring
     * ListingsController::applyNonPriceFilters() exactly (including its geo
     * condition: only in-person searches with `live` on apply a geo filter
     * at all, using the map's exact bounding box instead of the city-radius
     * circle when live). Price is deliberately excluded — add it yourself
     * (`$filters['prices'] ?? null`) when the caller wants it, same as
     * ListingsController's own index()/apiIndex() do after calling this.
     */
    public function applyToQuery($query, array $filters, array $criteria, ?bool $forceGeoFilter = null)
    {
        if ($filters['attendanceType'] ?? null) {
            $query->filter($filters['attendanceType']);
        }

        if ($filters['categories'] ?? null) {
            $query->filter($filters['categories']);
        }

        if ($filters['tags'] ?? null) {
            $query->filter($filters['tags']);
        }

        if ($filters['remoteLocation'] ?? null) {
            $query->filter($filters['remoteLocation']);
        }

        // Mirrors ListingsController::apiIndex()'s $applyGeoFilter condition,
        // not index()'s narrower one — apiIndex() is what actually returns
        // the filtered results a user sees (pagination, the live map,
        // infinite scroll all go through it), so it's the one that
        // represents "what does this search actually show", which is what a
        // replayed saved search should match. That condition also geo-filters
        // an empty/'null' searchType, not just 'inPerson'.
        //
        // array_key_exists, not truthiness, for the live-param half — mirrors
        // `isset($request->live)` exactly: ANY live value (including the
        // string 'false') means geo-filter; only the specific value decides
        // WHICH geo filter (see below). A saved search's normalized criteria
        // always carries a real `live` key (NormalizeSavedSearchCriteriaAction
        // casts it, never omits it), so for this caller that half of the gate
        // is effectively always true — intentional: a stored search with
        // coordinates should always geo-filter when replayed, the same as it
        // did live.
        // $forceGeoFilter lets a caller state the gate outright instead of
        // having it derived. ListingsController needs that: its index() and
        // apiIndex() deliberately gate geo on different conditions (see
        // applyNonPriceFilters' docblock), which is a property of the two
        // endpoints, not of the criteria. Left null, the derivation below
        // runs — the saved-search notifier has no endpoint to speak of.
        $searchType = $criteria['searchType'] ?? null;
        $applyGeoFilter = $forceGeoFilter ?? (
            ($searchType === 'inPerson' || ! $searchType || $searchType === 'null')
            && array_key_exists('live', $criteria)
        );
        if ($applyGeoFilter) {
            $geoFilter = ($criteria['live'] ?? false) ? ($filters['boundaryFilter'] ?? null) : ($filters['geoFilter'] ?? null);
            if ($geoFilter !== null) {
                $query->filter($geoFilter);
            }
        }

        if ($filters['dates'] ?? null) {
            $query->filter($filters['dates']);
        }

        return $query;
    }

    /**
     * Mirrors ListingsController::buildLocationFilter() — same 3-way branch
     * on searchType, same hardcoded attendance-type ids (1 = in-person,
     * 2 = remote — "should be" per that method's own comment, unchanged
     * here), same 40km radius. Returns only the filter-relevant keys
     * (attendanceType, geoFilter) — the UI-only category-picker lists that
     * method also returns stay out of this class entirely.
     */
    public function locationFilter(array $criteria): array
    {
        $searchType = $criteria['searchType'] ?? null;

        if (! $searchType || $searchType === 'null') {
            return ['geoFilter' => $this->geoDistanceFilter($criteria)];
        }

        if ($searchType === 'inPerson') {
            return [
                'attendanceType' => Query::term()->field('attendance_type_id')->value(1),
                'geoFilter' => $this->geoDistanceFilter($criteria),
            ];
        }

        if ($searchType === 'atHome') {
            return [
                'attendanceType' => Query::term()->field('attendance_type_id')->value(2),
            ];
        }

        return [];
    }

    /**
     * Same 40km geoDistance filter both branches of buildLocationFilter
     * build inline above — extracted only within this class.
     *
     * Uses isset(), not truthiness — ListingsController's own equivalent
     * checks `$request->lat && $request->lng`, under which a real
     * coordinate of exactly 0 (equator or prime meridian) would be silently
     * dropped. That's a pre-existing latent bug there (unrelated to this
     * feature, not fixed here), not something worth carrying into new code.
     */
    private function geoDistanceFilter(array $criteria)
    {
        $lat = $criteria['lat'] ?? null;
        $lng = $criteria['lng'] ?? null;

        if (! isset($lat) || ! isset($lng) || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return Query::geoDistance()
            ->field('location_latlon')
            ->distance('40km')
            ->lat((float) $lat)
            ->lon((float) $lng);
    }

    /**
     * Mirrors ListingsController::buildSearchFilters() — same any-of
     * (terms) semantics for categories/tags, same "at least one priceranges
     * entry in range" semantics for price, same nested-shows-date-range-OR-
     * always-available semantics for dates. Slug resolution and the
     * searchedCategories/searchedTags/searchedRemoteLocation UI lists that
     * method also builds are NOT here — this expects ids already resolved
     * (see the class docblock).
     */
    public function searchFilters(array $criteria): array
    {
        $filters = [];

        $categoryIds = $criteria['categoryIds'] ?? [];
        if (! empty($categoryIds)) {
            $filters['categories'] = Query::terms()->field('category_id')->values($categoryIds);
        }

        $tagIds = $criteria['tagIds'] ?? [];
        if (! empty($tagIds)) {
            $filters['tags'] = Query::bool()->must(
                Query::terms()->field('genres.genre_id')->values($tagIds)
            );
        }

        // Gated on searchType === 'atHome' — same reasoning as
        // ListingsController's own copy: a stray remoteLocationId on an
        // in-person search must not filter it down to zero results.
        $remoteLocationId = $criteria['remoteLocationId'] ?? null;
        if ($remoteLocationId && ($criteria['searchType'] ?? null) === 'atHome') {
            $filters['remoteLocation'] = Query::terms()->field('remote_location_ids')->values([$remoteLocationId]);
        }

        $priceMin = $criteria['priceMin'] ?? null;
        $priceMax = $criteria['priceMax'] ?? null;
        if ($priceMin !== null || $priceMax !== null) {
            $priceFilter = Query::range()->field('priceranges.price')->gte($priceMin ?? 0);
            if ($priceMax !== null) {
                $priceFilter->lte($priceMax);
            }
            $filters['prices'] = $priceFilter;
        }

        $start = $criteria['start'] ?? null;
        $end = $criteria['end'] ?? null;
        if ($start && $end) {
            $startOfDay = \Carbon\Carbon::parse($start)->startOfDay()->format('Y-m-d H:i:s');
            $endOfDay = \Carbon\Carbon::parse($end)->endOfDay()->format('Y-m-d H:i:s');

            $dateFilter = Query::bool();
            $dateFilter->should(
                Query::nested()->path('shows')->query(
                    Query::range()->field('shows.date')->gte($startOfDay)->lte($endOfDay)
                )
            );
            $dateFilter->should(Query::term()->field('showtype')->value('a'));
            $dateFilter->minimumShouldMatch(1);

            $filters['dates'] = $dateFilter;
        }

        return $filters;
    }

    /**
     * Mirrors ListingsController::buildMapBoundaryFilter() — same
     * geo_bounding_box shape. `live` here is the already-normalized boolean
     * (NormalizeSavedSearchCriteriaAction casts it), not the request's raw
     * string 'true' ListingsController's own request-adaptation step
     * converts before calling this.
     */
    public function mapBoundaryFilter(array $criteria)
    {
        if (! ($criteria['live'] ?? false)) {
            return null;
        }

        $coords = ['NElat', 'NElng', 'SWlat', 'SWlng'];
        foreach ($coords as $coord) {
            if (! isset($criteria[$coord]) || ! is_numeric($criteria[$coord])) {
                return null;
            }
        }

        return Query::bool()->filterRaw([
            'geo_bounding_box' => [
                'location_latlon' => [
                    'top_right' => [
                        'lat' => (float) $criteria['NElat'],
                        'lon' => (float) $criteria['NElng'],
                    ],
                    'bottom_left' => [
                        'lat' => (float) $criteria['SWlat'],
                        'lon' => (float) $criteria['SWlng'],
                    ],
                ],
            ],
        ]);
    }
}
