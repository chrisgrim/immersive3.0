<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Genre;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // The testing cache driver is array (per-process), but flush to guarantee a
    // clean slate so rememberForever() actually recomputes on the first call.
    Cache::flush();
});

// ----- getActiveCategories() (GET /api/categories/active/cached) -----

test('getActiveCategories returns categories that have a qualifying published event', function () {
    $category = Category::factory()->create(['rank' => 5]);
    Event::factory()->published()->create([
        'category_id' => $category->id,
        'closingDate' => now()->addMonth(),
    ]);

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.id'))->toBe($category->id);
});

test('getActiveCategories caches forever under the active-categories key', function () {
    $category = Category::factory()->create();
    Event::factory()->published()->create([
        'category_id' => $category->id,
        'closingDate' => null,
    ]);

    expect(Cache::has('active-categories'))->toBeFalse();

    $this->getJson('/api/categories/active/cached')->assertOk();

    expect(Cache::has('active-categories'))->toBeTrue();
});

test('getActiveCategories serves the cached value on a second request', function () {
    $category = Category::factory()->create();
    Event::factory()->published()->create([
        'category_id' => $category->id,
        'closingDate' => null,
    ]);

    $first = $this->getJson('/api/categories/active/cached')->assertOk();
    expect($first->json())->toHaveCount(1);

    // Adding another qualifying category after the cache is warm must NOT change
    // the response, proving the second request is served from cache.
    $other = Category::factory()->create();
    Event::factory()->published()->create([
        'category_id' => $other->id,
        'closingDate' => null,
    ]);

    $second = $this->getJson('/api/categories/active/cached')->assertOk();
    expect($second->json())->toHaveCount(1);
    expect($second->json('0.id'))->toBe($category->id);
});

test('getActiveCategories excludes a category with no qualifying events', function () {
    // No events at all.
    Category::factory()->create();

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('getActiveCategories excludes a category whose only event is a draft', function () {
    $category = Category::factory()->create();
    Event::factory()->draft()->create([
        'category_id' => $category->id,
        'closingDate' => now()->addMonth(),
    ]);

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('getActiveCategories excludes a category whose published event already closed', function () {
    $category = Category::factory()->create();
    Event::factory()->published()->create([
        'category_id' => $category->id,
        'closingDate' => now()->subDay(),
    ]);

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    // closingDate in the past fails both (closingDate >= startOfDay) and (closingDate is null).
    expect($response->json())->toHaveCount(0);
});

test('getActiveCategories includes a category whose published event has a null closing date', function () {
    $category = Category::factory()->create();
    Event::factory()->published()->create([
        'category_id' => $category->id,
        'closingDate' => null,
    ]);

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    expect($response->json())->toHaveCount(1);
});

test('getActiveCategories orders categories by rank descending', function () {
    $low = Category::factory()->create(['rank' => 1]);
    $high = Category::factory()->create(['rank' => 10]);
    $mid = Category::factory()->create(['rank' => 5]);

    foreach ([$low, $high, $mid] as $category) {
        Event::factory()->published()->create([
            'category_id' => $category->id,
            'closingDate' => null,
        ]);
    }

    $response = $this->getJson('/api/categories/active/cached')->assertOk();

    expect($response->json('*.id'))->toBe([$high->id, $mid->id, $low->id]);
});

// ----- getActiveGenres() (GET /api/genres/active/cached) -----

test('getActiveGenres returns admin genres with a qualifying published event', function () {
    $genre = Genre::factory()->admin()->create();
    $event = Event::factory()->published()->create(['closingDate' => now()->addMonth()]);
    $genre->events()->attach($event->id);

    $response = $this->getJson('/api/genres/active/cached')->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.id'))->toBe($genre->id);
});

test('getActiveGenres caches forever under the active-genres key', function () {
    $genre = Genre::factory()->admin()->create();
    $event = Event::factory()->published()->create(['closingDate' => null]);
    $genre->events()->attach($event->id);

    expect(Cache::has('active-genres'))->toBeFalse();

    $this->getJson('/api/genres/active/cached')->assertOk();

    expect(Cache::has('active-genres'))->toBeTrue();
});

test('getActiveGenres serves the cached value on a second request', function () {
    $genre = Genre::factory()->admin()->create();
    $event = Event::factory()->published()->create(['closingDate' => null]);
    $genre->events()->attach($event->id);

    $first = $this->getJson('/api/genres/active/cached')->assertOk();
    expect($first->json())->toHaveCount(1);

    // Warm cache: a newly qualifying genre must not appear on the next request.
    $other = Genre::factory()->admin()->create();
    $event2 = Event::factory()->published()->create(['closingDate' => null]);
    $other->events()->attach($event2->id);

    $second = $this->getJson('/api/genres/active/cached')->assertOk();
    expect($second->json())->toHaveCount(1);
    expect($second->json('0.id'))->toBe($genre->id);
});

test('getActiveGenres excludes non-admin genres even with a qualifying event', function () {
    // admin defaults to false in the factory.
    $genre = Genre::factory()->create(['admin' => false]);
    $event = Event::factory()->published()->create(['closingDate' => null]);
    $genre->events()->attach($event->id);

    $response = $this->getJson('/api/genres/active/cached')->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('getActiveGenres excludes an admin genre with no qualifying events', function () {
    Genre::factory()->admin()->create();

    $response = $this->getJson('/api/genres/active/cached')->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('getActiveGenres excludes an admin genre whose event already closed', function () {
    $genre = Genre::factory()->admin()->create();
    $event = Event::factory()->published()->create(['closingDate' => now()->subDay()]);
    $genre->events()->attach($event->id);

    $response = $this->getJson('/api/genres/active/cached')->assertOk();

    expect($response->json())->toHaveCount(0);
});

test('getActiveGenres caches independently from active-categories', function () {
    $this->getJson('/api/genres/active/cached')->assertOk();

    expect(Cache::has('active-genres'))->toBeTrue();
    expect(Cache::has('active-categories'))->toBeFalse();
});

// ----- getMaxPrice() (GET /api/price/max/cached) -----

test('getMaxPrice returns the ceil of the highest price among qualifying events', function () {
    $event = Event::factory()->published()->create(['closingDate' => now()->addMonth()]);
    $event->priceranges()->create(['price' => 42.10]);

    $response = $this->getJson('/api/price/max/cached')->assertOk();

    expect($response->json('maxPrice'))->toBe(43);
});

test('getMaxPrice returns 0 when there are no qualifying events', function () {
    $response = $this->getJson('/api/price/max/cached')->assertOk();

    expect($response->json('maxPrice'))->toBe(0);
});

test('getMaxPrice ignores a draft event\'s price', function () {
    $draft = Event::factory()->draft()->create(['closingDate' => now()->addMonth()]);
    $draft->priceranges()->create(['price' => 999]);

    $response = $this->getJson('/api/price/max/cached')->assertOk();

    expect($response->json('maxPrice'))->toBe(0);
});

test('getMaxPrice caches under the max-price key', function () {
    $event = Event::factory()->published()->create(['closingDate' => now()->addMonth()]);
    $event->priceranges()->create(['price' => 20]);

    expect(Cache::has('max-price'))->toBeFalse();

    $this->getJson('/api/price/max/cached')->assertOk();

    expect(Cache::has('max-price'))->toBeTrue();
});

test('getMaxPrice serves the cached value on a second request', function () {
    $event = Event::factory()->published()->create(['closingDate' => now()->addMonth()]);
    $event->priceranges()->create(['price' => 20]);

    $first = $this->getJson('/api/price/max/cached')->assertOk();
    expect($first->json('maxPrice'))->toBe(20);

    $pricier = Event::factory()->published()->create(['closingDate' => now()->addMonth()]);
    $pricier->priceranges()->create(['price' => 500]);

    $second = $this->getJson('/api/price/max/cached')->assertOk();
    expect($second->json('maxPrice'))->toBe(20);
});
