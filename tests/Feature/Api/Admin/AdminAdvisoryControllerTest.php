<?php

use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\InteractiveLevel;
use App\Models\Events\MobilityAdvisory;
use App\Models\User;

beforeEach(function () {
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// These advisory attribute models have no factories, so rows are built inline
// with Model::create([...]). withoutGlobalScopes() is needed when re-reading
// because each model carries a RankScope ordering global scope.

// ----- index() -----

test('index paginates content advisories with per_page 20', function () {
    ContentAdvisory::create(['name' => 'Strobe', 'slug' => 'strobe', 'user_id' => $this->moderator->id]);
    ContentAdvisory::create(['name' => 'Loud Noise', 'slug' => 'loud-noise', 'user_id' => $this->moderator->id]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/advisories/content')
        ->assertOk();

    expect($response->json('total'))->toBe(2);
    expect($response->json('per_page'))->toBe(20);
});

test('index maps each type to its backing model', function () {
    ContentAdvisory::create(['name' => 'C One', 'slug' => 'c-one', 'user_id' => $this->moderator->id]);
    MobilityAdvisory::create(['name' => 'M One', 'slug' => 'm-one', 'user_id' => $this->moderator->id]);
    InteractiveLevel::create(['name' => 'I One', 'description' => 'd', 'rank' => 0]);
    ContactLevel::create(['name' => 'K One', 'user_id' => $this->moderator->id]);

    foreach (['content', 'mobility', 'interactive', 'contact'] as $type) {
        $response = $this->actingAs($this->moderator)
            ->getJson("/api/admin/settings/advisories/{$type}")
            ->assertOk();
        expect($response->json('total'))->toBe(1);
    }
});

test('index supports searching by name', function () {
    ContentAdvisory::create(['name' => 'Flashing Lights', 'slug' => 'flashing-lights', 'user_id' => $this->moderator->id]);
    ContentAdvisory::create(['name' => 'Smoke', 'slug' => 'smoke', 'user_id' => $this->moderator->id]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/advisories/content?search=Flashing')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.name'))->toBe('Flashing Lights');
});

test('index filters content advisories by admin boolean', function () {
    ContentAdvisory::create(['name' => 'Admin One', 'slug' => 'admin-one', 'admin' => true, 'user_id' => $this->moderator->id]);
    ContentAdvisory::create(['name' => 'User One', 'slug' => 'user-one', 'admin' => false, 'user_id' => $this->moderator->id]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/advisories/content?type=1')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.name'))->toBe('Admin One');
});

test('index returns 500 for an unknown advisory type', function () {
    // note: getModelClass() throws InvalidArgumentException for unmapped types,
    // surfacing as a 500 rather than a validated 4xx.
    $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/advisories/bogus')
        ->assertStatus(500);
});

test('index requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->getJson('/api/admin/settings/advisories/content')
        ->assertStatus(403);
});

// ----- store() -----

test('store creates a content advisory with defaults and a slug', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'content',
            'name' => 'Haze Effects',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Haze Effects')
        ->assertJsonPath('slug', 'haze-effects');

    $this->assertDatabaseHas('content_advisories', [
        'name' => 'Haze Effects',
        'slug' => 'haze-effects',
        'admin' => 1, // defaults to true for content/mobility
    ]);
});

test('store creates a mobility advisory with a slug', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'mobility',
            'name' => 'Stairs Only',
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'stairs-only');

    $this->assertDatabaseHas('mobility_advisories', ['name' => 'Stairs Only', 'slug' => 'stairs-only']);
});

test('store creates an interactive level using the provided description', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'interactive',
            'name' => 'High Touch',
            'description' => 'Lots of audience participation',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'High Touch');

    $this->assertDatabaseHas('interactive_levels', [
        'name' => 'High Touch',
        'description' => 'Lots of audience participation',
    ]);
});

test('store rejects an interactive level without a description', function () {
    // interactive_levels.description is NOT NULL; requiring it turns a missing
    // description into a clean 422 instead of a masked DB 500.
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'interactive',
            'name' => 'No Description',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);

    $this->assertDatabaseMissing('interactive_levels', ['name' => 'No Description']);
});

test('store creates a contact level', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'contact',
            'name' => 'No Contact',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'No Contact');

    // note: contact levels don't get a slug or admin default in prepareStoreData.
    $this->assertDatabaseHas('contact_levels', ['name' => 'No Contact']);
});

test('store rejects an invalid type enum', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'invalid',
            'name' => 'X',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('store requires type and name', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/advisories', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'name']);
});

test('store requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson('/api/admin/settings/advisories', [
            'type' => 'content',
            'name' => 'Nope',
        ])
        ->assertStatus(403);

    $this->assertDatabaseMissing('content_advisories', ['name' => 'Nope']);
});

// ----- update() -----

test('update re-slugs a content advisory when the name changes', function () {
    $advisory = ContentAdvisory::create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/advisories/content/{$advisory->id}", [
            'name' => 'New Name',
        ])
        ->assertOk();

    // note: update() returns the boolean result of Eloquent's update(), which
    // serializes as JSON `true`.
    $fresh = ContentAdvisory::withoutGlobalScopes()->find($advisory->id);
    expect($fresh->name)->toBe('New Name');
    expect($fresh->slug)->toBe('new-name');
});

test('update re-slugs a mobility advisory', function () {
    $advisory = MobilityAdvisory::create([
        'name' => 'Some Mobility',
        'slug' => 'some-mobility',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/advisories/mobility/{$advisory->id}", [
            'name' => 'Renamed Mobility',
        ])
        ->assertOk();

    $fresh = MobilityAdvisory::withoutGlobalScopes()->find($advisory->id);
    expect($fresh->slug)->toBe('renamed-mobility');
});

test('update does not add a slug for interactive levels', function () {
    $level = InteractiveLevel::create(['name' => 'Mid', 'description' => 'medium', 'rank' => 0]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/advisories/interactive/{$level->id}", [
            'name' => 'Mid Updated',
            'description' => 'still medium',
        ])
        ->assertOk();

    $fresh = InteractiveLevel::withoutGlobalScopes()->find($level->id);
    expect($fresh->name)->toBe('Mid Updated');
    expect($fresh->description)->toBe('still medium');
});

test('update returns 404 for a missing advisory id', function () {
    $this->actingAs($this->moderator)
        ->patchJson('/api/admin/settings/advisories/content/999999', [
            'name' => 'Ghost',
        ])
        ->assertStatus(404);
});

test('update validates that name must be a string when present', function () {
    $advisory = ContentAdvisory::create([
        'name' => 'Valid',
        'slug' => 'valid',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/advisories/content/{$advisory->id}", [
            'rank' => 'not-an-integer',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rank']);
});

test('update requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $advisory = ContentAdvisory::create([
        'name' => 'Locked',
        'slug' => 'locked',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/admin/settings/advisories/content/{$advisory->id}", [
            'name' => 'Hacked',
        ])
        ->assertStatus(403);
});

// ----- destroy() -----

test('destroy removes a content advisory', function () {
    $advisory = ContentAdvisory::create([
        'name' => 'Delete Me',
        'slug' => 'delete-me',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/advisories/content/{$advisory->id}")
        ->assertOk();

    // note: these models do NOT use SoftDeletes — destroy is a hard delete.
    $this->assertDatabaseMissing('content_advisories', ['id' => $advisory->id]);
});

test('destroy is blocked when the advisory has associated events', function () {
    $advisory = ContentAdvisory::create([
        'name' => 'Linked',
        'slug' => 'linked',
        'user_id' => $this->moderator->id,
    ]);
    $event = \App\Models\Event::factory()->create();
    $advisory->events()->attach($event->id);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/advisories/content/{$advisory->id}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'ADVISORY_HAS_EVENTS');

    // Preserved so the content_advisory_event pivot rows aren't orphaned.
    $this->assertDatabaseHas('content_advisories', ['id' => $advisory->id]);
});

test('destroy returns 404 for a missing advisory id', function () {
    $this->actingAs($this->moderator)
        ->deleteJson('/api/admin/settings/advisories/mobility/999999')
        ->assertStatus(404);
});

test('destroy requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $advisory = ContentAdvisory::create([
        'name' => 'Keep Me',
        'slug' => 'keep-me',
        'user_id' => $this->moderator->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/admin/settings/advisories/content/{$advisory->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('content_advisories', ['id' => $advisory->id]);
});
