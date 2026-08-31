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

test('an out-of-range longitude applies no geo filter rather than a query ES rejects', function () {
    // The exact value from EI-LARAVEL-11. It is perfectly numeric, so the old
    // is_numeric()-only guard passed it straight through to Elasticsearch,
    // which answered `[geo_distance] center point longitude is invalid` — a
    // 400 that fails every shard and 500s the search page.
    $result = combinedQuery(['searchType' => 'inPerson', 'live' => false, 'lat' => 34.05, 'lng' => 3.14326023E8]);

    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
});

test('an out-of-range latitude applies no geo filter', function () {
    $result = combinedQuery(['searchType' => 'inPerson', 'live' => false, 'lat' => 91, 'lng' => -118.24]);

    expect(collect($result['bool']['filter'])->pluck('geo_distance')->filter())->toBeEmpty();
});

test('the extremes of the valid range still apply a geo filter', function () {
    // -90/180 are legal coordinates, not junk; the range check is inclusive.
    $result = combinedQuery(['searchType' => 'inPerson', 'live' => false, 'lat' => -90, 'lng' => 180]);

    expect($result['bool']['filter'])->toContain([
        'geo_distance' => [
            'location_latlon' => ['lat' => -90.0, 'lon' => 180.0],
            'distance' => '40km',
        ],
    ]);
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

test('an out-of-range map bound corner falls back to no boundary filter', function () {
    // geo_bounding_box validates its corners exactly like geo_distance
    // validates its centre, so this is the same 400 as EI-LARAVEL-11.
    $criteria = [
        'searchType' => 'inPerson', 'live' => true,
        'NElat' => 34.2, 'NElng' => -118.1, 'SWlat' => 33.9, 'SWlng' => -3.14326023E8,
    ];

    expect(builder()->buildFilters($criteria)['boundaryFilter'])->toBeNull();
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

/**
 * EI-LARAVEL-10: a crawler requested `?start=2026-08-26 00%3A00%3A00` — a
 * date whose colons had been encoded one time too many — and Carbon::parse
 * threw straight out of searchFilters(). That is a 500 on the busiest page on
 * the site, and, because this builder is shared, a dead
 * NotifySavedSearchMatchesCommand for any saved search holding a bad date.
 */
test('an unparseable date drops the date filter instead of throwing', function (string $start, string $end) {
    $filters = builder()->buildFilters(['start' => $start, 'end' => $end]);

    expect($filters['dates'] ?? null)->toBeNull();
})->with([
    'double-encoded colons (the reported crash)' => ['2026-08-26 00%3A00%3A00', '2026-08-27 00%3A00%3A00'],
    'not a date at all' => ['banana', 'kumquat'],
    'JavaScript NaN' => ['NaN', 'NaN'],
    'html injected into the param' => ['<script>', '<script>'],
]);

test('one bad end of the range drops the whole filter, never half a range', function () {
    // Half a range would silently widen the search rather than narrow it.
    expect(builder()->buildFilters(['start' => '2026-09-01', 'end' => 'garbage'])['dates'] ?? null)->toBeNull();
    expect(builder()->buildFilters(['start' => 'garbage', 'end' => '2026-09-05'])['dates'] ?? null)->toBeNull();
});

test('a valid range still builds the date filter', function () {
    // Guards against the fix above swallowing good input too.
    expect(builder()->buildFilters(['start' => '2026-09-01', 'end' => '2026-09-05'])['dates'] ?? null)->not->toBeNull();
});

test('parseSearchDate returns null for junk and a Carbon for a real date', function () {
    expect(EventSearchFilterBuilder::parseSearchDate('2026-08-26 00%3A00%3A00'))->toBeNull();
    expect(EventSearchFilterBuilder::parseSearchDate(null))->toBeNull();
    expect(EventSearchFilterBuilder::parseSearchDate(''))->toBeNull();
    expect(EventSearchFilterBuilder::parseSearchDate('2026-09-01'))->toBeInstanceOf(\Carbon\Carbon::class);
});
