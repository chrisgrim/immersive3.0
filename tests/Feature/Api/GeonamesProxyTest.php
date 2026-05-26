<?php

use App\Models\User;

/**
 * H-S6: the geonames timezone lookup is now proxied server-side so the API
 * username doesn't ship in the bundle. These tests cover the route guards,
 * not the upstream call itself.
 */
test('geonames timezone requires authentication', function () {
    $this->getJson('/api/geonames/timezone?lat=40&lng=-73')->assertStatus(401);
});

test('geonames timezone validates lat/lng', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/geonames/timezone?lat=999&lng=-73')
        ->assertStatus(422);
});

test('geonames timezone returns 503 if username is unconfigured', function () {
    config()->set('services.geonames.username', null);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/geonames/timezone?lat=40&lng=-73')
        ->assertStatus(503);
});
