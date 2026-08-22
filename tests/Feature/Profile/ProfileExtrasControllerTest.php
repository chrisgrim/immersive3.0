<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

// ---------------------------------------------------------------------------
// GET /api/profile/stats
// ---------------------------------------------------------------------------

test('guests are blocked from viewing profile stats', function () {
    $this->getJson('/api/profile/stats')->assertUnauthorized();
});

test('stats counts only the authenticated users own saved events and followed organizers', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $events = Event::factory()->count(3)->published()->create();
    foreach ($events as $event) {
        $user->favouritedEvents()->attach($event->id);
    }
    // Another user's favorites must not count toward this user's stats.
    $otherUser->favouritedEvents()->attach(Event::factory()->published()->create()->id);

    $organizers = Organizer::factory()->count(2)->create(['status' => 'p']);
    $this->actingAs($user);
    foreach ($organizers as $organizer) {
        $organizer->follow();
    }

    $this->actingAs($user)
        ->getJson('/api/profile/stats')
        ->assertOk()
        ->assertJson(['saved_events_count' => 3, 'followed_organizers_count' => 2]);
});

test('stats excludes an organizer that was deactivated after the user followed it', function () {
    // Regression test: a follow row survives the organizer being
    // rejected/drafted (see FollowController::destroy), but it shouldn't
    // keep inflating the followed-organizers count once that happens.
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($user);
    $organizer->follow();
    $organizer->update(['status' => 'd']);

    $this->actingAs($user)
        ->getJson('/api/profile/stats')
        ->assertOk()
        ->assertJson(['followed_organizers_count' => 0]);
});

// ---------------------------------------------------------------------------
// GET /api/profile/followed-organizers
// ---------------------------------------------------------------------------

test('guests are blocked from viewing followed organizers', function () {
    $this->getJson('/api/profile/followed-organizers')->assertUnauthorized();
});

test('followed organizers only returns organizers the authenticated user follows', function () {
    $user = User::factory()->create();
    $followed = Organizer::factory()->create(['status' => 'p', 'name' => 'Followed Co']);
    $notFollowed = Organizer::factory()->create(['status' => 'p', 'name' => 'Not Followed Co']);
    $this->actingAs($user);
    $followed->follow();

    $response = $this->actingAs($user)
        ->getJson('/api/profile/followed-organizers')
        ->assertOk();

    $names = collect($response->json('organizers'))->pluck('name');
    expect($names)->toContain('Followed Co');
    expect($names)->not->toContain('Not Followed Co');
});

test('followed organizers excludes an organizer that was deactivated after the user followed it', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p', 'name' => 'Rejected Later Co']);
    $this->actingAs($user);
    $organizer->follow();
    $organizer->update(['status' => 'd']);

    $response = $this->actingAs($user)
        ->getJson('/api/profile/followed-organizers')
        ->assertOk();

    expect($response->json('organizers'))->toBeEmpty();
});

test('followed organizers response never leaks sensitive organizer fields', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p', 'email' => 'org-secret@example.com']);
    $this->actingAs($user);
    $organizer->follow();

    $response = $this->actingAs($user)->getJson('/api/profile/followed-organizers')->assertOk();

    expect($response->json('organizers.0'))->toHaveKeys(['id', 'name', 'slug', 'thumbImagePath']);
    expect($response->json('organizers.0'))->not->toHaveKey('email');
});

// ---------------------------------------------------------------------------
// GET /api/profile/{user}/public-extras
// ---------------------------------------------------------------------------

test('a guest can view a public profiles opted-in extras — the route must not require auth', function () {
    // Regression test: this route used to be nested inside the owner-only
    // profile/* group's auth:sanctum middleware, which 401'd every guest —
    // exactly the audience a public profile page is for.
    $owner = User::factory()->create([
        'privacy_preferences' => ['followed_organizers' => true, 'saved_events_count' => true],
    ]);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($owner);
    $organizer->follow();

    $response = $this->getJson("/api/profile/{$owner->id}/public-extras")->assertOk();

    expect($response->json('followed_organizers'))->toHaveCount(1);
    expect($response->json('saved_events_count'))->toBe(0);
});

test('public-extras shows everything the profile owner has not opted out of', function () {
    $owner = User::factory()->create(['privacy_preferences' => []]);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($owner);
    $organizer->follow();

    $response = $this->getJson("/api/profile/{$owner->id}/public-extras")->assertOk();

    expect($response->json('followed_organizers'))->toHaveCount(1);
    expect($response->json('saved_events_count'))->toBe(0);
});

test('public-extras hides a field the profile owner has explicitly opted out of', function () {
    $owner = User::factory()->create(['privacy_preferences' => ['followed_organizers' => false, 'saved_events_count' => false]]);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($owner);
    $organizer->follow();

    $response = $this->getJson("/api/profile/{$owner->id}/public-extras")->assertOk();

    expect($response->json('followed_organizers'))->toBeNull();
    expect($response->json('saved_events_count'))->toBeNull();
});

// ---------------------------------------------------------------------------
// PATCH /api/profile/bio
// ---------------------------------------------------------------------------

test('guests are blocked from updating bio', function () {
    $this->patchJson('/api/profile/bio', ['blurb' => 'hi'])->assertUnauthorized();
});

test('a user can save and clear their own bio', function () {
    $user = User::factory()->create(['blurb' => null]);

    $this->actingAs($user)
        ->patchJson('/api/profile/bio', ['blurb' => 'I love immersive theatre.'])
        ->assertOk();
    expect($user->fresh()->blurb)->toBe('I love immersive theatre.');

    $this->actingAs($user)
        ->patchJson('/api/profile/bio', ['blurb' => null])
        ->assertOk();
    expect($user->fresh()->blurb)->toBeNull();
});

test('bio is capped at 500 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/profile/bio', ['blurb' => str_repeat('a', 501)])
        ->assertStatus(422);
});

test('updating bio never touches another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['blurb' => 'untouched']);

    $this->actingAs($user)
        ->patchJson('/api/profile/bio', ['blurb' => 'mine'])
        ->assertOk();

    expect($other->fresh()->blurb)->toBe('untouched');
});
