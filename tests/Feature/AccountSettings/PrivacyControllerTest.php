<?php

use App\Mail\PersonalDataRequestInternalNotice;
use App\Mail\PersonalDataRequestReceived;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// ---------------------------------------------------------------------------
// GET /api/account-settings/privacy
// ---------------------------------------------------------------------------

test('guests are blocked from viewing privacy preferences', function () {
    $this->getJson('/api/account-settings/privacy')->assertUnauthorized();
});

test('a user with no saved preferences sees both toggles default on', function () {
    $user = User::factory()->create(['privacy_preferences' => null]);

    $this->actingAs($user)
        ->getJson('/api/account-settings/privacy')
        ->assertOk()
        ->assertExactJson(['privacy_preferences' => [
            'followed_organizers' => true,
            'saved_events_count' => true,
        ]]);
});

// ---------------------------------------------------------------------------
// PATCH /api/account-settings/privacy
// ---------------------------------------------------------------------------

test('a user can save both privacy toggles together', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/privacy', [
            'followed_organizers' => true,
            'saved_events_count' => true,
        ])
        ->assertOk();

    expect($user->fresh()->showsPublicly('followed_organizers'))->toBeTrue();
    expect($user->fresh()->showsPublicly('saved_events_count'))->toBeTrue();
});

test('saving privacy preferences does not clobber other keys on the same column', function () {
    $user = User::factory()->create(['privacy_preferences' => ['followed_organizers' => true, 'saved_events_count' => true]]);

    $this->actingAs($user)
        ->patchJson('/api/account-settings/privacy', [
            'followed_organizers' => false,
            'saved_events_count' => true,
        ])
        ->assertOk();

    expect($user->fresh()->privacy_preferences)->toMatchArray([
        'followed_organizers' => false,
        'saved_events_count' => true,
    ]);
});

test('privacy preferences requires both keys as booleans', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/account-settings/privacy', ['followed_organizers' => true])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// GET /api/profile/{user}/public-extras
// ---------------------------------------------------------------------------

test('a user with default privacy settings shows both to other viewers', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($target);
    $organizer = Organizer::factory()->create(['status' => 'p', 'name' => 'Public Org']);
    $organizer->follow();
    $event = \App\Models\Event::factory()->published()->create();
    $event->favorite();

    $response = $this->actingAs($viewer)->getJson("/api/profile/{$target->id}/public-extras")->assertOk();

    expect(collect($response->json('followed_organizers'))->pluck('name'))->toContain('Public Org');
    expect($response->json('saved_events_count'))->toBe(1);
});

test('opting followed organizers out of public visibility hides them from other viewers', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['privacy_preferences' => ['followed_organizers' => false]]);

    $this->actingAs($target);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $organizer->follow();
    $event = \App\Models\Event::factory()->published()->create();
    $event->favorite();

    $response = $this->actingAs($viewer)->getJson("/api/profile/{$target->id}/public-extras")->assertOk();

    expect($response->json('followed_organizers'))->toBeNull();
    // The other toggle wasn't touched, so it stays on the default.
    expect($response->json('saved_events_count'))->toBe(1);
});

test('opting saved events count out of public visibility hides the count from other viewers', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['privacy_preferences' => ['saved_events_count' => false]]);

    $this->actingAs($target);
    $organizer = Organizer::factory()->create(['status' => 'p', 'name' => 'Public Org']);
    $organizer->follow();

    $response = $this->actingAs($viewer)->getJson("/api/profile/{$target->id}/public-extras")->assertOk();

    expect($response->json('saved_events_count'))->toBeNull();
    expect(collect($response->json('followed_organizers'))->pluck('name'))->toContain('Public Org');
});

test('a guest can view public extras, and sees what the target user has not opted out of', function () {
    // The route must NOT require auth — public-extras exists specifically
    // for logged-out visitors on a public profile page (see
    // ProfileExtrasController::publicExtras); privacy is enforced by the
    // per-field opt-out gate, not by blocking guests outright.
    $target = User::factory()->create(['privacy_preferences' => []]);
    $this->actingAs($target);
    $organizer = Organizer::factory()->create(['status' => 'p', 'name' => 'Public Org']);
    $organizer->follow();

    $response = $this->getJson("/api/profile/{$target->id}/public-extras")->assertOk();

    expect(collect($response->json('followed_organizers'))->pluck('name'))->toContain('Public Org');
});

test('a guest sees nothing the target user has explicitly opted out of', function () {
    $target = User::factory()->create(['privacy_preferences' => ['saved_events_count' => false]]);
    $this->actingAs($target);
    $event = \App\Models\Event::factory()->published()->create();
    $event->favorite();

    $response = $this->getJson("/api/profile/{$target->id}/public-extras")->assertOk();

    expect($response->json('saved_events_count'))->toBeNull();
});

// ---------------------------------------------------------------------------
// POST /api/account-settings/privacy/data-request
// ---------------------------------------------------------------------------

test('requesting personal data emails the user and the team', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)
        ->postJson('/api/account-settings/privacy/data-request')
        ->assertOk();

    Mail::assertSent(PersonalDataRequestReceived::class, fn ($mail) => $mail->hasTo('me@example.com'));
    Mail::assertSent(PersonalDataRequestInternalNotice::class, fn ($mail) => $mail->hasTo(config('mail.legal_email')));
});

test('guests cannot request personal data', function () {
    $this->postJson('/api/account-settings/privacy/data-request')->assertUnauthorized();
});
