<?php

use App\Models\Components\Favorite;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\User;

// getSimilar() caches its result for 24h keyed by event slug, and that cache is SHARED
// across every user. So the per-user "favorited?" flag must be computed per-request, never
// baked into the cached payload — otherwise the first viewer's favorites leak to everyone.
// (Regression test for the cached-path currentUserFavorite eager-load leak.)

test('similar-events favorite state is per-user and does not leak through the shared cache', function () {
    $base = Event::factory()->published()->create([
        'hasLocation' => true,
        'closingDate' => now()->addDays(30),
    ]);
    Location::factory()->create(['event_id' => $base->id, 'city' => 'Portland']);

    $similar = Event::factory()->published()->create([
        'hasLocation' => true,
        'closingDate' => now()->addDays(30),
    ]);
    Location::factory()->create(['event_id' => $similar->id, 'city' => 'Portland']);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice favorites the similar event.
    Favorite::factory()->create([
        'user_id' => $alice->id,
        'favorited_id' => $similar->id,
        'favorited_type' => Event::class,
    ]);

    // Alice loads the list first — this populates the 24h shared cache.
    $aliceResponse = $this->actingAs($alice)
        ->getJson("/api/events/{$base->slug}/similar")
        ->assertOk();
    $aliceSimilar = collect($aliceResponse->json('events'))->firstWhere('id', $similar->id);
    expect($aliceSimilar)->not->toBeNull();
    expect($aliceSimilar['isFavorited'])->toBeTrue();

    // Bob (who has NOT favorited it) now hits the SAME cached endpoint. He must see HIS
    // own favorite state, not Alice's. Before the fix this returned true (leaked).
    $bobResponse = $this->actingAs($bob)
        ->getJson("/api/events/{$base->slug}/similar")
        ->assertOk();
    $bobSimilar = collect($bobResponse->json('events'))->firstWhere('id', $similar->id);
    expect($bobSimilar)->not->toBeNull();
    expect($bobSimilar['isFavorited'])->toBeFalse();
});

// ----- getLatestRemote() -----
// The non-location search results page's (all.vue) empty-state fallback —
// reuses getLatestRemoteEvents(), already exercised indirectly via
// getSimilarByLocation's own remote fallback, so these tests focus on what's
// new: the route/response shape, not re-proving the underlying query.

test('latest-remote returns published, currently-open, remote events only', function () {
    $remote = Event::factory()->published()->create([
        'hasLocation' => false,
        'closingDate' => now()->addDays(10),
    ]);
    $inPerson = Event::factory()->published()->create([
        'hasLocation' => true,
        'closingDate' => now()->addDays(10),
    ]);
    $closedRemote = Event::factory()->published()->create([
        'hasLocation' => false,
        'closingDate' => now()->subDays(1),
    ]);
    $draftRemote = Event::factory()->create([
        'status' => 'd',
        'hasLocation' => false,
        'closingDate' => now()->addDays(10),
    ]);

    $response = $this->getJson('/api/events/latest-remote')->assertOk();

    $ids = collect($response->json('events'))->pluck('id');
    expect($ids)->toContain($remote->id);
    expect($ids)->not->toContain($inPerson->id, $closedRemote->id, $draftRemote->id);
});

test('latest-remote does not require authentication', function () {
    Event::factory()->published()->create(['hasLocation' => false, 'closingDate' => now()->addDays(10)]);

    $this->getJson('/api/events/latest-remote')->assertOk();
});

test('latest-remote caps at 12 events, most recently created first', function () {
    $events = collect(range(1, 14))->map(fn ($i) => Event::factory()->published()->create([
        'hasLocation' => false,
        'closingDate' => now()->addDays(10),
        'created_at' => now()->subMinutes(14 - $i),
    ]));

    $response = $this->getJson('/api/events/latest-remote')->assertOk();

    $ids = collect($response->json('events'))->pluck('id');
    expect($ids)->toHaveCount(12);
    expect($ids->first())->toBe($events->last()->id);
});
