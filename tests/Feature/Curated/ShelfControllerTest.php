<?php

use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['type' => 'u']);
    $this->community = Community::factory()->create(['user_id' => $this->owner->id, 'status' => 'p']);

    $this->curator = User::factory()->create(['type' => 'u']);
    $this->community->curators()->attach($this->curator);

    $this->stranger = User::factory()->create(['type' => 'u']);
});

// ----- store() (POST /communities/{community}/shelves) -----

test('store creates a New Shelf at order 0 and shifts existing shelves down', function () {
    $existing = Shelf::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Existing',
        'order' => 0,
    ]);

    $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/shelves")
        ->assertOk();

    // The previously-existing shelf is bumped to order 1.
    expect($existing->fresh()->order)->toBe(1);

    // A fresh shelf named 'New Shelf' now sits at order 0.
    $newShelf = Shelf::where('community_id', $this->community->id)
        ->where('name', 'New Shelf')
        ->first();
    expect($newShelf)->not->toBeNull();
    expect($newShelf->order)->toBe(0);
    expect($newShelf->user_id)->toBe($this->curator->id);
});

test('store returns all shelves each with a paginated posts relation', function () {
    Shelf::factory()->create(['community_id' => $this->community->id, 'order' => 0]);

    $response = $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/shelves")
        ->assertOk();

    // Two shelves total (the existing one + the new one).
    expect($response->json())->toHaveCount(2);
    // Each shelf carries a paginated posts payload (paginator shape).
    expect($response->json('0.posts'))->toHaveKey('per_page');
    expect($response->json('0.posts.per_page'))->toBe(8);
});

test('store is denied to a non-curator', function () {
    $this->actingAs($this->stranger)
        ->postJson("/communities/{$this->community->slug}/shelves")
        ->assertStatus(403);

    $this->assertDatabaseMissing('shelves', [
        'community_id' => $this->community->id,
        'name' => 'New Shelf',
    ]);
});

test('store redirects a guest to login', function () {
    $this->post("/communities/{$this->community->slug}/shelves")
        ->assertRedirect('/login');
});

// ----- update() (PUT /communities/{community}/shelves/{shelf}) -----

test('update renames a shelf and returns it with paginated posts', function () {
    $shelf = Shelf::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->curator)
        ->putJson("/communities/{$this->community->slug}/shelves/{$shelf->id}", [
            'name' => 'Renamed Shelf',
        ])
        ->assertOk();

    expect($response->json('name'))->toBe('Renamed Shelf');
    expect($shelf->fresh()->name)->toBe('Renamed Shelf');

    // note: update() paginates posts with per_page 4 (store uses 8).
    expect($response->json('posts.per_page'))->toBe(4);
});

test('update is denied to a non-curator', function () {
    $shelf = Shelf::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Untouched',
    ]);

    $this->actingAs($this->stranger)
        ->putJson("/communities/{$this->community->slug}/shelves/{$shelf->id}", [
            'name' => 'Hacked',
        ])
        ->assertStatus(403);

    expect($shelf->fresh()->name)->toBe('Untouched');
});

// ----- destroy() (DELETE /communities/{community}/shelves/{shelf}) -----

test('destroy deletes the shelf and returns the communitys first three shelves', function () {
    $toDelete = Shelf::factory()->create(['community_id' => $this->community->id, 'order' => 0]);
    Shelf::factory()->count(4)->create(['community_id' => $this->community->id]);

    $response = $this->actingAs($this->curator)
        ->deleteJson("/communities/{$this->community->slug}/shelves/{$toDelete->id}")
        ->assertOk();

    // note: Shelf has no SoftDeletes trait, so delete() is a hard delete.
    $this->assertDatabaseMissing('shelves', ['id' => $toDelete->id]);

    // destroy() returns at most the first 3 remaining shelves.
    expect($response->json())->toHaveCount(3);
});

test('destroy is denied to a non-curator', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->stranger)
        ->deleteJson("/communities/{$this->community->slug}/shelves/{$shelf->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('shelves', ['id' => $shelf->id]);
});

// ----- order() (POST /communities/{community}/shelves/order) -----

test('order bulk-reorders shelves from the request array', function () {
    $a = Shelf::factory()->create(['community_id' => $this->community->id, 'order' => 0]);
    $b = Shelf::factory()->create(['community_id' => $this->community->id, 'order' => 1]);

    $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/shelves/order", [
            ['id' => $a->id, 'order' => 7],
            ['id' => $b->id, 'order' => 2],
        ])
        ->assertOk();

    expect($a->fresh()->order)->toBe(7);
    expect($b->fresh()->order)->toBe(2);
});

test('order is denied to a non-curator', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id, 'order' => 0]);

    $this->actingAs($this->stranger)
        ->postJson("/communities/{$this->community->slug}/shelves/order", [
            ['id' => $shelf->id, 'order' => 9],
        ])
        ->assertStatus(403);

    expect($shelf->fresh()->order)->toBe(0);
});

// ----- paginate() (GET /communities/{community}/shelves/{shelf}/paginate) -----

test('paginate with type published returns only published, non-hidden posts', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id]);

    Post::factory()->count(2)->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'p',
        'is_hidden' => false,
    ]);
    // These should be excluded by publishedPosts() (draft + hidden).
    Post::factory()->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'd',
        'is_hidden' => false,
    ]);
    Post::factory()->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'p',
        'is_hidden' => true,
    ]);

    $response = $this->actingAs($this->curator)
        ->getJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/paginate?type=published")
        ->assertOk();

    expect($response->json('per_page'))->toBe(8);
    expect($response->json('total'))->toBe(2);
});

test('paginate without published type returns all posts on the shelf', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id]);

    Post::factory()->count(2)->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'p',
    ]);
    Post::factory()->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'd',
    ]);
    Post::factory()->create([
        'community_id' => $this->community->id,
        'shelf_id' => $shelf->id,
        'status' => 'p',
        'is_hidden' => true,
    ]);

    $response = $this->actingAs($this->curator)
        ->getJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/paginate")
        ->assertOk();

    expect($response->json('per_page'))->toBe(8);
    expect($response->json('total'))->toBe(4);
});

test('paginate is denied to a non-curator', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->stranger)
        ->getJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/paginate")
        ->assertStatus(403);
});

// ----- toggleHidden() (PATCH /communities/{community}/shelves/{shelf}/toggle-hidden) -----

test('toggleHidden flips is_hidden and returns JSON', function () {
    $shelf = Shelf::factory()->create([
        'community_id' => $this->community->id,
        'is_hidden' => false,
    ]);

    $this->actingAs($this->curator)
        ->patchJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/toggle-hidden")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_hidden' => true,
            'message' => 'Shelf hidden successfully',
        ]);

    // note: Shelf::is_hidden has no boolean cast, so the stored attribute is int 1/0.
    expect($shelf->fresh()->is_hidden)->toBe(1);

    $this->actingAs($this->curator)
        ->patchJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/toggle-hidden")
        ->assertOk()
        ->assertJsonPath('is_hidden', false)
        ->assertJsonPath('message', 'Shelf shown successfully');

    expect($shelf->fresh()->is_hidden)->toBe(0);
});

test('toggleHidden is denied to a non-curator', function () {
    $shelf = Shelf::factory()->create([
        'community_id' => $this->community->id,
        'is_hidden' => false,
    ]);

    $this->actingAs($this->stranger)
        ->patchJson("/communities/{$this->community->slug}/shelves/{$shelf->id}/toggle-hidden")
        ->assertStatus(403);

    expect($shelf->fresh()->is_hidden)->toBe(0);
});
