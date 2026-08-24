<?php

use App\Actions\Search\EventSearchFilterBuilder;

/**
 * Structural assertions on the compiled Elasticsearch query DSL
 * (QueryBuilderInterface::buildQuery(): array) — this app's test config runs
 * SCOUT_DRIVER=null, so there's no live Elasticsearch to actually execute
 * against and confirm real matches. What CAN be verified without one is that
 * this class compiles the exact query shapes ListingsController's own
 * (deliberately parallel, not wired together — see EventSearchFilterBuilder's
 * own docblock for why) filter construction does — see
 * tests/Feature/Search/ListingsFilterTest.php for that side, exercised
 * through the controller's own methods.
 *
 * Two real discrepancies from ListingsController surfaced only by writing
 * these tests, not by reading the code: `live` needs isset()-style gating
 * (any value present, not just true) exactly like the controller's own
 * `isset($request->live)`, and the geo-filter condition to mirror is
 * apiIndex()'s broader one (also covers empty/'null' searchType), not
 * index()'s narrower one — apiIndex() is what actually returns the results a
 * user sees. Both are now correct; see the git history on this file if
 * either regresses.
 */
function builder(): EventSearchFilterBuilder
{
    return new EventSearchFilterBuilder;
}

function combinedQuery(array $criteria): array
{
    $b = builder();
    $filters = $b->buildFilters($criteria);
    $query = \Elastic\ScoutDriverPlus\Support\Query::bool();
    $b->applyToQuery($query, $filters, $criteria);
    if ($filters['prices'] ?? null) {
        $query->filter($filters['prices']);
    }

    return $query->buildQuery();
}

test('in-person search applies the attendance-type-1 filter and a 40km geo radius', function () {
    $result = combinedQuery(['searchType' => 'inPerson', 'live' => false, 'lat' => 34.05, 'lng' => -118.24]);

    expect($result['bool']['filter'])->toContain(['term' => ['attendance_type_id' => ['value' => 1]]]);
    expect($result['bool']['filter'])->toContain([
        'geo_distance' => [
            'location_latlon' => ['lat' => 34.05, 'lon' => -118.24],
            'distance' => '40km',
        ],
    ]);
});

test('at-home search applies the attendance-type-2 filter and no geo filter', function () {
    $result = combinedQuery(['searchType' => 'atHome']);

    expect($result['bool']['filter'])->toContain(['term' => ['attendance_type_id' => ['value' => 2]]]);
    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
});

test('a null/absent searchType applies no attendance-type filter but still applies geo radius', function () {
    // apiIndex()'s $applyGeoFilter condition (which this mirrors — see
    // EventSearchFilterBuilder::applyToQuery()) geo-filters an empty/null
    // searchType too, not just inPerson.
    $result = combinedQuery(['searchType' => null, 'live' => false, 'lat' => 40.7, 'lng' => -74.0]);

    expect(collect($result['bool']['filter'])->pluck('term.attendance_type_id')->filter())->toBeEmpty();
    expect($result['bool']['filter'])->toContain([
        'geo_distance' => [
            'location_latlon' => ['lat' => 40.7, 'lon' => -74.0],
            'distance' => '40km',
        ],
    ]);
});

test('a coordinate of exactly 0 (equator/prime meridian) still applies a geo filter', function () {
    // Regression guard for the isset()-not-truthiness fix documented on
    // EventSearchFilterBuilder::geoDistanceFilter() — ListingsController's
    // own `$request->lat && $request->lng` check would silently drop this.
    $result = combinedQuery(['searchType' => 'inPerson', 'live' => false, 'lat' => 0, 'lng' => 0]);

    expect($result['bool']['filter'])->toContain([
        'geo_distance' => [
            'location_latlon' => ['lat' => 0.0, 'lon' => 0.0],
            'distance' => '40km',
        ],
    ]);
});

test('missing lat/lng applies no geo filter and does not error', function () {
    $result = combinedQuery(['searchType' => 'inPerson']);

    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
});

test('categories filter is an any-of terms clause', function () {
    $result = combinedQuery(['categoryIds' => [5, 6, 7]]);

    expect($result['bool']['filter'])->toContain(['terms' => ['category_id' => [5, 6, 7]]]);
});

test('no categories means no category filter at all', function () {
    $filters = builder()->buildFilters(['categoryIds' => []]);

    expect($filters['categories'] ?? null)->toBeNull();
});

test('tags filter is a must-wrapped any-of terms clause on genres.genre_id', function () {
    $result = combinedQuery(['tagIds' => [9, 10]]);

    expect($result['bool']['filter'])->toContain([
        'bool' => ['must' => [['terms' => ['genres.genre_id' => [9, 10]]]]],
    ]);
});

test('remote-location filter only applies when searchType is atHome', function () {
    $atHome = combinedQuery(['searchType' => 'atHome', 'remoteLocationId' => 42]);
    expect($atHome['bool']['filter'])->toContain(['terms' => ['remote_location_ids' => [42]]]);

    // A stray remoteLocationId left over from switching tabs must not
    // silently filter an in-person search down to zero results.
    $inPerson = combinedQuery(['searchType' => 'inPerson', 'remoteLocationId' => 42]);
    expect(collect($inPerson['bool']['filter'])->pluck('terms.remote_location_ids')->filter())->toBeEmpty();
});

test('price with only a minimum is an unbounded-above range filter', function () {
    $result = combinedQuery(['priceMin' => 25.0]);

    expect($result['bool']['filter'])->toContain(['range' => ['priceranges.price' => ['gte' => 25.0]]]);
});

test('price with both bounds sets gte and lte', function () {
    $result = combinedQuery(['priceMin' => 10.0, 'priceMax' => 50.0]);

    expect($result['bool']['filter'])->toContain(['range' => ['priceranges.price' => ['gte' => 10.0, 'lte' => 50.0]]]);
});

test('price with only a maximum still floors the minimum at 0, not null', function () {
    $result = combinedQuery(['priceMax' => 50.0]);

    expect($result['bool']['filter'])->toContain(['range' => ['priceranges.price' => ['gte' => 0, 'lte' => 50.0]]]);
});

test('no price criteria means no price filter', function () {
    $filters = builder()->buildFilters([]);
    expect($filters['prices'] ?? null)->toBeNull();
});

test('dates filter matches a nested show in the inclusive full-day range OR an always-available event', function () {
    $result = combinedQuery(['start' => '2026-09-01', 'end' => '2026-09-05']);

    $dateClause = collect($result['bool']['filter'])->first(fn ($f) => isset($f['bool']['should']));
    expect($dateClause)->not->toBeNull();
    expect($dateClause['bool']['minimum_should_match'])->toBe(1);
    expect($dateClause['bool']['should'])->toContain(['term' => ['showtype' => ['value' => 'a']]]);

    $nested = collect($dateClause['bool']['should'])->first(fn ($f) => isset($f['nested']));
    expect($nested['nested']['path'])->toBe('shows');
    expect($nested['nested']['query']['range']['shows.date'])->toBe([
        'gte' => '2026-09-01 00:00:00',
        'lte' => '2026-09-05 23:59:59',
    ]);
});

test('no start/end means no date filter', function () {
    $filters = builder()->buildFilters(['start' => null, 'end' => null]);
    expect($filters['dates'] ?? null)->toBeNull();
});

test('a live in-person search uses the exact map bounding box instead of the city radius', function () {
    $criteria = [
        'searchType' => 'inPerson',
        'live' => true,
        'lat' => 34.05, 'lng' => -118.24, // present but must be ignored in favor of the box
        'NElat' => 34.2, 'NElng' => -118.1, 'SWlat' => 33.9, 'SWlng' => -118.4,
    ];
    $result = combinedQuery($criteria);

    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
    $box = collect($result['bool']['filter'])->first(fn ($f) => isset($f['bool']['filter']['geo_bounding_box']));
    expect($box['bool']['filter']['geo_bounding_box']['location_latlon'])->toBe([
        'top_right' => ['lat' => 34.2, 'lon' => -118.1],
        'bottom_left' => ['lat' => 33.9, 'lon' => -118.4],
    ]);
});

test('live=true with an atHome searchType applies neither geo filter', function () {
    // atHome is excluded from apiIndex()'s $applyGeoFilter condition even
    // though it's broader than index()'s — only inPerson/empty/'null' count.
    // A live=true left over from switching tabs must not geo-filter an
    // atHome search at all.
    $criteria = [
        'searchType' => 'atHome',
        'live' => true,
        'NElat' => 34.2, 'NElng' => -118.1, 'SWlat' => 33.9, 'SWlng' => -118.4,
    ];
    $result = combinedQuery($criteria);

    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
    expect(collect($result['bool']['filter'])->filter(fn ($f) => isset($f['bool']['filter']['geo_bounding_box'])))->toBeEmpty();
});

test('incomplete map bounds fall back to no geo filter rather than erroring', function () {
    $criteria = ['searchType' => 'inPerson', 'live' => true, 'NElat' => 34.2]; // missing the other 3
    $filters = builder()->buildFilters($criteria);

    expect($filters['boundaryFilter'])->toBeNull();
});

test('a fully-loaded criteria set combines every applicable filter without dropping any', function () {
    $result = combinedQuery([
        'searchType' => 'inPerson',
        'live' => false,
        'lat' => 34.05, 'lng' => -118.24,
        'categoryIds' => [1],
        'tagIds' => [2],
        'priceMin' => 10.0, 'priceMax' => 100.0,
        'start' => '2026-09-01', 'end' => '2026-09-05',
    ]);

    // attendanceType + geo + categories + tags + dates + price = 6 clauses.
    expect($result['bool']['filter'])->toHaveCount(6);
});
