<?php

use App\Models\User;

// ---------------------------------------------------------------------------
// wantsNotification() — new opt-in default, backward-compat with opt-out default
// ---------------------------------------------------------------------------

test('wantsNotification defaults a missing key to false when passed default false', function () {
    $user = User::factory()->create(['notification_preferences' => null]);

    expect($user->wantsNotification('saved_event_new_dates', false))->toBeFalse();
    expect($user->wantsNotification('followed_organizer_new_event', false))->toBeFalse();
});

test('wantsNotification still defaults a missing key to true when no default is passed', function () {
    $user = User::factory()->create(['type' => 'a', 'notification_preferences' => null]);

    expect($user->wantsNotification('organizers'))->toBeTrue();
});

test('an explicit true value wins over an opt-in false default', function () {
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);

    expect($user->wantsNotification('saved_event_new_dates', false))->toBeTrue();
});

// ---------------------------------------------------------------------------
// GET /api/hub/notification-preferences
// ---------------------------------------------------------------------------

test('a user with no saved preferences sees both new toggles default off', function () {
    $user = User::factory()->create(['notification_preferences' => null]);

    $this->actingAs($user)
        ->getJson('/api/hub/notification-preferences')
        ->assertOk()
        ->assertExactJson(['notification_preferences' => [
            'saved_event_new_dates' => false,
            'followed_organizer_new_event' => false,
        ]]);
});

test('the response never includes unrelated admin keys', function () {
    $user = User::factory()->create([
        'type' => 'a',
        'notification_preferences' => ['organizers' => false, 'events' => true],
    ]);

    $response = $this->actingAs($user)->getJson('/api/hub/notification-preferences')->assertOk();

    expect($response->json('notification_preferences'))->toHaveKeys([
        'saved_event_new_dates', 'followed_organizer_new_event',
    ])->not->toHaveKeys(['organizers', 'events']);
});

test('a guest cannot read notification preferences', function () {
    $this->getJson('/api/hub/notification-preferences')->assertStatus(401);
});

// ---------------------------------------------------------------------------
// PATCH /api/hub/notification-preferences
// ---------------------------------------------------------------------------

test('an authenticated user can turn both toggles on', function () {
    $user = User::factory()->create(['notification_preferences' => null]);

    $this->actingAs($user)
        ->patchJson('/api/hub/notification-preferences', [
            'saved_event_new_dates' => true,
            'followed_organizer_new_event' => true,
        ])
        ->assertOk()
        ->assertExactJson(['notification_preferences' => [
            'saved_event_new_dates' => true,
            'followed_organizer_new_event' => true,
        ]]);

    expect($user->fresh()->notification_preferences)->toMatchArray([
        'saved_event_new_dates' => true,
        'followed_organizer_new_event' => true,
    ]);
});

test('updating the two new keys does not clobber pre-existing admin keys', function () {
    $admin = User::factory()->create([
        'type' => 'a',
        'notification_preferences' => ['organizers' => false, 'events' => true],
    ]);

    $this->actingAs($admin)
        ->patchJson('/api/hub/notification-preferences', [
            'saved_event_new_dates' => true,
            'followed_organizer_new_event' => false,
        ])
        ->assertOk();

    $fresh = $admin->fresh()->notification_preferences;
    expect($fresh)->toMatchArray([
        'organizers' => false,
        'events' => true,
        'saved_event_new_dates' => true,
        'followed_organizer_new_event' => false,
    ]);
});

test('a guest cannot update notification preferences', function () {
    $this->patchJson('/api/hub/notification-preferences', [
        'saved_event_new_dates' => true,
        'followed_organizer_new_event' => true,
    ])->assertStatus(401);
});

test('a missing key is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/hub/notification-preferences', ['saved_event_new_dates' => true])
        ->assertStatus(422);
});

test('a non-boolean value is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/hub/notification-preferences', [
            'saved_event_new_dates' => 'not-a-bool',
            'followed_organizer_new_event' => true,
        ])
        ->assertStatus(422);
});
