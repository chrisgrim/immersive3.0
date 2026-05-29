<?php

use App\Models\Admin\Dock;
use App\Models\Curated\Card;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- index() -----

test('index returns docks ordered by order ascending', function () {
    Dock::factory()->create(['order' => 2, 'name' => 'second']);
    Dock::factory()->create(['order' => 0, 'name' => 'first']);
    Dock::factory()->create(['order' => 1, 'name' => 'middle']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/docks')
        ->assertOk();

    expect(collect($response->json())->pluck('name')->all())
        ->toBe(['first', 'middle', 'second']);
});

test('index eager-loads shelves, posts and cards relations', function () {
    Dock::factory()->create();

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/docks')
        ->assertOk();

    expect($response->json('0'))->toHaveKeys(['shelves', 'posts', 'cards']);
});

test('index is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/docks')->assertStatus(403);
});

test('index requires authentication', function () {
    $this->getJson('/api/admin/docks')->assertStatus(401);
});

// ----- store() -----

test('store creates a dock for the authenticated admin and returns ordered docks', function () {
    $response = $this->actingAs($this->moderator)
        ->postJson('/api/admin/docks', [
            'name' => 'Featured',
            'type' => 'f',
            'location' => 'home',
            'order' => 0,
        ])
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    $this->assertDatabaseHas('docks', [
        'name' => 'Featured',
        'type' => 'f',
        'location' => 'home',
        'user_id' => $this->moderator->id,
    ]);
});

test('store validates type against the allowed set', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/docks', [
            'type' => 'z',
            'location' => 'home',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('store validates location against the allowed set', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/docks', [
            'type' => 'f',
            'location' => 'sidebar',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['location']);
});

test('store requires type and location', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/docks', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'location']);
});

test('store accepts every allowed type and location value', function () {
    foreach (['f', 't', 'i', 'h', 's', 'p'] as $type) {
        $this->actingAs($this->moderator)
            ->postJson('/api/admin/docks', ['type' => $type, 'location' => 'none'])
            ->assertOk();
    }
    foreach (['home', 'search', 'none'] as $location) {
        $this->actingAs($this->moderator)
            ->postJson('/api/admin/docks', ['type' => 'f', 'location' => $location])
            ->assertOk();
    }

    expect(Dock::count())->toBe(9);
});

test('store is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson('/api/admin/docks', ['type' => 'f', 'location' => 'home'])
        ->assertStatus(403);

    expect(Dock::count())->toBe(0);
});

// ----- update() -----

test('update changes dock attributes', function () {
    $dock = Dock::factory()->create(['type' => 'f', 'location' => 'home', 'name' => 'old']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}", [
            'name' => 'renamed',
            'type' => 's',
            'location' => 'search',
            'order' => 3,
        ])
        ->assertOk();

    $dock->refresh();
    expect($dock->name)->toBe('renamed');
    expect($dock->type)->toBe('s');
    expect($dock->location)->toBe('search');
    expect($dock->order)->toBe(3);
});

test('update validates type', function () {
    $dock = Dock::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}", [
            'type' => 'zzz',
            'location' => 'home',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

test('update is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $dock = Dock::factory()->create(['name' => 'keep']);

    $this->actingAs($user)
        ->postJson("/api/admin/docks/{$dock->id}", [
            'name' => 'hacked',
            'type' => 'f',
            'location' => 'home',
        ])
        ->assertStatus(403);

    expect($dock->fresh()->name)->toBe('keep');
});

// ----- destroy() -----

test('destroy detaches all associations and deletes the dock', function () {
    $dock = Dock::factory()->create();
    $shelf = Shelf::factory()->create();
    $dock->shelves()->attach($shelf->id);

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(1);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/docks/{$dock->id}")
        ->assertOk();

    $this->assertDatabaseMissing('docks', ['id' => $dock->id]);
    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(0);
});

test('destroy is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $dock = Dock::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/admin/docks/{$dock->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('docks', ['id' => $dock->id]);
});

// ----- toggleShelf() -----

test('toggleShelf attach detaches other associations first then attaches the shelf', function () {
    $dock = Dock::factory()->create();
    $existingPost = Post::factory()->create();
    $dock->posts()->attach($existingPost->id);
    $shelf = Shelf::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/shelves", [
            'shelf_id' => $shelf->id,
            'action' => 'attach',
        ])
        ->assertOk();

    // The previously-attached post association is gone; only the shelf remains.
    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(1);
    $this->assertDatabaseHas('associations', [
        'dock_id' => $dock->id,
        'association_id' => $shelf->id,
        'association_type' => Shelf::class,
    ]);
    $this->assertDatabaseMissing('associations', [
        'dock_id' => $dock->id,
        'association_id' => $existingPost->id,
        'association_type' => Post::class,
    ]);
});

test('toggleShelf detach just removes that shelf', function () {
    $dock = Dock::factory()->create();
    $shelf = Shelf::factory()->create();
    $dock->shelves()->attach($shelf->id);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/shelves", [
            'shelf_id' => $shelf->id,
            'action' => 'detach',
        ])
        ->assertOk();

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(0);
});

test('toggleShelf validates shelf_id exists and action', function () {
    $dock = Dock::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/shelves", [
            'shelf_id' => 99999,
            'action' => 'sideways',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shelf_id', 'action']);
});

test('toggleShelf is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $dock = Dock::factory()->create();
    $shelf = Shelf::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/admin/docks/{$dock->id}/shelves", [
            'shelf_id' => $shelf->id,
            'action' => 'attach',
        ])
        ->assertStatus(403);
});

// ----- togglePost() -----

test('togglePost attach detaches other associations first then attaches the post', function () {
    $dock = Dock::factory()->create();
    $existingShelf = Shelf::factory()->create();
    $dock->shelves()->attach($existingShelf->id);
    $post = Post::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/posts", [
            'post_id' => $post->id,
            'action' => 'attach',
        ])
        ->assertOk();

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(1);
    $this->assertDatabaseHas('associations', [
        'dock_id' => $dock->id,
        'association_id' => $post->id,
        'association_type' => Post::class,
    ]);
});

test('togglePost detach removes the post', function () {
    $dock = Dock::factory()->create();
    $post = Post::factory()->create();
    $dock->posts()->attach($post->id);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/posts", [
            'post_id' => $post->id,
            'action' => 'detach',
        ])
        ->assertOk();

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(0);
});

test('togglePost validates post_id and action', function () {
    $dock = Dock::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/posts", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['post_id', 'action']);
});

// ----- toggleCard() -----

test('toggleCard attach detaches other associations first then attaches the card', function () {
    $dock = Dock::factory()->create();
    $existingShelf = Shelf::factory()->create();
    $dock->shelves()->attach($existingShelf->id);
    $card = Card::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/cards", [
            'card_id' => $card->id,
            'action' => 'attach',
        ])
        ->assertOk();

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(1);
    $this->assertDatabaseHas('associations', [
        'dock_id' => $dock->id,
        'association_id' => $card->id,
        'association_type' => Card::class,
    ]);
});

test('toggleCard detach removes the card', function () {
    $dock = Dock::factory()->create();
    $card = Card::factory()->create();
    $dock->cards()->attach($card->id);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/cards", [
            'card_id' => $card->id,
            'action' => 'detach',
        ])
        ->assertOk();

    expect(DB::table('associations')->where('dock_id', $dock->id)->count())->toBe(0);
});

test('toggleCard validates card_id and action', function () {
    $dock = Dock::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/docks/{$dock->id}/cards", [
            'card_id' => 88888,
            'action' => 'nope',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['card_id', 'action']);
});

// ----- getAvailableShelves() / getAvailablePosts() / getAvailableCommunities() -----

test('getAvailableShelves returns shelves ordered by name', function () {
    Shelf::factory()->create(['name' => 'Zebra']);
    Shelf::factory()->create(['name' => 'Apple']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/docks/available-shelves')
        ->assertOk();

    expect(collect($response->json())->pluck('name')->all())
        ->toBe(['Apple', 'Zebra']);
});

test('getAvailablePosts returns posts ordered by name', function () {
    Post::factory()->create(['name' => 'Zeta']);
    Post::factory()->create(['name' => 'Alpha']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/docks/available-posts')
        ->assertOk();

    expect(collect($response->json())->pluck('name')->all())
        ->toBe(['Alpha', 'Zeta']);
});

test('getAvailableCommunities returns id and name ordered by name', function () {
    Community::factory()->create(['name' => 'Zoo Club']);
    Community::factory()->create(['name' => 'Art House']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/docks/available-communities')
        ->assertOk();

    expect(collect($response->json())->pluck('name')->all())
        ->toBe(['Art House', 'Zoo Club']);
    expect(array_keys($response->json('0')))->toBe(['id', 'name']);
});

test('available-shelves is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/docks/available-shelves')->assertStatus(403);
});
