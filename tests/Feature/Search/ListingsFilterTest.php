<?php

use App\Actions\Search\SearchActions;
use App\Http\Controllers\Search\ListingsController;
use App\Models\Category;
use App\Models\Events\RemoteLocation;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// These tests exercise ONLY the pure filter-construction helpers on
// ListingsController (buildLocationFilter / buildSearchFilters /
// buildMapBoundaryFilter) plus SearchActions::nameSearch/eventSearch query
// shaping. Elasticsearch is not available in the test env (SCOUT_DRIVER=null),
// so none of these tests touch index()/apiIndex() execution paths or
// Event::searchQuery(). The build* helpers are protected, so we invoke them via
// reflection against a fresh controller instance.

// ----- helpers -----

/**
 * Invoke a protected/private method on a fresh ListingsController, passing
 * whatever positional args that method needs.
 */
function invokeControllerMethod(string $method, array $args)
{
    $controller = new ListingsController;

    $ref = new ReflectionMethod(ListingsController::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($controller, ...$args);
}

/**
 * Invoke a protected build* method with a Request carrying the given query
 * parameters — every build* method takes just that one argument.
 */
function callBuilder(string $method, array $query)
{
    return invokeControllerMethod($method, [Request::create('/search', 'GET', $query)]);
}

beforeEach(function () {
    // The buildLocationFilter geo branch logs a warning on bad coords; keep the
    // log facade fake so we never write to disk and can assert it fired.
    Log::spy();
});

// ============================================================
// buildLocationFilter — searchType handling
// ============================================================

test('buildLocationFilter with no searchType returns only a null geoFilter and no attendanceType', function () {
    $result = callBuilder('buildLocationFilter', []);

    expect($result)->toHaveKey('geoFilter');
    expect($result['geoFilter'])->toBeNull();
    expect($result)->not->toHaveKey('attendanceType');
});

test("buildLocationFilter treats the literal string 'null' searchType like no searchType", function () {
    $result = callBuilder('buildLocationFilter', ['searchType' => 'null']);

    expect($result)->toHaveKey('geoFilter');
    expect($result['geoFilter'])->toBeNull();
    expect($result)->not->toHaveKey('attendanceType');
});

test('buildLocationFilter with no searchType builds a 40km geoDistance filter from numeric lat/lng', function () {
    $result = callBuilder('buildLocationFilter', [
        'lat' => '40.7128',
        'lng' => '-74.0060',
    ]);

    expect($result['geoFilter'])->not->toBeNull();

    $built = $result['geoFilter']->buildQuery();
    expect($built)->toHaveKey('geo_distance');
    expect($built['geo_distance']['location_latlon']['lat'])->toBe(40.7128);
    expect($built['geo_distance']['location_latlon']['lon'])->toBe(-74.0060);
    expect($built['geo_distance']['distance'])->toBe('40km');
});

test('buildLocationFilter ignores non-numeric lat/lng and logs a warning (no geoFilter)', function () {
    // note: lat/lng of 'NaN' are truthy strings, so they pass the `$request->lat
    // && $request->lng` guard but fail is_numeric(), hitting the warning branch.
    $result = callBuilder('buildLocationFilter', [
        'lat' => 'NaN',
        'lng' => 'NaN',
        'city' => 'Nowhere',
    ]);

    expect($result['geoFilter'])->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Invalid lat/lng'))->once();
});

test('buildLocationFilter ignores an out-of-range lng and logs a warning (no geoFilter)', function () {
    // EI-LARAVEL-11: numeric but not a coordinate. Elasticsearch rejects the
    // whole query with a 400 rather than matching nothing, so this has to be
    // dropped here like any other junk coordinate.
    $result = callBuilder('buildLocationFilter', [
        'lat' => '34.05',
        'lng' => '314326023',
        'city' => 'Nowhere',
    ]);

    expect($result['geoFilter'])->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Invalid lat/lng'))->once();
});

test('buildLocationFilter still accepts the extremes of the valid range', function () {
    $result = callBuilder('buildLocationFilter', ['lat' => '90', 'lng' => '-180']);

    $built = $result['geoFilter']->buildQuery();
    expect($built['geo_distance']['location_latlon'])->toBe(['lat' => 90.0, 'lon' => -180.0]);
    Log::shouldNotHaveReceived('warning');
});

test('buildLocationFilter ignores missing lng even when lat is present', function () {
    // Only lat present -> the `$request->lat && $request->lng` guard short-circuits,
    // so no geoFilter is built and nothing is logged.
    $result = callBuilder('buildLocationFilter', ['lat' => '40.7']);

    expect($result['geoFilter'])->toBeNull();
    Log::shouldNotHaveReceived('warning');
});

// ----- inPerson branch -----

test('buildLocationFilter inPerson returns attendance_type_id term of 1', function () {
    $result = callBuilder('buildLocationFilter', ['searchType' => 'inPerson']);

    expect($result)->toHaveKeys(['attendanceType', 'inPersonCategories', 'geoFilter']);
    expect($result['geoFilter'])->toBeNull();

    $built = $result['attendanceType']->buildQuery();
    expect($built['term']['attendance_type_id']['value'])->toBe(1);
});

test('buildLocationFilter inPerson builds a geoFilter when numeric lat/lng given', function () {
    $result = callBuilder('buildLocationFilter', [
        'searchType' => 'inPerson',
        'lat' => '34.05',
        'lng' => '-118.24',
    ]);

    expect($result['geoFilter'])->not->toBeNull();
    $built = $result['geoFilter']->buildQuery();
    expect($built['geo_distance']['location_latlon']['lat'])->toBe(34.05);
    expect($built['geo_distance']['location_latlon']['lon'])->toBe(-118.24);
});

test('buildLocationFilter inPerson with non-numeric lat/lng yields null geoFilter and logs', function () {
    $result = callBuilder('buildLocationFilter', [
        'searchType' => 'inPerson',
        'lat' => 'foo',
        'lng' => 'bar',
    ]);

    expect($result['geoFilter'])->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

test('buildLocationFilter inPerson includes categories with matching or null applicable_attendance_types', function () {
    $matching = Category::factory()->create(['applicable_attendance_types' => [1]]);
    $unrestricted = Category::factory()->create(['applicable_attendance_types' => null]);
    $remoteOnly = Category::factory()->create(['applicable_attendance_types' => [2]]);

    $result = callBuilder('buildLocationFilter', ['searchType' => 'inPerson']);

    $ids = $result['inPersonCategories']->pluck('id')->all();
    expect($ids)->toContain($matching->id);
    expect($ids)->toContain($unrestricted->id);
    expect($ids)->not->toContain($remoteOnly->id);
});

// ----- atHome branch -----

test('buildLocationFilter atHome returns attendance_type_id term of 2 and no geoFilter key', function () {
    $result = callBuilder('buildLocationFilter', ['searchType' => 'atHome']);

    expect($result)->toHaveKeys(['attendanceType', 'remoteCategories']);
    // note: the atHome branch never sets a geoFilter key (remote events have no
    // geo distance filtering), unlike the default and inPerson branches.
    expect($result)->not->toHaveKey('geoFilter');

    $built = $result['attendanceType']->buildQuery();
    expect($built['term']['attendance_type_id']['value'])->toBe(2);
});

test('buildLocationFilter atHome includes categories with matching or null applicable_attendance_types', function () {
    $matching = Category::factory()->create(['applicable_attendance_types' => [2]]);
    $unrestricted = Category::factory()->create(['applicable_attendance_types' => null]);
    $inPersonOnly = Category::factory()->create(['applicable_attendance_types' => [1]]);

    $result = callBuilder('buildLocationFilter', ['searchType' => 'atHome']);

    $ids = $result['remoteCategories']->pluck('id')->all();
    expect($ids)->toContain($matching->id);
    expect($ids)->toContain($unrestricted->id);
    expect($ids)->not->toContain($inPersonOnly->id);
});

test('buildLocationFilter returns an empty array for an unrecognized searchType', function () {
    // note: any non-null searchType that is not inPerson/atHome falls through all
    // three branches and returns []. No geoFilter key at all.
    $result = callBuilder('buildLocationFilter', ['searchType' => 'somethingElse']);

    expect($result)->toBe([]);
});

// ============================================================
// buildSearchFilters — categories
// ============================================================

test('buildSearchFilters with no params returns an empty filter set', function () {
    $result = callBuilder('buildSearchFilters', []);

    expect($result)->toBe([]);
});

test('buildSearchFilters converts a category slug to its id and builds a terms filter', function () {
    $category = Category::factory()->create();

    $result = callBuilder('buildSearchFilters', ['category' => $category->slug]);

    expect($result)->toHaveKeys(['searchedCategories', 'categories']);
    $built = $result['categories']->buildQuery();
    expect($built['terms']['category_id'])->toBe([$category->id]);
});

test('buildSearchFilters accepts a numeric category id directly', function () {
    $category = Category::factory()->create();

    $result = callBuilder('buildSearchFilters', ['category' => (string) $category->id]);

    $built = $result['categories']->buildQuery();
    expect($built['terms']['category_id'])->toBe([$category->id]);
});

test('buildSearchFilters handles a comma-separated mix of slug and numeric id', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create();

    $result = callBuilder('buildSearchFilters', ['category' => $a->slug.','.$b->id]);

    $built = $result['categories']->buildQuery();
    expect($built['terms']['category_id'])->toBe([$a->id, $b->id]);
});

test('buildSearchFilters handles an array of categories', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create();

    $result = callBuilder('buildSearchFilters', ['category' => [$a->slug, (string) $b->id]]);

    $built = $result['categories']->buildQuery();
    expect($built['terms']['category_id'])->toBe([$a->id, $b->id]);
});

test("buildSearchFilters skips the literal 'NaN' category token", function () {
    $category = Category::factory()->create();

    $result = callBuilder('buildSearchFilters', ['category' => 'NaN,'.$category->slug]);

    $built = $result['categories']->buildQuery();
    expect($built['terms']['category_id'])->toBe([$category->id]);
});

test('buildSearchFilters drops an unknown category slug, producing no category filter', function () {
    // note: a slug that resolves to no Category yields an empty $categoryIds, so
    // the whole category filter block is skipped (no 'categories' key at all).
    $result = callBuilder('buildSearchFilters', ['category' => 'does-not-exist']);

    expect($result)->not->toHaveKey('categories');
    expect($result)->not->toHaveKey('searchedCategories');
});

test('buildSearchFilters with only a NaN category produces no category filter', function () {
    $result = callBuilder('buildSearchFilters', ['category' => 'NaN']);

    expect($result)->not->toHaveKey('categories');
});

// ============================================================
// buildSearchFilters — tags (genres)
// ============================================================

test('buildSearchFilters converts a tag slug to its genre id inside a bool/must terms filter', function () {
    $genre = Genre::factory()->create();

    $result = callBuilder('buildSearchFilters', ['tag' => $genre->slug]);

    expect($result)->toHaveKeys(['searchedTags', 'tags']);
    $built = $result['tags']->buildQuery();
    // Shape: { bool: { must: [ { terms: { 'genres.genre_id': [id] } } ] } }
    expect($built['bool']['must'][0]['terms']['genres.genre_id'])->toBe([$genre->id]);
});

test('buildSearchFilters accepts a comma-separated list of tag ids and slugs', function () {
    $a = Genre::factory()->create();
    $b = Genre::factory()->create();

    $result = callBuilder('buildSearchFilters', ['tag' => (string) $a->id.','.$b->slug]);

    $built = $result['tags']->buildQuery();
    expect($built['bool']['must'][0]['terms']['genres.genre_id'])->toBe([$a->id, $b->id]);
});

test("buildSearchFilters skips the literal 'NaN' tag token", function () {
    $genre = Genre::factory()->create();

    $result = callBuilder('buildSearchFilters', ['tag' => 'NaN,'.$genre->slug]);

    $built = $result['tags']->buildQuery();
    expect($built['bool']['must'][0]['terms']['genres.genre_id'])->toBe([$genre->id]);
});

test('buildSearchFilters drops an unknown tag slug, producing no tag filter', function () {
    $result = callBuilder('buildSearchFilters', ['tag' => 'no-such-genre']);

    expect($result)->not->toHaveKey('tags');
    expect($result)->not->toHaveKey('searchedTags');
});

// ============================================================
// buildSearchFilters — remoteLocation (At Home search-by-type)
// ============================================================

test('buildSearchFilters converts a remote location slug to a terms filter by id', function () {
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => User::factory()->create()->id]);

    $result = callBuilder('buildSearchFilters', ['remoteLocation' => $zoom->slug, 'searchType' => 'atHome']);

    expect($result)->toHaveKeys(['searchedRemoteLocation', 'remoteLocation']);
    expect($result['searchedRemoteLocation']->id)->toBe($zoom->id);
    $built = $result['remoteLocation']->buildQuery();
    expect($built['terms']['remote_location_ids'])->toBe([$zoom->id]);
});

test('buildSearchFilters accepts a numeric remote location id directly', function () {
    $telephone = RemoteLocation::create(['name' => 'Telephone', 'slug' => 'telephone', 'admin' => true, 'rank' => 0, 'user_id' => User::factory()->create()->id]);

    $result = callBuilder('buildSearchFilters', ['remoteLocation' => (string) $telephone->id, 'searchType' => 'atHome']);

    $built = $result['remoteLocation']->buildQuery();
    expect($built['terms']['remote_location_ids'])->toBe([$telephone->id]);
});

test('buildSearchFilters drops an unknown remote location slug, producing no filter', function () {
    $result = callBuilder('buildSearchFilters', ['remoteLocation' => 'does-not-exist', 'searchType' => 'atHome']);

    expect($result)->not->toHaveKey('remoteLocation');
    expect($result)->not->toHaveKey('searchedRemoteLocation');
});

test('buildSearchFilters ignores a remoteLocation param when searchType is not atHome', function () {
    // A stray remoteLocation left over from switching tabs (see
    // nav-search.vue's handleLocationSearch) must not silently filter an
    // in-person search down to zero results — in-person events have no
    // remote_location_ids at all.
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => User::factory()->create()->id]);

    $result = callBuilder('buildSearchFilters', ['remoteLocation' => $zoom->slug, 'searchType' => 'inPerson']);

    expect($result)->not->toHaveKey('remoteLocation');
    expect($result)->not->toHaveKey('searchedRemoteLocation');
});

// ============================================================
// buildSearchFilters — price range
// ============================================================

test('buildSearchFilters builds a price range with a gte lower bound from price0 only', function () {
    $result = callBuilder('buildSearchFilters', ['price0' => '15']);

    expect($result)->toHaveKey('prices');
    $built = $result['prices']->buildQuery();
    expect($built['range']['priceranges.price'])->toHaveKey('gte');
    expect($built['range']['priceranges.price']['gte'])->toBe(15.0);
    // note: with no price1, only the lower bound is added (no upper bound).
    expect($built['range']['priceranges.price'])->not->toHaveKey('lte');
});

test('buildSearchFilters adds both gte and lte when price0 and price1 are set', function () {
    $result = callBuilder('buildSearchFilters', ['price0' => '10', 'price1' => '50']);

    $built = $result['prices']->buildQuery();
    expect($built['range']['priceranges.price']['gte'])->toBe(10.0);
    expect($built['range']['priceranges.price']['lte'])->toBe(50.0);
});

test('buildSearchFilters defaults the lower bound to 0 when only price1 is present', function () {
    // note: the block is entered when EITHER price0 or price1 is present; with
    // only price1, $minPrice defaults to the integer literal 0 (not a float),
    // and an upper bound is still added.
    $result = callBuilder('buildSearchFilters', ['price1' => '30']);

    $built = $result['prices']->buildQuery();
    expect($built['range']['priceranges.price']['gte'])->toBe(0);
    expect($built['range']['priceranges.price']['lte'])->toBe(30.0);
});

test('buildSearchFilters casts non-numeric price1 to 0.0 via (float) cast', function () {
    // note: (float) 'NaN' === 0.0 in PHP, so an upper bound of 0 is added. The
    // helper does no validation on price values; this documents current behavior.
    $result = callBuilder('buildSearchFilters', ['price0' => '5', 'price1' => 'NaN']);

    $built = $result['prices']->buildQuery();
    expect($built['range']['priceranges.price']['gte'])->toBe(5.0);
    expect($built['range']['priceranges.price']['lte'])->toBe(0.0);
});

// ============================================================
// buildSearchFilters — date range
// ============================================================

test('buildSearchFilters builds a should/should date bool when start and end are set', function () {
    $result = callBuilder('buildSearchFilters', [
        'start' => '2026-06-01',
        'end' => '2026-06-30',
    ]);

    expect($result)->toHaveKey('dates');
    $built = $result['dates']->buildQuery();

    // Two should clauses: a nested shows.date range and an always-available term.
    expect($built['bool']['should'])->toHaveCount(2);
    expect($built['bool']['minimum_should_match'])->toBe(1);

    $nestedRange = $built['bool']['should'][0]['nested']['query']['range']['shows.date'];
    expect($nestedRange['gte'])->toBe('2026-06-01 00:00:00');
    // note: the end bound is normalized to endOfDay so same-day-later shows match.
    expect($nestedRange['lte'])->toBe('2026-06-30 23:59:59');
    expect($built['bool']['should'][0]['nested']['path'])->toBe('shows');

    $alwaysTerm = $built['bool']['should'][1]['term']['showtype'];
    expect($alwaysTerm['value'])->toBe('a');
});

test('buildSearchFilters omits the date filter when only start is given', function () {
    $result = callBuilder('buildSearchFilters', ['start' => '2026-06-01']);

    expect($result)->not->toHaveKey('dates');
});

test('buildSearchFilters omits the date filter when only end is given', function () {
    $result = callBuilder('buildSearchFilters', ['end' => '2026-06-30']);

    expect($result)->not->toHaveKey('dates');
});

// ============================================================
// buildSearchFilters — combined
// ============================================================

test('buildSearchFilters can build category, tag, price and date filters together', function () {
    $category = Category::factory()->create();
    $genre = Genre::factory()->create();

    $result = callBuilder('buildSearchFilters', [
        'category' => $category->slug,
        'tag' => $genre->slug,
        'price0' => '0',
        'price1' => '100',
        'start' => '2026-07-01',
        'end' => '2026-07-15',
    ]);

    expect($result)->toHaveKeys(['categories', 'tags', 'prices', 'dates']);
    expect($result['categories']->buildQuery()['terms']['category_id'])->toBe([$category->id]);
    expect($result['tags']->buildQuery()['bool']['must'][0]['terms']['genres.genre_id'])->toBe([$genre->id]);
});

// ============================================================
// buildMapBoundaryFilter
// ============================================================

test('buildMapBoundaryFilter returns null when live is not set', function () {
    $result = callBuilder('buildMapBoundaryFilter', [
        'NElat' => '40.9', 'NElng' => '-73.7', 'SWlat' => '40.4', 'SWlng' => '-74.2',
    ]);

    expect($result)->toBeNull();
});

test("buildMapBoundaryFilter returns null when live is not exactly the string 'true'", function () {
    // note: live must be the literal string 'true'; live=1 / live=on do not count.
    $result = callBuilder('buildMapBoundaryFilter', [
        'live' => '1',
        'NElat' => '40.9', 'NElng' => '-73.7', 'SWlat' => '40.4', 'SWlng' => '-74.2',
    ]);

    expect($result)->toBeNull();
});

test('buildMapBoundaryFilter builds a geo_bounding_box when live=true and all coords are numeric', function () {
    $result = callBuilder('buildMapBoundaryFilter', [
        'live' => 'true',
        'NElat' => '40.9', 'NElng' => '-73.7', 'SWlat' => '40.4', 'SWlng' => '-74.2',
    ]);

    expect($result)->not->toBeNull();
    $built = $result->buildQuery();
    $box = $built['bool']['filter']['geo_bounding_box']['location_latlon'];
    expect($box['top_right'])->toBe(['lat' => 40.9, 'lon' => -73.7]);
    expect($box['bottom_left'])->toBe(['lat' => 40.4, 'lon' => -74.2]);
});

test('buildMapBoundaryFilter returns null and logs when any boundary coord is non-numeric', function () {
    $result = callBuilder('buildMapBoundaryFilter', [
        'live' => 'true',
        'NElat' => '40.9', 'NElng' => 'NaN', 'SWlat' => '40.4', 'SWlng' => '-74.2',
    ]);

    expect($result)->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Invalid geo boundary'))->once();
});

test('buildMapBoundaryFilter returns null and logs when a boundary coord is out of range', function () {
    $result = callBuilder('buildMapBoundaryFilter', [
        'live' => 'true',
        'NElat' => '40.9', 'NElng' => '-73.7', 'SWlat' => '-91', 'SWlng' => '-74.2',
    ]);

    expect($result)->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Invalid geo boundary'))->once();
});

test('buildMapBoundaryFilter returns null and logs when a boundary coord is missing', function () {
    $result = callBuilder('buildMapBoundaryFilter', [
        'live' => 'true',
        'NElat' => '40.9', 'SWlat' => '40.4', 'SWlng' => '-74.2',
    ]);

    expect($result)->toBeNull();
    Log::shouldHaveReceived('warning')->once();
});

// ============================================================
// SearchActions::nameSearch / eventSearch
// These shape pure Query objects (no ES client needed) when keywords present,
// and matchAll() when blank.
// ============================================================

test('nameSearch returns a match_all query when no keywords supplied', function () {
    $query = (new SearchActions)->nameSearch(Request::create('/search', 'GET', []));

    // note: match_all serializes to an empty stdClass, so compare structurally
    // rather than by identity (distinct object instances are never ===).
    $built = $query->buildQuery();
    expect($built)->toHaveKey('match_all');
    expect((array) $built['match_all'])->toBe([]);
});

test('nameSearch builds a five-clause bool should query for keywords', function () {
    $query = (new SearchActions)->nameSearch(Request::create('/search', 'GET', ['keywords' => 'haunted house']));

    $built = $query->buildQuery();
    expect($built['bool']['should'])->toHaveCount(5);
    expect($built['bool']['minimum_should_match'])->toBe(1);

    // First clause: exact name.raw match boosted 10.
    // note: boost values come back as floats (10.0) from the query builder.
    expect($built['bool']['should'][0]['match']['name.raw']['query'])->toBe('haunted house');
    expect($built['bool']['should'][0]['match']['name.raw']['boost'])->toBe(10.0);

    // Second clause: fuzzy name match.
    expect($built['bool']['should'][1]['match']['name']['fuzziness'])->toBe('AUTO');

    // Last clause: multi_match bool_prefix over ngram subfields.
    expect($built['bool']['should'][4]['multi_match']['type'])->toBe('bool_prefix');
    expect($built['bool']['should'][4]['multi_match']['fields'])->toBe(['name._2gram', 'name._3gram']);
});

test('eventSearch returns a match_all query when no keywords supplied', function () {
    $query = (new SearchActions)->eventSearch(Request::create('/search', 'GET', []));

    $built = $query->buildQuery();
    expect($built)->toHaveKey('match_all');
    expect((array) $built['match_all'])->toBe([]);
});

test('eventSearch builds the same five-clause bool should query as nameSearch for keywords', function () {
    $query = (new SearchActions)->eventSearch(Request::create('/search', 'GET', ['keywords' => 'escape room']));

    $built = $query->buildQuery();
    expect($built['bool']['should'])->toHaveCount(5);
    expect($built['bool']['minimum_should_match'])->toBe(1);
    expect($built['bool']['should'][3]['prefix']['name']['value'])->toBe('escape room');
});

// ============================================================
// maxPriceQuery — must never include the current price filter
// ============================================================

test('maxPriceQuery excludes the price filter even when price0/price1 are set', function () {
    // Regression test: the price slider's own upper bound is computed by
    // aggregating max price over this query. If it included the CURRENT
    // price filter, the aggregation would be self-referential — the ceiling
    // could never exceed whatever price range was already applied, which
    // permanently capped the slider at the last-selected value the moment
    // any upper bound was set (reported live: setting price to $45 in a
    // saved search made the results page's own slider unable to go above
    // $45 ever again).
    $requestParams = ['price0' => 10, 'price1' => 45];

    $locationFilters = callBuilder('buildLocationFilter', $requestParams);
    $searchFilters = callBuilder('buildSearchFilters', $requestParams);
    $boundaryFilter = callBuilder('buildMapBoundaryFilter', $requestParams);

    // Sanity check: the price filter really was built, so its absence below
    // is a meaningful exclusion, not a no-op.
    expect($searchFilters['prices'])->not->toBeNull();
    expect(json_encode($searchFilters['prices']->buildQuery()))->toContain('priceranges.price');

    $request = Request::create('/search', 'GET', $requestParams);
    // No live/searchType params here, so $applyGeoFilter is irrelevant to
    // what this test asserts (price exclusion) — false either way.
    $maxPriceQuery = invokeControllerMethod('maxPriceQuery', [$searchFilters, $locationFilters, $boundaryFilter, $request, false]);

    expect(json_encode($maxPriceQuery->buildQuery()))->not->toContain('priceranges.price');
});

test('maxPriceQuery still includes the other active filters (category)', function () {
    $requestParams = ['price0' => 10, 'price1' => 45, 'category' => '3,5'];

    $locationFilters = callBuilder('buildLocationFilter', $requestParams);
    $searchFilters = callBuilder('buildSearchFilters', $requestParams);
    $boundaryFilter = callBuilder('buildMapBoundaryFilter', $requestParams);

    $request = Request::create('/search', 'GET', $requestParams);
    $maxPriceQuery = invokeControllerMethod('maxPriceQuery', [$searchFilters, $locationFilters, $boundaryFilter, $request, false]);

    $built = json_encode($maxPriceQuery->buildQuery());
    expect($built)->not->toContain('priceranges.price');
    expect($built)->toContain('category_id');
});

test('a coordinate of exactly 0 still builds a geo filter', function () {
    // Longitude 0 runs through London; latitude 0 is the equator. Both are
    // real, searchable places whose coordinate is falsy in PHP. This
    // controller used to gate on `$request->lat && $request->lng`, so such a
    // search silently dropped its geo filter and returned worldwide results.
    // EventSearchFilterBuilder already gated on isset() and documented this
    // as a bug here; delegating to it is what fixed it.
    $result = callBuilder('buildLocationFilter', [
        'searchType' => 'inPerson',
        'lat' => 51.5,
        'lng' => 0,
        'city' => 'London',
    ]);

    expect($result['geoFilter'])->not->toBeNull();

    $compiled = $result['geoFilter']->buildQuery();
    expect($compiled['geo_distance']['location_latlon']['lon'])->toBe(0.0);
    expect($compiled['geo_distance']['distance'])->toBe('40km');
});

test('a 0 coordinate is not mistaken for a missing one, and logs nothing', function () {
    callBuilder('buildLocationFilter', ['searchType' => 'inPerson', 'lat' => 0, 'lng' => 0]);

    Log::shouldNotHaveReceived('warning');
});

test('a search resolves its slugs once, not once per filter stage', function () {
    // criteriaFromRequest() is called by all three filter builders and again
    // by the results and max-price queries. It does slug->id lookups against
    // categories, genres and remote_locations, so without memoisation one
    // search on the busiest page on the site fires those lookups five times
    // over (caught in review of the refactor that introduced this path).
    $category = Category::factory()->create(['slug' => 'immersive-theatre']);
    $genre = Genre::factory()->create(['slug' => 'horror', 'admin' => 1]);

    $request = Request::create('/index/search', 'GET', [
        'searchType' => 'inPerson',
        'category' => $category->slug,
        'tag' => $genre->slug,
        'lat' => 34.05,
        'lng' => -118.24,
    ]);

    $controller = new ListingsController;
    $ref = new ReflectionMethod(ListingsController::class, 'criteriaFromRequest');
    $ref->setAccessible(true);

    DB::enableQueryLog();
    // The same sequence index()/apiIndex() run.
    $ref->invoke($controller, $request);
    $ref->invoke($controller, $request);
    $ref->invoke($controller, $request);
    $ref->invoke($controller, $request);
    $ref->invoke($controller, $request);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // One lookup for the category slug, one for the genre slug. Five calls,
    // not five times the queries.
    expect($queries)->toBe(2);
});

test('a separate request is normalised on its own, never served the previous ones criteria', function () {
    $a = Category::factory()->create(['slug' => 'cat-a']);
    $b = Category::factory()->create(['slug' => 'cat-b']);

    $controller = new ListingsController;
    $ref = new ReflectionMethod(ListingsController::class, 'criteriaFromRequest');
    $ref->setAccessible(true);

    $first = $ref->invoke($controller, Request::create('/index/search', 'GET', ['category' => $a->slug]));
    $second = $ref->invoke($controller, Request::create('/index/search', 'GET', ['category' => $b->slug]));

    expect($first['categoryIds'])->toBe([$a->id]);
    expect($second['categoryIds'])->toBe([$b->id]);
});

// ============================================================
// buildSearchFilters — date validation (EI-LARAVEL-10)
// ============================================================

test('buildSearchFilters drops and logs a date that is not a date', function () {
    // The reported crash: a crawler requested colons encoded one time too
    // many, and Carbon::parse threw straight out of the filter builder as a
    // 500 on the busiest page on the site.
    $result = callBuilder('buildSearchFilters', [
        'start' => '2026-08-26 00%3A00%3A00',
        'end' => '2026-08-27 00%3A00%3A00',
    ]);

    expect($result['dates'] ?? null)->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Invalid search date'))->once();
});

test('buildSearchFilters still builds a date filter for a valid range, and logs nothing', function () {
    $result = callBuilder('buildSearchFilters', ['start' => '2026-09-01', 'end' => '2026-09-05']);

    expect($result['dates'] ?? null)->not->toBeNull();
    Log::shouldNotHaveReceived('warning');
});

test('buildSearchFilters logs nothing when no dates are supplied at all', function () {
    // Absent is not junk — only a present-but-unparseable value is worth a line.
    $result = callBuilder('buildSearchFilters', []);

    expect($result['dates'] ?? null)->toBeNull();
    Log::shouldNotHaveReceived('warning');
});
