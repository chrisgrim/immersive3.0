<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\User;

test('only the authenticated users own favorites are returned', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $eventA = Event::factory()->published()->create();
    $eventB = Event::factory()->published()->create();

    $this->actingAs($userA);
    $eventA->favorite();
    $this->actingAs($userB);
    $eventB->favorite();

    $response = $this->actingAs($userA)->getJson('/api/hub/events');

    $response->assertOk();
    $slugs = collect($response->json('events.data'))->pluck('slug');
    expect($slugs)->toContain($eventA->slug);
    expect($slugs)->not->toContain($eventB->slug);
});

test('favorites are ordered most-recently-favorited first', function () {
    $user = User::factory()->create();
    $older = Event::factory()->published()->create(['name' => 'Older favorite']);
    $newer = Event::factory()->published()->create(['name' => 'Newer favorite']);

    $this->actingAs($user);
    $older->favorite();
    $this->travel(1)->minutes();
    $newer->favorite();

    $response = $this->actingAs($user)->getJson('/api/hub/events');

    $slugs = collect($response->json('events.data'))->pluck('slug')->values();
    expect($slugs->first())->toBe($newer->slug);
    expect($slugs->last())->toBe($older->slug);
});

test('a specific-dated event with future shows reports dates left and the next date', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['showtype' => 's']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(5)]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(10)]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(3)]); // already past

    $this->actingAs($user);
    $event->favorite();

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $remaining = collect($response->json('events.data'))->firstWhere('slug', $event->slug)['remaining'];

    expect($remaining['type'])->toBe('dated');
    expect($remaining['count'])->toBe(2);
    expect($remaining['label'])->toContain('2 dates left');
});

test('a specific-dated event with zero future shows reports the run has ended', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['showtype' => 's']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(3)]);

    $this->actingAs($user);
    $event->favorite();

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $remaining = collect($response->json('events.data'))->firstWhere('slug', $event->slug)['remaining'];

    expect($remaining)->toBe(['type' => 'ended', 'label' => 'Run has ended', 'count' => 0]);
});

test('an always-available event reports as ongoing regardless of its sentinel show', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['showtype' => 'a']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addMonths(6)]);

    $this->actingAs($user);
    $event->favorite();

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $remaining = collect($response->json('events.data'))->firstWhere('slug', $event->slug)['remaining'];

    expect($remaining)->toBe(['type' => 'ongoing', 'label' => 'Always available']);
});

test('a favorited event that is no longer published is excluded from the list', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite();
    $event->update(['status' => 'd']);

    $response = $this->actingAs($user)->getJson('/api/hub/events');

    expect(collect($response->json('events.data'))->pluck('slug'))->not->toContain($event->slug);
});

test('a favorited event that is archived is excluded from the list', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite();
    $event->update(['archived' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/events');

    expect(collect($response->json('events.data'))->pluck('slug'))->not->toContain($event->slug);
});

test('a user with no favorites sees an empty list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/hub/events');

    $response->assertOk();
    expect($response->json('events.data'))->toBe([]);
});

test('a guest cannot view the saved events list', function () {
    $this->getJson('/api/hub/events')->assertStatus(401);
});

test('a user can fetch a single favorited events detail, same shape as the list', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite();

    $response = $this->actingAs($user)->getJson("/api/hub/events/{$event->slug}");

    $response->assertOk();
    expect($response->json('event.slug'))->toBe($event->slug);
    expect($response->json('event.isFavorited'))->toBeTrue();
    expect($response->json('event'))->toHaveKey('notifyUpdates');
    expect($response->json('event'))->toHaveKey('remaining');
});

test('fetching a single event 404s if the current user never favorited it', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user)->getJson("/api/hub/events/{$event->slug}")->assertStatus(404);
});

test('fetching a single event 404s if another user favorited it instead', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($owner);
    $event->favorite();

    $this->actingAs($otherUser)->getJson("/api/hub/events/{$event->slug}")->assertStatus(404);
});

test('a guest cannot fetch a single saved event', function () {
    $event = Event::factory()->published()->create();

    $this->getJson("/api/hub/events/{$event->slug}")->assertStatus(401);
});
