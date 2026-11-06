<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Organizer;
use Tests\Support\FakeSearchEngine;

/** A running listing at a real address; secret or not is up to the test. */
function listingAt(string $city, array $location = [], array $event = []): Event
{
    $listing = Event::factory()->published()->create(['hasLocation' => true, 'closingDate' => now()->addDays(30), ...$event]);

    Location::factory()->create([
        'event_id' => $listing->id,
        'city' => $city,
        'home' => 'Hidden Home',
        'street' => '999 Hidden Lane',
        'postal_code' => '60699',
        'venue' => 'Somewhere Downtown',
        'latitude' => 41.88,
        'longitude' => -87.63,
        ...$location,
    ]);

    return $listing;
}

function secretLocationGone(array $location): void
{
    expect($location)->not->toHaveKeys(['home', 'street', 'postal_code']);
}

function searchQueryForChicago(): string
{
    return 'city=Chicago%2C+IL&lat=41.88&lng=-87.63&searchType=inPerson&live=false';
}

test('similar events keep a secret listing\'s street back, and its free-text venue and city in', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);
    $beside = listingAt('Chicago');

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();
    $location = collect($response->json('events'))->firstWhere('id', $secret->id)['location'];

    secretLocationGone($location);
    expect($location['venue'])->toBe('Somewhere Downtown')
        ->and($location['city'])->toBe('Chicago')
        ->and($response->getContent())->not->toContain('999 Hidden Lane');
});

test('similar events still give an open listing\'s street', function () {
    $open = listingAt('Chicago');
    $beside = listingAt('Chicago');

    $location = collect($this->getJson("/api/events/{$beside->slug}/similar")->json('events'))->firstWhere('id', $open->id)['location'];

    expect($location['street'])->toBe('999 Hidden Lane');
});

test('a listing that goes secret after similar events were cached stops showing its street', function () {
    $listing = listingAt('Chicago');
    $beside = listingAt('Chicago');

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk()->assertSee('999 Hidden Lane');

    Location::where('event_id', $listing->id)->update(['hiddenLocationToggle' => true]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk()->assertDontSee('999 Hidden Lane');
});

test('a cached entry read back from the store is held to the listing as it is now', function () {
    $listing = listingAt('Chicago');
    $beside = listingAt('Chicago');

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    // A shared store hands back a copy from before the change, not these objects.
    $key = "similar_events_{$beside->slug}";
    Cache::put($key, unserialize(serialize(Cache::get($key))), 60);

    Location::where('event_id', $listing->id)->update(['hiddenLocationToggle' => true]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertDontSee('999 Hidden Lane');
});

test('similar events by location keep a secret listing\'s street back', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);

    $response = $this->getJson('/api/events/similar-by-location?lat=41.88&lng=-87.63')->assertOk();

    secretLocationGone(collect($response->json('events'))->firstWhere('id', $secret->id)['location']);
    expect($response->getContent())->not->toContain('999 Hidden Lane');
});

test('the search API keeps a secret listing\'s street back, and an open listing\'s in', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);
    $open = listingAt('Chicago', ['street' => '1 Open Road']);
    FakeSearchEngine::install([$secret->id, $open->id]);

    $response = $this->getJson('/api/index/search?'.searchQueryForChicago())->assertOk();
    $rows = collect($response->json('data'));

    secretLocationGone($rows->firstWhere('id', $secret->id)['location']);
    expect($rows->firstWhere('id', $open->id)['location']['street'])->toBe('1 Open Road')
        ->and($response->getContent())->not->toContain('999 Hidden Lane');
});

test('a search hit the table has lost still answers', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);
    FakeSearchEngine::install([999999, $secret->id]);

    $response = $this->getJson('/api/index/search?'.searchQueryForChicago())->assertOk()->assertDontSee('999 Hidden Lane');

    // A list with no null row, or the grid throws on it; the keys matter as
    // much, since a gap would send `data` as an object.
    expect(array_is_list($response->json('data')))->toBeTrue()
        ->and($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($secret->id);
});

test('a window of results with a lost hit is a list too, and still says what the index counted', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);
    FakeSearchEngine::install([999999, $secret->id]);

    $response = $this->getJson('/api/index/search?'.searchQueryForChicago().'&pages=2')->assertOk();

    expect(array_is_list($response->json('data')))->toBeTrue()
        ->and($response->json('data'))->toHaveCount(1)
        ->and($response->json('to'))->toBe(1)
        ->and($response->json('total'))->toBe(2);
});

test('a page of nothing but lost hits is an empty list', function () {
    FakeSearchEngine::install([999998, 999999]);

    expect($this->getJson('/api/index/search?'.searchQueryForChicago())->assertOk()->json('data'))->toBe([]);
});

test('the search page keeps a secret listing\'s street out of what it embeds', function () {
    $secret = listingAt('Chicago', ['hiddenLocationToggle' => true]);
    FakeSearchEngine::install([$secret->id]);

    $response = $this->withoutVite()->get('/index/search?'.searchQueryForChicago())->assertOk();

    secretLocationGone($response->viewData('searchedEvents')['data'][0]['location']);
    expect($response->getContent())->not->toContain('999 Hidden Lane');
});

test('the team editing a secret listing still reads its street', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $listing = listingAt('Chicago', ['hiddenLocationToggle' => true], ['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1']);

    $this->actingAs($organizer->owner)->withoutVite()
        ->get("/hosting/event/{$listing->slug}/edit")
        ->assertOk()
        ->assertSee('999 Hidden Lane', false);
});
