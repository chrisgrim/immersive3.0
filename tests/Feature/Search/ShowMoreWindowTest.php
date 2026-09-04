<?php

use App\Http\Controllers\Search\ListingsController;
use App\Models\Event;
use Tests\Support\FakeSearchEngine;

/**
 * "Show more": `?page=N` means N pages are open, the page route renders
 * that window on a cold load, and a click asks the API for the same window
 * (`pages=N`) and replaces the list. FakeSearchEngine honours from/size, so
 * the windows below are real slices of the canned hit list.
 */
function windowEvents(int $count)
{
    return Event::factory()->count($count)->published()->create([
        'location_latlon' => ['lat' => 34.05, 'lon' => -118.24],
    ]);
}

function laListQuery(): string
{
    return 'city=Los+Angeles%2C+CA&lat=34.0549076&lng=-118.242643&searchType=inPerson&live=false';
}

function windowEngine(): FakeSearchEngine
{
    return resolve(\Laravel\Scout\EngineManager::class)->engine();
}

// ============================================================
// Cold load of the page — /index/search?page=N
// ============================================================

test('a cold load of ?page=2 renders pages 1 and 2 as one open list', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    $response = $this->withoutVite()->get('/index/search?'.laListQuery().'&page=2');

    $response->assertOk();
    $events = $response->viewData('searchedEvents');
    expect($events['data'])->toHaveCount(40);
    expect($events['current_page'])->toBe(2);
    expect($events['per_page'])->toBe(20);
    expect($events['last_page'])->toBe(3);
    expect($events['from'])->toBe(1);
    expect($events['to'])->toBe(40);
    expect($events['has_more'])->toBeTrue();

    // One query, from the top, sized for both pages.
    $results = windowEngine()->searches[0]['body'];
    expect($results['from'] ?? 0)->toBe(0);
    expect($results['size'])->toBe(40);
});

test('the last page has no more to show', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    $events = $this->withoutVite()->get('/index/search?'.laListQuery().'&page=3')->viewData('searchedEvents');

    expect($events['data'])->toHaveCount(45);
    expect($events['current_page'])->toBe(3);
    expect($events['has_more'])->toBeFalse();
});

test('a depth past the results is one page, fully open — not page three of one', function () {
    FakeSearchEngine::install(windowEvents(3)->pluck('id')->all());

    $events = $this->withoutVite()->get('/index/search?'.laListQuery().'&page=3')->viewData('searchedEvents');

    expect($events['data'])->toHaveCount(3);
    expect($events['current_page'])->toBe(1);
    expect($events['last_page'])->toBe(1);
    expect($events['has_more'])->toBeFalse();
});

test('a cold load never fetches deeper than MAX_INITIAL_PAGES', function () {
    FakeSearchEngine::install(windowEvents(3)->pluck('id')->all());

    $this->withoutVite()->get('/index/search?'.laListQuery().'&page=99')->assertOk();

    expect(windowEngine()->searches[0]['body']['size'])->toBe(ListingsController::PER_PAGE * ListingsController::MAX_INITIAL_PAGES);
});

test('at the depth cap the list stops, says so, and a cold load restores exactly that', function () {
    // One more match than MAX_INITIAL_PAGES × PER_PAGE can open.
    $cap = ListingsController::PER_PAGE * ListingsController::MAX_INITIAL_PAGES;
    FakeSearchEngine::install(windowEvents($cap + 1)->pluck('id')->all());

    $events = $this->withoutVite()->get('/index/search?'.laListQuery().'&page=99')->viewData('searchedEvents');

    expect($events['data'])->toHaveCount($cap);
    expect($events['current_page'])->toBe(ListingsController::MAX_INITIAL_PAGES);
    expect($events['last_page'])->toBe(ListingsController::MAX_INITIAL_PAGES + 1);
    expect($events['has_more'])->toBeFalse();
    expect($events['limit_reached'])->toBeTrue();

    $results = windowEngine()->searches[0]['body'];
    expect($results['from'] ?? 0)->toBe(0);
    expect($results['size'])->toBe(ListingsController::PER_PAGE * ListingsController::MAX_INITIAL_PAGES);
});

test('a Show more click past the cap is clamped the same way', function () {
    $cap = ListingsController::PER_PAGE * ListingsController::MAX_INITIAL_PAGES;
    FakeSearchEngine::install(windowEvents($cap + 1)->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.laListQuery().'&pages=99&include_pins=0');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount($cap);
    expect($response->json('current_page'))->toBe(ListingsController::MAX_INITIAL_PAGES);
    expect($response->json('has_more'))->toBeFalse();
    expect($response->json('limit_reached'))->toBeTrue();
    expect(windowEngine()->searches[0]['body']['size'])->toBe(ListingsController::PER_PAGE * ListingsController::MAX_INITIAL_PAGES);
});

test('the last page is not the cap: has_more and limit_reached are both false', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.laListQuery().'&pages=3&include_pins=0');

    expect($response->json('has_more'))->toBeFalse();
    expect($response->json('limit_reached'))->toBeFalse();
});

test('no results is still the same shape, with nothing more to show', function () {
    FakeSearchEngine::install([]);

    $events = $this->withoutVite()->get('/index/search?'.laListQuery().'&page=2')->viewData('searchedEvents');

    expect($events['data'])->toBe([]);
    expect($events['current_page'])->toBe(1);
    expect($events['has_more'])->toBeFalse();
    expect($events['limit_reached'])->toBeFalse();
});

// ============================================================
// The API — /api/index/search
// ============================================================

test('a Show more click asks for the whole window and gets it back, declining the pins', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.laListQuery().'&pages=2&include_pins=0');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(40);
    expect($response->json('current_page'))->toBe(2);
    expect($response->json('last_page'))->toBe(3);
    expect($response->json('has_more'))->toBeTrue();
    // Declined pins are absent, not empty: the client keeps its markers.
    expect($response->json())->not->toHaveKey('pins');

    $engine = windowEngine();
    expect($engine->searches[0]['body']['from'] ?? 0)->toBe(0);
    expect($engine->searches[0]['body']['size'])->toBe(40);
    // include_pins=0 means the pins query is not even run.
    expect($engine->pinSearches())->toBe([]);
});

test('without pages= the API is still one page at a time, pins included', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.laListQuery().'&page=2');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20);
    expect($response->json('current_page'))->toBe(2);
    expect($response->json('has_more'))->toBeTrue();
    expect($response->json('pins'))->toHaveCount(45);

    $body = windowEngine()->searches[0]['body'];
    expect($body['from'])->toBe(20);
    expect($body['size'])->toBe(20);
});

test('a fresh search says whether there is more to show', function () {
    FakeSearchEngine::install(windowEvents(45)->pluck('id')->all());

    expect($this->getJson('/api/index/search?'.laListQuery())->json('has_more'))->toBeTrue();

    FakeSearchEngine::install(windowEvents(5)->pluck('id')->all());

    expect($this->getJson('/api/index/search?'.laListQuery())->json('has_more'))->toBeFalse();
});
