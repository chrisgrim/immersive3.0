<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Events\RemoteLocation;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// EventAttributesController applies auth:sanctum in its constructor.

beforeEach(function () {
    $this->user = User::factory()->create();

    // publicRemoteLocations() caches its eligibility query for an hour (see
    // its own comment) — phpunit.xml sets CACHE_STORE, but config/cache.php
    // reads the legacy CACHE_DRIVER key, so tests actually hit real local
    // Redis, not an isolated store (a known, separately-tracked bug). Forget
    // this key explicitly so one test's eligible-locations set can't leak
    // into the next.
    Cache::forget('at-home-eligible-remote-location-ids');
});

// publicRemoteLocations() only surfaces types with at least one currently-
// bookable event (attendance_type_id=2, closingDate in the future) — see
// its own doc comment for why. Every test below that expects a type to
// actually appear needs one of these attached, or it's correctly filtered
// out regardless of admin/search/sort behavior.
function makeEligible(RemoteLocation $remoteLocation): Event
{
    $event = Event::factory()->published()->create([
        'attendance_type_id' => 2,
        'closingDate' => now()->addMonth(),
    ]);
    $event->remotelocations()->attach($remoteLocation->id);

    return $event;
}

test('GET /categories returns the catalog ordered by name', function () {
    Category::create(['name' => 'Zeta', 'slug' => 'zeta', 'description' => '', 'remote' => 0, 'rank' => 0]);
    Category::create(['name' => 'Alpha', 'slug' => 'alpha', 'description' => '', 'remote' => 0, 'rank' => 0]);

    $response = $this->actingAs($this->user)->getJson('/api/categories')->assertOk();
    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toContain('Alpha', 'Zeta');
    expect(array_search('Alpha', $names))->toBeLessThan(array_search('Zeta', $names));
});

test('GET /genres returns admin genres for any authenticated user', function () {
    Genre::create(['name' => 'Horror', 'slug' => 'horror', 'rank' => 0, 'admin' => 1, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)->getJson('/api/genres')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Horror']);
});

test('GET /agelimits returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/agelimits')->assertOk();
});

test('GET /contentadvisories returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/contentadvisories')->assertOk();
});

test('GET /mobilityadvisories returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/mobilityadvisories')->assertOk();
});

test('event attribute endpoints require authentication', function () {
    $this->getJson('/api/categories')->assertStatus(401);
    $this->getJson('/api/genres')->assertStatus(401);
    $this->getJson('/api/agelimits')->assertStatus(401);
});

// GET /remotelocations/public is the one deliberate exception carved out of
// the controller's blanket auth:sanctum middleware (see __construct()) — it
// backs the At Home nav search bar for anonymous visitors.

test('GET /remotelocations/public does not require authentication', function () {
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($zoom);

    $this->getJson('/api/remotelocations/public')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Zoom']);
});

test('GET /remotelocations/public only returns admin=true entries', function () {
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    $custom = RemoteLocation::create(['name' => 'My Custom Link', 'slug' => 'my-custom-link', 'admin' => false, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($zoom);
    makeEligible($custom);

    $names = collect($this->getJson('/api/remotelocations/public')->json())->pluck('name')->all();

    expect($names)->toContain('Zoom');
    expect($names)->not->toContain('My Custom Link');
});

test('GET /remotelocations/public only returns types with a currently-bookable event', function () {
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    $stale = RemoteLocation::create(['name' => 'Stale Platform', 'slug' => 'stale-platform', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($zoom);
    // Attached to an event, but that event's closingDate has already passed
    // — this type shouldn't be offered since picking it would return zero
    // results, the exact confusion this eligibility filter exists to avoid.
    $closedEvent = Event::factory()->published()->create([
        'attendance_type_id' => 2,
        'closingDate' => now()->subMonth(),
    ]);
    $closedEvent->remotelocations()->attach($stale->id);

    $names = collect($this->getJson('/api/remotelocations/public')->json())->pluck('name')->all();

    expect($names)->toContain('Zoom');
    expect($names)->not->toContain('Stale Platform');
});

test('GET /remotelocations/public excludes types backed only by an unpublished event', function () {
    // Regression: the eligibility query filtered attendance_type_id and
    // closingDate but not status, so a draft event with a future closingDate
    // could make its remote location appear "eligible" even though the
    // actual (published-only) search index would return zero results for it
    // — the exact confusion this whole eligibility filter exists to avoid.
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    $draftOnly = RemoteLocation::create(['name' => 'Draft Only', 'slug' => 'draft-only', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($zoom);

    $draftEvent = Event::factory()->create([
        'status' => 'd',
        'attendance_type_id' => 2,
        'closingDate' => now()->addMonth(),
    ]);
    $draftEvent->remotelocations()->attach($draftOnly->id);

    $names = collect($this->getJson('/api/remotelocations/public')->json())->pluck('name')->all();

    expect($names)->toContain('Zoom');
    expect($names)->not->toContain('Draft Only');
});

test('GET /remotelocations/public filters by search term', function () {
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    $telephone = RemoteLocation::create(['name' => 'Telephone', 'slug' => 'telephone', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($zoom);
    makeEligible($telephone);

    $names = collect($this->getJson('/api/remotelocations/public?search=Tele')->json())->pluck('name')->all();

    expect($names)->toBe(['Telephone']);
});

test('GET /remotelocations/public sorts major platforms first, then alphabetically', function () {
    $altspace = RemoteLocation::create(['name' => 'AltSpace', 'slug' => 'altspace', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    $zoom = RemoteLocation::create(['name' => 'Zoom', 'slug' => 'zoom', 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
    makeEligible($altspace);
    makeEligible($zoom);

    $names = collect($this->getJson('/api/remotelocations/public')->json())->pluck('name')->all();

    expect(array_search('Zoom', $names))->toBeLessThan(array_search('AltSpace', $names));
});

test('GET /remotelocations/public defaults to 6 results', function () {
    for ($i = 0; $i < 10; $i++) {
        $type = RemoteLocation::create(['name' => "Type {$i}", 'slug' => "type-{$i}", 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
        makeEligible($type);
    }

    $this->getJson('/api/remotelocations/public')
        ->assertOk()
        ->assertJsonCount(6);
});

test('GET /remotelocations/public caps the limit at 20', function () {
    for ($i = 0; $i < 25; $i++) {
        $type = RemoteLocation::create(['name' => "Type {$i}", 'slug' => "type-{$i}", 'admin' => true, 'rank' => 0, 'user_id' => $this->user->id]);
        makeEligible($type);
    }

    $this->getJson('/api/remotelocations/public?limit=100')
        ->assertOk()
        ->assertJsonCount(20);
});
