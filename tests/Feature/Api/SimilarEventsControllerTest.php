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
