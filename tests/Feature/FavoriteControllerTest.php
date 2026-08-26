<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

test('an authenticated user can favorite an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user)
        ->postJson("/api/events/{$event->slug}/favorite")
        ->assertOk()
        ->assertJson(['isFavorited' => true]);

    $this->assertDatabaseHas('favorites', [
        'user_id' => $user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);
});

test('favoriting twice via the endpoint is idempotent — no duplicate row', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user)->postJson("/api/events/{$event->slug}/favorite")->assertOk();
    $this->actingAs($user)->postJson("/api/events/{$event->slug}/favorite")->assertOk();

    $this->assertDatabaseCount('favorites', 1);
});

test('a non-published event cannot be favorited', function () {
    // Regression guard: Event's own global scope (LatestPublishedFirstScope) only adds
    // an ORDER BY, it does NOT filter by status despite the name — without
    // an explicit check, a draft/embargoed/rejected event's slug could be
    // favorited directly, matching FollowController::store()'s equivalent
    // guard for organizers.
    $user = User::factory()->create();
    $draft = Event::factory()->create(['status' => 'd']);

    $this->actingAs($user)
        ->postJson("/api/events/{$draft->slug}/favorite")
        ->assertNotFound();

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $user->id,
        'favorited_id' => $draft->id,
        'favorited_type' => Event::class,
    ]);
});

test('an authenticated user can unfavorite an event', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user)->postJson("/api/events/{$event->slug}/favorite")->assertOk();

    $this->actingAs($user)
        ->deleteJson("/api/events/{$event->slug}/favorite")
        ->assertOk()
        ->assertJson(['isFavorited' => false]);

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);
});

test('unfavoriting when nothing exists is a no-op, not an error', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/events/{$event->slug}/favorite")
        ->assertOk()
        ->assertJson(['isFavorited' => false]);
});

test('removing the last favorited event from an organizer unfollows that organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();
    expect($organizer->isFollowed())->toBeTrue();

    $this->actingAs($user)->deleteJson("/api/events/{$event->slug}/favorite")->assertOk();

    expect($organizer->isFollowed())->toBeFalse();
});

test('removing one of several favorited events from an organizer keeps the follow', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $eventA = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    $eventB = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $eventA->favorite();
    $eventB->favorite();
    $organizer->follow();

    $this->actingAs($user)->deleteJson("/api/events/{$eventA->slug}/favorite")->assertOk();

    expect($organizer->isFollowed())->toBeTrue();
});

test('removing a favorited event with no organizer does not error', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['organizer_id' => null]);

    $this->actingAs($user);
    $event->favorite();

    $this->actingAs($user)
        ->deleteJson("/api/events/{$event->slug}/favorite")
        ->assertOk()
        ->assertJson(['isFavorited' => false]);
});

test('removing a favorited event the user never followed the organizer for does not error', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite(); // never followed the organizer

    $this->actingAs($user)
        ->deleteJson("/api/events/{$event->slug}/favorite")
        ->assertOk()
        ->assertJson(['isFavorited' => false]);
});

test('a guest cannot favorite an event', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/events/{$event->slug}/favorite")->assertStatus(401);
    $this->assertDatabaseCount('favorites', 0);
});

test('a guest cannot unfavorite an event', function () {
    $event = Event::factory()->create();

    $this->deleteJson("/api/events/{$event->slug}/favorite")->assertStatus(401);
});
