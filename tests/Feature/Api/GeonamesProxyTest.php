<?php

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

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

test('a swallowed upstream failure is still reported to the exception handler', function () {
    // Every catch block that logs and returns a friendly error now also calls
    // report($e), so the failure reaches Sentry instead of only laravel.log.
    // This pins the pattern on one representative site (audit fix #14).
    Exceptions::fake();
    config(['services.geonames.username' => 'ei-test']);
    Http::fake(fn () => throw new ConnectionException('upstream down'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/geonames/timezone?lat=40&lng=-73')
        ->assertStatus(502);

    Exceptions::assertReported(ConnectionException::class);
});
