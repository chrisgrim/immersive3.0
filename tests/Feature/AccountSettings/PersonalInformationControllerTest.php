<?php

use App\Models\User;
use App\Models\UserLocation;

// ---------------------------------------------------------------------------
// GET /api/account-settings/personal-info
// ---------------------------------------------------------------------------

test('guests are blocked from viewing personal info', function () {
    $this->getJson('/api/account-settings/personal-info')->assertUnauthorized();
});

test('a user sees their own personal info with no location set', function () {
    $user = User::factory()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'legal_first_name' => 'Ada',
        'legal_last_name' => 'Lovelace',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/account-settings/personal-info')
        ->assertOk()
        ->assertJson([
            'legal_first_name' => 'Ada',
            'legal_last_name' => 'Lovelace',
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'location' => null,
        ])
        ->assertJsonMissingPath('phone');
});

test('a user with a saved location sees it shaped for the frontend, city-level only', function () {
    $user = User::factory()->create();
    UserLocation::factory()->for($user)->create([
        'street' => '123 Main St',
        'city' => 'Portland',
        'region' => 'OR',
        'postal_code' => '97201',
        'country' => 'USA',
        'latitude' => 45.5,
        'longitude' => -122.6,
    ]);

    $this->actingAs($user)
        ->getJson('/api/account-settings/personal-info')
        ->assertOk()
        ->assertJsonFragment([
            'location' => [
                'city' => 'Portland',
                'region' => 'OR',
                'country' => 'USA',
                'lat' => 45.5,
                'lng' => -122.6,
            ],
        ])
        // The dormant street/postal_code columns must never reach the client.
        ->assertJsonMissing(['street' => '123 Main St'])
        ->assertJsonMissing(['postal_code' => '97201']);
});

// ---------------------------------------------------------------------------
// PATCH .../legal-name
// ---------------------------------------------------------------------------

test('a user can save their legal name', function () {
    $user = User::factory()->create(['legal_first_name' => null, 'legal_last_name' => null]);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/legal-name', [
            'legal_first_name' => 'Grace',
            'legal_last_name' => 'Hopper',
        ])
        ->assertOk()
        ->assertJson(['legal_first_name' => 'Grace', 'legal_last_name' => 'Hopper']);

    expect($user->fresh()->legal_first_name)->toBe('Grace');
    expect($user->fresh()->legal_last_name)->toBe('Hopper');
});

test('legal name requires both first and last name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/legal-name', ['legal_first_name' => 'Grace'])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// PATCH .../preferred-name
// ---------------------------------------------------------------------------

test('a user can save their preferred name', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/preferred-name', ['name' => 'New Name'])
        ->assertOk();

    expect($user->fresh()->name)->toBe('New Name');
});

test('preferred name is capped at 60 characters, matching the old settings form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/preferred-name', ['name' => str_repeat('a', 61)])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// PATCH .../location
// ---------------------------------------------------------------------------

test('a user can save a location for the first time', function () {
    $user = User::factory()->create();
    expect($user->location)->toBeNull();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/location', [
            'city' => 'Cupertino',
            'region' => 'CA',
            'country' => 'United States',
            'lat' => 37.323,
            'lng' => -122.032,
        ])
        ->assertOk()
        ->assertJson([
            'city' => 'Cupertino',
            'region' => 'CA',
            'country' => 'United States',
        ]);

    $location = $user->fresh()->location;
    expect($location)->not->toBeNull();
    expect($location->city)->toBe('Cupertino');
    expect($location->street)->toBeNull();
});

test('saving a location twice updates the existing row instead of creating a duplicate', function () {
    $user = User::factory()->create();
    UserLocation::factory()->for($user)->create(['city' => 'Old City']);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/location', [
            'city' => 'Portland',
            'country' => 'United States',
        ])
        ->assertOk();

    expect(UserLocation::where('user_id', $user->id)->count())->toBe(1);
    expect($user->fresh()->location->city)->toBe('Portland');
});

// Note: the controller also catches a duplicate-key QueryException from a
// genuine concurrent race (two first-time saves both missing the initial
// lookup before either insert commits) and falls back to an update. That
// specific interleaving isn't reproducible in a synchronous test process —
// the user_locations.user_id unique constraint (migration
// 2026_08_17_065708) is what actually prevents the duplicate row; the catch
// block only decides how the loser's request resolves.

test('location requires a city, but not a street', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/location', [])
        ->assertStatus(422);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/location', ['city' => 'Cupertino'])
        ->assertOk();
});

test('an existing street address on the row is left untouched by a location save', function () {
    $user = User::factory()->create();
    UserLocation::factory()->for($user)->create(['street' => '1 Infinite Loop', 'city' => 'Old City']);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/location', ['city' => 'Cupertino'])
        ->assertOk();

    expect($user->fresh()->location->street)->toBe('1 Infinite Loop');
});

// ---------------------------------------------------------------------------
// Cross-user isolation
// ---------------------------------------------------------------------------

test('updating personal info never touches another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['name' => 'Untouched']);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/personal-info/preferred-name', ['name' => 'Changed'])
        ->assertOk();

    expect($other->fresh()->name)->toBe('Untouched');
});
