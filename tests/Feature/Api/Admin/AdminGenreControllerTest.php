<?php

use App\Models\Genre;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Genre image uploads go to the 'digitalocean' disk via ImageHandler.
    Storage::fake('digitalocean');
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- index() -----

test('index returns paginated genres including those outside the admin scope', function () {
    // The default AdminScope orders admin-first; index() drops it via
    // withoutGlobalScope('admin') so non-admin genres are still returned.
    Genre::factory()->count(2)->create(['admin' => false]);
    Genre::factory()->admin()->count(2)->create();

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/genres')
        ->assertOk();

    expect($response->json('total'))->toBe(4);
    // paginate(40) => per_page is 40.
    expect($response->json('per_page'))->toBe(40);
});

test('index supports searching by name', function () {
    Genre::factory()->create(['name' => 'Immersive Theater']);
    Genre::factory()->create(['name' => 'Escape Game']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/genres?search=Immersive')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.name'))->toBe('Immersive Theater');
});

test('index filters by the admin boolean', function () {
    Genre::factory()->admin()->count(3)->create();
    Genre::factory()->create(['admin' => false]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/genres?type=1')
        ->assertOk();

    expect($response->json('total'))->toBe(3);
});

test('index supports sorting by rank', function () {
    Genre::factory()->create(['name' => 'Low', 'rank' => 1]);
    Genre::factory()->create(['name' => 'High', 'rank' => 9]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/genres?sort_field=rank&sort_direction=desc')
        ->assertOk();

    expect($response->json('data.0.name'))->toBe('High');
    expect($response->json('data.1.name'))->toBe('Low');
});

test('index requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/settings/genres')->assertStatus(403);
});

// ----- store() -----

test('store creates a genre with a slug derived from the name', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/genres', [
            'name' => 'Promenade Theatre',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Promenade Theatre')
        ->assertJsonPath('slug', 'promenade-theatre');

    $this->assertDatabaseHas('genres', [
        'name' => 'Promenade Theatre',
        'slug' => 'promenade-theatre',
        'user_id' => $this->moderator->id,
    ]);
});

test('store defaults admin to true when not provided', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/genres', [
            'name' => 'Defaults Admin',
        ])
        ->assertCreated()
        // note: Genre has no boolean cast for `admin`, so it serializes as the
        // raw int 1 (truthy) rather than a JSON `true`.
        ->assertJsonPath('admin', 1);
});

test('store rejects a duplicate name', function () {
    Genre::factory()->create(['name' => 'Dupe Genre']);

    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/genres', [
            'name' => 'Dupe Genre',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store requires a name', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/genres', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store accepts an optional 400x400 image', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/genres', [
            'name' => 'With Image',
            'image' => UploadedFile::fake()->image('g.jpg', 50, 50),
        ])
        ->assertCreated();

    $genre = Genre::withoutGlobalScope('admin')->where('name', 'With Image')->first();
    expect($genre->images()->exists())->toBeTrue();
});

test('store requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson('/api/admin/settings/genres', ['name' => 'Nope'])
        ->assertStatus(403);

    $this->assertDatabaseMissing('genres', ['name' => 'Nope']);
});

// ----- update() -----

test('update with only rank changes the rank', function () {
    $genre = Genre::factory()->create(['rank' => 0, 'name' => 'Keep Name']);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", ['rank' => 7])
        ->assertOk();

    $fresh = $genre->fresh();
    expect($fresh->rank)->toBe(7);
    expect($fresh->name)->toBe('Keep Name');
});

test('update with only admin changes the admin flag', function () {
    $genre = Genre::factory()->create(['admin' => false]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", ['admin' => true])
        ->assertOk();

    expect((bool) $genre->fresh()->admin)->toBeTrue();
});

test('update with only name re-slugs the genre', function () {
    $genre = Genre::factory()->create(['name' => 'Before', 'slug' => 'before-x']);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", ['name' => 'After Name'])
        ->assertOk()
        ->assertJsonPath('name', 'After Name')
        ->assertJsonPath('slug', 'after-name');
});

test('update name-only rejects a duplicate name', function () {
    Genre::factory()->create(['name' => 'Existing Genre']);
    $genre = Genre::factory()->create(['name' => 'Original']);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", ['name' => 'Existing Genre'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('update image-only branch replaces the genre image', function () {
    $genre = Genre::factory()->create();
    // Seed an existing image whose path is deletable by ImageHandler
    // (path must look like {type}-images/{slug}/{file}).
    Image::factory()->create([
        'imageable_id' => $genre->id,
        'imageable_type' => Genre::class,
        'large_image_path' => 'genre-images/old/old-1.webp',
        'thumb_image_path' => 'genre-images/old/old-1-thumb.webp',
        'rank' => 0,
    ]);
    // The image being "present" in storage lets deleteImage clean it up.
    Storage::disk('digitalocean')->put('/public/genre-images/old/old-1.webp', 'x');

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/settings/genres/{$genre->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('new.jpg', 50, 50),
        ])
        ->assertOk();

    // Old image replaced by exactly one new image.
    expect($genre->fresh()->images()->count())->toBe(1);
    expect($genre->fresh()->images()->first()->large_image_path)->not->toBe('genre-images/old/old-1.webp');
});

test('update full branch updates rank, name, and admin together', function () {
    $genre = Genre::factory()->create(['name' => 'Full Old', 'rank' => 0, 'admin' => false]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", [
            'name' => 'Full New',
            'rank' => 3,
            'admin' => true,
        ])
        ->assertOk();

    $fresh = $genre->fresh();
    expect($fresh->name)->toBe('Full New');
    expect($fresh->rank)->toBe(3);
    expect((bool) $fresh->admin)->toBeTrue();
    expect($fresh->slug)->toBe('full-new');
});

test('update requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $genre = Genre::factory()->create();

    $this->actingAs($user)
        ->patchJson("/api/admin/settings/genres/{$genre->id}", ['rank' => 5])
        ->assertStatus(403);
});

// ----- destroy() -----

test('destroy removes the genre', function () {
    $genre = Genre::factory()->create();

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/genres/{$genre->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Genre deleted successfully');

    // note: Genre does NOT use SoftDeletes — this is a hard delete.
    $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
});

test('destroy is blocked when the genre has associated events', function () {
    $genre = Genre::factory()->create();
    $event = \App\Models\Event::factory()->create();
    $genre->events()->attach($event->id);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/genres/{$genre->id}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'GENRE_HAS_EVENTS');

    // The genre is preserved so the event_genre pivot rows aren't orphaned.
    $this->assertDatabaseHas('genres', ['id' => $genre->id]);
});

test('destroy requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $genre = Genre::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/admin/settings/genres/{$genre->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('genres', ['id' => $genre->id]);
});
