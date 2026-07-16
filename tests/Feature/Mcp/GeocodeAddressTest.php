<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GeocodeAddress;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function geoUser(): User
{
    return User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
}

test('geocode-address resolves structured fields via nominatim when no google key is set', function () {
    config(['services.google_geocoding.key' => null]);
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([[
            'display_name' => '30, Rockefeller Plaza, Manhattan, New York, NY, 10112, United States',
            'lat' => '40.7587', 'lon' => '-73.9787',
            'category' => 'building', 'name' => '30 Rockefeller Plaza',
            'address' => [
                'house_number' => '30', 'road' => 'Rockefeller Plaza', 'city' => 'New York',
                'state' => 'New York', 'ISO3166-2-lvl4' => 'US-NY',
                'postcode' => '10112', 'country' => 'United States', 'country_code' => 'us',
            ],
        ]], 200),
    ]);

    $response = EiServer::actingAs(geoUser())->tool(GeocodeAddress::class, [
        'address' => '30 Rockefeller Plaza, New York',
    ]);

    $response->assertOk()
        ->assertSee('Rockefeller Plaza')
        ->assertSee('40.7587')
        ->assertSee('NY')
        ->assertSee('10112');
});

test('geocode-address uses google when a server key is configured', function () {
    config(['services.google_geocoding.key' => 'server-key']);
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'results' => [[
                'formatted_address' => '30 Rockefeller Plaza, New York, NY 10112, USA',
                'geometry' => ['location' => ['lat' => 40.7587, 'lng' => -73.9787]],
                'address_components' => [
                    ['long_name' => '30', 'short_name' => '30', 'types' => ['street_number']],
                    ['long_name' => 'Rockefeller Plaza', 'short_name' => 'Rockefeller Plaza', 'types' => ['route']],
                    ['long_name' => 'New York', 'short_name' => 'New York', 'types' => ['locality']],
                    ['long_name' => 'New York', 'short_name' => 'NY', 'types' => ['administrative_area_level_1']],
                    ['long_name' => 'United States', 'short_name' => 'US', 'types' => ['country']],
                    ['long_name' => '10112', 'short_name' => '10112', 'types' => ['postal_code']],
                ],
            ]],
        ], 200),
    ]);

    $response = EiServer::actingAs(geoUser())->tool(GeocodeAddress::class, [
        'address' => '30 Rockefeller Plaza',
    ]);

    $response->assertOk()->assertSee('google')->assertSee('10112');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'maps.googleapis.com'));
});

test('geocode-address reports no matches clearly', function () {
    config(['services.google_geocoding.key' => null]);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    EiServer::actingAs(geoUser())->tool(GeocodeAddress::class, ['address' => 'xyzzy nowhere'])
        ->assertHasErrors();
});

test('geocode-address surfaces upstream failures as readable errors', function () {
    config(['services.google_geocoding.key' => null]);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

    EiServer::actingAs(geoUser())->tool(GeocodeAddress::class, ['address' => 'somewhere'])
        ->assertHasErrors();
});
