<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

// Helper: an organizer + member who can host/manage events on it.
function memberOf(Organizer $organizer, string $type = 'u'): User
{
    $user = User::factory()->create(['type' => $type]);
    $organizer->users()->attach($user->id, ['role' => 'member']);
    return $user->fresh();
}

// ----- submit() — web route POST /hosting/event/{event}/submit -----

test('submit transitions a draft event to under-review', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertOk();

    expect($event->fresh()->status)->toBe('r');
});

test('submit rejects an already-submitted event with 422', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'r']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(422);

    expect($event->fresh()->status)->toBe('r');
});

test('submit rejects a published event with 422', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(422);
});

test('submit is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    // Stranger has no teams → fails the host gate first (403).
    $this->actingAs($stranger)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(403);
});

test('submit is denied to guests (302 to login)', function () {
    $event = Event::factory()->create();
    $this->postJson(route('hosting.event.submit', $event))->assertStatus(401);
});

// ----- destroy() — web route DELETE /hosting/event/{event} -----

test('destroy soft-deletes the event', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->deleteJson(route('hosting.event.destroy', $event))
        ->assertOk();

    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id))->not->toBeNull();
});

test('destroy is denied to non-members', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->deleteJson(route('hosting.event.destroy', $event))
        ->assertStatus(403);

    expect(Event::find($event->id))->not->toBeNull();
});

// ----- create() — web route POST /hosting/event/create -----

test('create rejects users who have no teams', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(403);
});

test('create returns 409 when a duplicate name exists without acknowledgement', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->create(['name' => 'Spooky Dinner Theater']);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), [
            'organizer_id' => $organizer->id,
            'name' => 'Spooky Dinner Theater',
        ])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'duplicateEvents', 'warning']);
});

test('create accepts a duplicate name when acknowledge_duplicate=1', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->create(['name' => 'Spooky Dinner Theater']);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), [
            'organizer_id' => $organizer->id,
            'name' => 'Spooky Dinner Theater',
            'acknowledge_duplicate' => 1,
        ])
        ->assertStatus(201);
});

test('create blocks non-admins at 5 unpublished events', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->count(5)->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
    ]);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(422);
});

test('admins bypass the 5-unpublished limit', function () {
    $organizer = Organizer::factory()->create();
    $admin = memberOf($organizer, 'a');

    Event::factory()->count(5)->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
    ]);

    $this->actingAs($admin)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(201);
});

// ----- update() — api route POST /api/hosting/event/{event} -----

test('update applies a description change for an organizer member', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'description' => 'Original.',
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Brand new copy.'])
        ->assertOk();

    expect($event->fresh()->description)->toBe('Brand new copy.');
});

test('update is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Hijack'])
        ->assertStatus(403);
});

test('update returns 409 on duplicate name without acknowledgement', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Original']);
    Event::factory()->create(['name' => 'Conflicting Name']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Conflicting Name'])
        ->assertStatus(409);

    expect($event->fresh()->name)->toBe('Original');
});

// ----- duplicate() — api route POST /api/events/{event}/duplicate -----

test('duplicate creates a new event for an organizer member', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'name' => 'Source Event',
    ]);
    $user = memberOf($organizer);

    $before = Event::count();

    $this->actingAs($user)
        ->postJson("/api/events/{$event->slug}/duplicate")
        ->assertStatus(201);

    expect(Event::count())->toBe($before + 1);
});

test('duplicate is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/api/events/{$event->slug}/duplicate")
        ->assertStatus(403);
});
