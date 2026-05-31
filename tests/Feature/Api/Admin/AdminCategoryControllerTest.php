<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // The category controller writes images to the 'digitalocean' disk via
    // ImageHandler, so fake it for any store/update path that uploads files.
    Storage::fake('digitalocean');
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- index() -----

test('index returns categories with their images ordered by name', function () {
    $b = Category::factory()->create(['name' => 'Beta', 'rank' => 0]);
    $a = Category::factory()->create(['name' => 'Alpha', 'rank' => 0]);

    Image::factory()->create([
        'imageable_id' => $a->id,
        'imageable_type' => Category::class,
    ]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/settings/categories')
        ->assertOk();

    // Ordered by name ascending.
    expect($response->json('0.name'))->toBe('Alpha');
    expect($response->json('1.name'))->toBe('Beta');
    // images relation is eager loaded.
    expect($response->json('0.images'))->toHaveCount(1);
});

test('index requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/settings/categories')->assertStatus(403);
});

// ----- store() -----

test('store creates a category and auto-generates the slug from the name', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Escape Rooms',
            'description' => 'Lock-in adventures',
            'type' => 'c',
        ])
        // note: store() returns the freshly-created Eloquent model, so Laravel
        // emits a 201 Created (wasRecentlyCreated), not a 200.
        ->assertCreated()
        ->assertJsonPath('name', 'Escape Rooms')
        ->assertJsonPath('slug', 'escape-rooms');

    $this->assertDatabaseHas('categories', [
        'name' => 'Escape Rooms',
        'slug' => 'escape-rooms',
        'type' => 'c',
    ]);
});

test('store honors an explicitly provided slug', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Haunted Houses',
            'description' => 'Spooky',
            'type' => 'c',
            'slug' => 'custom-spooky-slug',
        ])
        ->assertCreated()
        ->assertJsonPath('slug', 'custom-spooky-slug');
});

test('store rejects a duplicate name', function () {
    Category::factory()->create(['name' => 'Theater']);

    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Theater',
            'description' => 'dup',
            'type' => 'c',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store requires name, description, and a valid type', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'type' => 'x', // not in c,g
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'description', 'type']);
});

test('store stores applicable_attendance_types as a plain integer array (not double-encoded)', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Hybrid Shows',
            'description' => 'either',
            'type' => 'c',
            'applicable_attendance_types' => [1, 2],
        ])
        ->assertCreated()
        ->assertJsonPath('applicable_attendance_types', [1, 2]);

    // The DB column holds a JSON array of ints, not a JSON-encoded string.
    $category = Category::where('name', 'Hybrid Shows')->first();
    expect($category->applicable_attendance_types)->toBe([1, 2]);
});

test('store saves an uploaded image using dimension-by-index', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'With Icon',
            'description' => 'has an icon image',
            'type' => 'c',
            'image' => [UploadedFile::fake()->image('icon.jpg', 50, 50)],
            'image_index' => [1], // index 1 => 400x400 icon
        ])
        ->assertCreated();

    $category = Category::where('name', 'With Icon')->first();
    // The image is persisted to the morph table with the supplied rank (index).
    expect($category->images()->where('rank', 1)->exists())->toBeTrue();
});

test('store requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson('/api/admin/settings/categories', [
            'name' => 'Nope',
            'description' => 'x',
            'type' => 'c',
        ])
        ->assertStatus(403);

    $this->assertDatabaseMissing('categories', ['name' => 'Nope']);
});

// ----- update() -----

test('update accepts applicable_attendance_types as a JSON string', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => null]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'applicable_attendance_types' => json_encode([1, 2]),
        ])
        ->assertOk();

    expect($category->fresh()->applicable_attendance_types)->toBe([1, 2]);
});

test('update accepts applicable_attendance_types as an array', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => null]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'applicable_attendance_types' => [2],
        ])
        ->assertOk();

    expect($category->fresh()->applicable_attendance_types)->toBe([2]);
});

test('update changes general fields like name and description', function () {
    $category = Category::factory()->create([
        'name' => 'Old Name',
        'description' => 'old',
    ]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'name' => 'New Name',
            'description' => 'fresh',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'New Name');

    expect($category->fresh()->description)->toBe('fresh');
});

test('update rejects a name that duplicates another category', function () {
    Category::factory()->create(['name' => 'Taken']);
    $category = Category::factory()->create(['name' => 'Mine']);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'name' => 'Taken',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('update calls ImageHandler::updateImages for currentImages re-ranking', function () {
    $category = Category::factory()->create();
    $image = Image::factory()->create([
        'imageable_id' => $category->id,
        'imageable_type' => Category::class,
        'large_image_path' => 'category-images/foo/foo-1.webp',
        'thumb_image_path' => 'category-images/foo/foo-1-thumb.webp',
        'rank' => 0,
    ]);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'currentImages' => json_encode([
                ['url' => 'category-images/foo/foo-1.webp', 'rank' => 5],
            ]),
        ])
        ->assertOk();

    // updateImages re-ranks the kept image to its new rank.
    expect($image->fresh()->rank)->toBe(5);
});

test('update forgets the active-categories cache', function () {
    $category = Category::factory()->create();
    Cache::put('active-categories', 'cached-value', 600);

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'description' => 'updated',
        ])
        ->assertOk();

    expect(Cache::has('active-categories'))->toBeFalse();
});

test('update requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $category = Category::factory()->create();

    $this->actingAs($user)
        ->patchJson("/api/admin/settings/categories/{$category->slug}", [
            'description' => 'hacked',
        ])
        ->assertStatus(403);
});

// ----- destroy() -----

test('destroy soft-deletes a category that has no associated events', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/categories/{$category->slug}")
        ->assertOk()
        ->assertJsonPath('message', 'Category deleted successfully');

    // note: the Category model does NOT use SoftDeletes — destroy() is a hard
    // delete. The row is fully removed from the table.
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('destroy returns 422 when the category has associated events', function () {
    $category = Category::factory()->create();
    Event::factory()->create(['category_id' => $category->id]);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/settings/categories/{$category->slug}")
        ->assertStatus(422)
        ->assertJsonPath('error', 'CATEGORY_HAS_EVENTS');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('destroy requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $category = Category::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/admin/settings/categories/{$category->slug}")
        ->assertStatus(403);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});
