<?php

use App\Models\Event;
use App\Models\User;
use App\Notifications\SavedEventNewDatesNotification;

// ---------------------------------------------------------------------------
// GET /api/notifications
// ---------------------------------------------------------------------------

test('guests are blocked from viewing the notification feed', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

test('a user with no notifications sees an empty feed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

    expect($response->json('notifications.data'))->toBe([]);
});

test('a notification lands in the feed even when the user has mail off, and only for that user', function () {
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => false]]);
    $other = User::factory()->create();
    $event = Event::factory()->published()->create(['name' => 'Feed Test Event']);

    $user->notify(new SavedEventNewDatesNotification($event));

    $mine = $this->actingAs($user)->getJson('/api/notifications')->assertOk();
    expect($mine->json('notifications.data'))->toHaveCount(1);
    expect($mine->json('notifications.data.0.data.event_name'))->toBe('Feed Test Event');

    $theirs = $this->actingAs($other)->getJson('/api/notifications')->assertOk();
    expect($theirs->json('notifications.data'))->toBe([]);
});

test('the feed is ordered most recent first', function () {
    $user = User::factory()->create();
    $older = Event::factory()->published()->create(['name' => 'Older']);
    $newer = Event::factory()->published()->create(['name' => 'Newer']);

    $user->notify(new SavedEventNewDatesNotification($older));
    $this->travel(1)->minutes();
    $user->notify(new SavedEventNewDatesNotification($newer));

    $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();
    $names = collect($response->json('notifications.data'))->pluck('data.event_name');
    expect($names->first())->toBe('Newer');
    expect($names->last())->toBe('Older');
});

// ---------------------------------------------------------------------------
// POST /api/notifications/{id}/read
// ---------------------------------------------------------------------------

test('a user can mark their own notification as read', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $user->notify(new SavedEventNewDatesNotification($event));
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)
        ->postJson("/api/notifications/{$id}/read")
        ->assertOk();

    expect($user->notifications()->first()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $event = Event::factory()->published()->create();
    $other->notify(new SavedEventNewDatesNotification($event));
    $id = $other->notifications()->first()->id;

    $this->actingAs($user)
        ->postJson("/api/notifications/{$id}/read")
        ->assertNotFound();

    expect($other->notifications()->first()->read_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// POST /api/notifications/read-all
// ---------------------------------------------------------------------------

test('mark-all-read only touches the current users own notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $eventA = Event::factory()->published()->create();
    $eventB = Event::factory()->published()->create();
    $user->notify(new SavedEventNewDatesNotification($eventA));
    $user->notify(new SavedEventNewDatesNotification($eventB));
    $other->notify(new SavedEventNewDatesNotification($eventA));

    $this->actingAs($user)
        ->postJson('/api/notifications/read-all')
        ->assertOk();

    expect($user->notifications()->whereNull('read_at')->count())->toBe(0);
    expect($other->notifications()->whereNull('read_at')->count())->toBe(1);
});
