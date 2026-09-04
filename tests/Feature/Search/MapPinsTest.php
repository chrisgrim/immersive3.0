<?php

use App\Http\Controllers\Search\ListingsController;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeSearchEngine;

/**
 * The search map draws every match, not the current page — see
 * ListingsController::MAX_MAP_PINS and Event::mapPins(). Endpoint tests run
 * against FakeSearchEngine, so they cover whether the pins query is issued,
 * on which requests, with what shape, and whether the answer reaches the
 * JSON and the page.
 */
function mapPinKeys(): array
{
    return ['id', 'slug', 'name', 'tag_line', 'price_range', 'thumbImagePath', 'location_latlon'];
}

function pinnableEvents(int $count)
{
    return Event::factory()->count($count)->published()->create([
        'location_latlon' => ['lat' => 34.05, 'lon' => -118.24],
        'price_range' => '$10 - $20',
        'thumbImagePath' => 'event-images/x-thumb.webp',
        'tag_line' => 'A tag line',
    ]);
}

function losAngelesQuery(): string
{
    return 'city=Los+Angeles%2C+CA&lat=34.0549076&lng=-118.242643&searchType=inPerson&live=false';
}

// ============================================================
// Event::mapPins — the payload
// ============================================================

test('Event::mapPins returns only the marker fields, as plain arrays', function () {
    $events = pinnableEvents(2);

    $pins = Event::mapPins($events->pluck('id')->all());

    expect($pins)->toHaveCount(2);
    foreach ($pins as $pin) {
        expect(array_keys($pin))->toBe(mapPinKeys());
    }
    expect($pins[0]['location_latlon'])->toBe(['lat' => 34.05, 'lon' => -118.24]);
    // The $appends accessors must not sneak in — isFavorited is a query per row.
    expect($pins[0])->not->toHaveKey('isFavorited')
        ->not->toHaveKey('description');
});

test('Event::mapPins skips ids the table no longer has, and is free for no ids', function () {
    $kept = pinnableEvents(1)->first();
    $gone = pinnableEvents(1)->first();
    $gone->delete();

    $pins = Event::mapPins([$kept->id, $gone->id, 999999]);

    expect($pins)->toHaveCount(1);
    expect($pins[0]['id'])->toBe($kept->id);

    DB::enableQueryLog();
    expect(Event::mapPins([]))->toBe([]);
    expect(DB::getQueryLog())->toBe([]);
});

// ============================================================
// /api/index/search — the JSON the page re-fetches
// ============================================================

test('an in-person search returns a pin for every match, not just the page', function () {
    $events = pinnableEvents(3);
    FakeSearchEngine::install($events->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.losAngelesQuery());

    $response->assertOk();
    expect($response->json('pins'))->toHaveCount(3);
    expect(collect($response->json('pins'))->pluck('id')->sort()->values()->all())
        ->toBe($events->pluck('id')->sort()->values()->all());
    expect(array_keys($response->json('pins.0')))->toBe(mapPinKeys());
});

test('the pins query is the results query plus a coordinates filter, capped, ids only', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $this->getJson('/api/index/search?'.losAngelesQuery())->assertOk();

    $pinSearches = $engine->pinSearches();
    expect($pinSearches)->toHaveCount(1);

    $body = $pinSearches[0]['body'];
    expect($body['size'])->toBe(ListingsController::MAX_MAP_PINS);
    expect($body['_source'])->toBeFalse();

    // Same filters as the list — the results query is the first search
    // issued (the paginated one) — with the hasLocation term appended.
    $resultFilters = $engine->searches[0]['body']['query']['bool']['filter'];
    $pinFilters = $body['query']['bool']['filter'];
    expect(array_slice($pinFilters, 0, count($resultFilters)))->toBe($resultFilters);
    expect(json_encode(array_slice($pinFilters, count($resultFilters))))->toContain('"hasLocation"');
});

test('a price filter narrows the pins exactly as it narrows the list', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $this->getJson('/api/index/search?'.losAngelesQuery().'&price0=10&price1=50')->assertOk();

    $pinQuery = json_encode($engine->pinSearches()[0]['body']['query']);
    expect($pinQuery)->toContain('"priceranges.price"');
    expect($pinQuery)->toContain('"gte":10');
    expect($pinQuery)->toContain('"lte":50');
});

test('a live map search bounds the pins to the viewport, like the results', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $this->getJson('/api/index/search?searchType=inPerson&live=true&lat=34.05&lng=-118.3'
        .'&NElat=34.2&NElng=-118.1&SWlat=33.9&SWlng=-118.5')->assertOk();

    expect(json_encode($engine->pinSearches()[0]['body']['query']))->toContain('geo_bounding_box');
    expect(json_encode($engine->searches[0]['body']['query']))->toContain('geo_bounding_box');
});

test('page 2 carries the full pin set as well', function () {
    FakeSearchEngine::install(pinnableEvents(3)->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.losAngelesQuery().'&page=2');

    $response->assertOk();
    expect($response->json('pins'))->toHaveCount(3);
});

test('an at-home search carries no pins and never asks Elasticsearch for any', function () {
    FakeSearchEngine::install(pinnableEvents(2)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $response = $this->getJson('/api/index/search?searchType=atHome');

    $response->assertOk();
    expect($response->json('pins'))->toBe([]);
    expect($engine->pinSearches())->toBe([]);
});

test('a bare search type with live set still gets pins — the same gate as the geo filter', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $response = $this->getJson('/api/index/search?live=false&lat=34.05&lng=-118.24');

    $response->assertOk();
    expect($response->json('pins'))->toHaveCount(1);
    expect($engine->pinSearches())->toHaveCount(1);
});

test('without live there is no map on the page, so no pins query even for in-person', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $response = $this->getJson('/api/index/search?searchType=inPerson&lat=34.05&lng=-118.24');

    $response->assertOk();
    expect($response->json('pins'))->toBe([]);
    expect($engine->pinSearches())->toBe([]);
});

test('a search with no matches still answers with an empty pins list', function () {
    FakeSearchEngine::install([]);

    $response = $this->getJson('/api/index/search?'.losAngelesQuery());

    $response->assertOk();
    expect($response->json('total'))->toBe(0);
    expect($response->json('pins'))->toBe([]);
});

test('an id the index still holds but the table has lost is not a pin', function () {
    $event = pinnableEvents(1)->first();
    FakeSearchEngine::install([$event->id, 999999]);

    $response = $this->getJson('/api/index/search?'.losAngelesQuery());

    $response->assertOk();
    expect(collect($response->json('pins'))->pluck('id')->all())->toBe([$event->id]);
});

// ============================================================
// /index/search — the server-rendered page
// ============================================================

test('the map page hands the pins to the desktop component', function () {
    $event = pinnableEvents(1)->first();
    FakeSearchEngine::install([$event->id]);

    $response = $this->withoutVite()->get('/index/search?'.losAngelesQuery());

    $response->assertOk()->assertSee('<vue-search-location', false);
    expect(collect($response->viewData('mapPins'))->pluck('id')->all())->toBe([$event->id]);
});

test('the map page hands the pins to the mobile component too', function () {
    $event = pinnableEvents(1)->first();
    FakeSearchEngine::install([$event->id]);

    $response = $this->withoutVite()
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')
        ->get('/index/search?'.losAngelesQuery());

    $response->assertOk()->assertSee('<vue-search-location-mobile', false);
    expect(collect($response->viewData('mapPins'))->pluck('id')->all())->toBe([$event->id]);
});

test('a saved custom-map search replays its rectangle for the pins too', function () {
    $event = pinnableEvents(1)->first();
    FakeSearchEngine::install([$event->id]);
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $this->withoutVite()
        ->get('/index/search?searchType=inPerson&live=true&lat=34.05&lng=-118.3'
            .'&NElat=34.2&NElng=-118.1&SWlat=33.9&SWlng=-118.5')
        ->assertOk()
        ->assertSee(':pins=\'[{"id":'.$event->id.',', false);

    expect(json_encode($engine->pinSearches()[0]['body']['query']))->toContain('geo_bounding_box');
});

test('the list page does not compute pins', function () {
    FakeSearchEngine::install(pinnableEvents(1)->pluck('id')->all());
    $engine = resolve(\Laravel\Scout\EngineManager::class)->engine();

    $response = $this->withoutVite()->get('/index/search?searchType=atHome');

    $response->assertOk();
    expect($response->viewData('mapPins'))->toBe([]);
    expect($engine->pinSearches())->toBe([]);
});
