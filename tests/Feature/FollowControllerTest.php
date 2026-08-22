<?php

use App\Models\Organizer;
use App\Models\User;

test('an authenticated user can follow an organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/organizers/{$organizer->slug}/follow")
        ->assertOk()
        ->assertJson(['isFollowed' => true]);

    $this->assertDatabaseHas('organizer_followers', [
        'user_id' => $user->id,
        'organizer_id' => $organizer->id,
    ]);
});

test('following twice via the endpoint is idempotent — no duplicate row', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)->postJson("/api/organizers/{$organizer->slug}/follow")->assertOk();
    $this->actingAs($user)->postJson("/api/organizers/{$organizer->slug}/follow")->assertOk();

    $this->assertDatabaseCount('organizer_followers', 1);
});

test('an authenticated user can unfollow an organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)->postJson("/api/organizers/{$organizer->slug}/follow")->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/organizers/{$organizer->slug}/follow")
        ->assertOk()
        ->assertJson(['isFollowed' => false]);

    $this->assertDatabaseMissing('organizer_followers', [
        'user_id' => $user->id,
        'organizer_id' => $organizer->id,
    ]);
});

test('unfollowing when nothing exists is a no-op, not an error', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/organizers/{$organizer->slug}/follow")
        ->assertOk()
        ->assertJson(['isFollowed' => false]);
});

test('a guest cannot follow an organizer', function () {
    $organizer = Organizer::factory()->create();

    $this->postJson("/api/organizers/{$organizer->slug}/follow")->assertStatus(401);
    $this->assertDatabaseCount('organizer_followers', 0);
});

test('a guest cannot unfollow an organizer', function () {
    $organizer = Organizer::factory()->create();

    $this->deleteJson("/api/organizers/{$organizer->slug}/follow")->assertStatus(401);
});

test('following a deactivated organizer 404s', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'd']);

    $this->actingAs($user)
        ->postJson("/api/organizers/{$organizer->slug}/follow")
        ->assertStatus(404);

    $this->assertDatabaseCount('organizer_followers', 0);
});

test('a user can still unfollow an organizer that was deactivated after they followed it', function () {
    // Regression test: destroy() used to 404 on any non-published organizer,
    // same as store()'s guard against creating a NEW follow — but that left
    // an already-existing follow row permanently stuck the moment the
    // organizer was rejected/drafted, since it could never be removed again.
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);

    $this->actingAs($user)->postJson("/api/organizers/{$organizer->slug}/follow")->assertOk();
    $organizer->update(['status' => 'd']);

    $this->actingAs($user)
        ->deleteJson("/api/organizers/{$organizer->slug}/follow")
        ->assertOk()
        ->assertJson(['isFollowed' => false]);

    $this->assertDatabaseMissing('organizer_followers', [
        'user_id' => $user->id,
        'organizer_id' => $organizer->id,
    ]);
});
