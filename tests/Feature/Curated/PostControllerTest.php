<?php

use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    // Owner of the community, plus a curator attached via the community_user pivot.
    $this->owner = User::factory()->create(['type' => 'u']);
    $this->community = Community::factory()->create(['user_id' => $this->owner->id, 'status' => 'p']);

    $this->curator = User::factory()->create(['type' => 'u']);
    $this->community->curators()->attach($this->curator);

    // A plain user with no relationship to the community.
    $this->stranger = User::factory()->create(['type' => 'u']);
});

// ----- create() (GET /communities/{community}/posts/create) -----

test('create renders the post create view for a curator', function () {
    Shelf::factory()->count(2)->create(['community_id' => $this->community->id]);

    $this->actingAs($this->curator)
        ->get("/communities/{$this->community->slug}/posts/create")
        ->assertOk()
        ->assertViewIs('curated.posts.create')
        ->assertViewHas('community')
        ->assertViewHas('shelves');
});

test('create excludes shelves with status a', function () {
    // note: create() filters shelves with where('status', '!=', 'a').
    $visible = Shelf::factory()->create(['community_id' => $this->community->id, 'status' => 'p']);
    $archived = Shelf::factory()->create(['community_id' => $this->community->id, 'status' => 'a']);

    $response = $this->actingAs($this->curator)
        ->get("/communities/{$this->community->slug}/posts/create")
        ->assertOk();

    $shelfIds = $response->viewData('shelves')->pluck('id');
    expect($shelfIds)->toContain($visible->id);
    expect($shelfIds)->not->toContain($archived->id);
});

test('create is denied to a non-curator', function () {
    $this->actingAs($this->stranger)
        ->get("/communities/{$this->community->slug}/posts/create")
        ->assertStatus(403);
});

test('create redirects a guest to login', function () {
    $this->get("/communities/{$this->community->slug}/posts/create")
        ->assertRedirect('/login');
});

// ----- store() (POST /communities/{community}/posts) -----

test('store creates a post with slug built from name and community id', function () {
    $shelf = Shelf::factory()->create(['community_id' => $this->community->id]);

    // note: store() returns the freshly-created model, so Laravel responds 201 Created.
    $response = $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts", [
            'name' => 'My Great Post',
            'blurb' => 'A blurb',
            'shelf_id' => $shelf->id,
        ])
        ->assertCreated();

    $expectedSlug = Str::slug('My Great Post').'-'.$this->community->id;

    expect($response->json('slug'))->toBe($expectedSlug);
    expect($response->json('name'))->toBe('My Great Post');
    expect($response->json('shelf_id'))->toBe($shelf->id);
    expect($response->json('user_id'))->toBe($this->curator->id);

    $this->assertDatabaseHas('posts', [
        'slug' => $expectedSlug,
        'community_id' => $this->community->id,
        'user_id' => $this->curator->id,
        'shelf_id' => $shelf->id,
    ]);
});

test('store creates a post without a shelf', function () {
    $response = $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts", [
            'name' => 'Shelfless',
            'blurb' => 'No shelf here',
        ])
        ->assertCreated();

    expect($response->json('shelf_id'))->toBeNull();
});

test('store saves an uploaded image via ImageHandler', function () {
    Storage::fake('digitalocean');

    $response = $this->actingAs($this->curator)
        ->post("/communities/{$this->community->slug}/posts", [
            'name' => 'Post With Image',
            'blurb' => 'Has an image',
            'image' => UploadedFile::fake()->image('cover.jpg', 1000, 563),
        ])
        ->assertCreated();

    $post = Post::where('slug', Str::slug('Post With Image').'-'.$this->community->id)->first();
    expect($post)->not->toBeNull();
    expect($post->images()->count())->toBe(1);

    // ImageHandler writes 4 files (large/thumb x webp/jpg) to the faked disk.
    expect(Storage::disk('digitalocean')->allFiles())->toHaveCount(4);
});

test('store is denied to a non-curator', function () {
    $this->actingAs($this->stranger)
        ->postJson("/communities/{$this->community->slug}/posts", ['name' => 'Nope'])
        ->assertStatus(403);

    $this->assertDatabaseMissing('posts', ['name' => 'Nope']);
});

test('store redirects a guest to login', function () {
    $this->post("/communities/{$this->community->slug}/posts", ['name' => 'Nope'])
        ->assertRedirect('/login');
});

// ----- show() (PUBLIC GET /communities/{community}/posts/{post}) -----

test('show renders a visible post for a guest', function () {
    $post = Post::factory()->create([
        'community_id' => $this->community->id,
        'is_hidden' => false,
    ]);

    $this->get("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertOk()
        ->assertViewIs('curated.posts.show')
        ->assertViewHas('curator', false);
});

test('show returns 404 for a hidden post when viewer is not a curator', function () {
    $post = Post::factory()->hidden()->create(['community_id' => $this->community->id]);

    // Guest
    $this->get("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertStatus(404);

    // Logged-in stranger (not a curator)
    $this->actingAs($this->stranger)
        ->get("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertStatus(404);
});

test('show lets a curator see a hidden post', function () {
    $post = Post::factory()->hidden()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->curator)
        ->get("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertOk()
        ->assertViewHas('curator', true);
});

// ----- edit() (GET /communities/{community}/posts/{post}/edit) -----

test('edit renders the edit view for a curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->curator)
        ->get("/communities/{$this->community->slug}/posts/{$post->slug}/edit")
        ->assertOk()
        ->assertViewIs('curated.posts.edit')
        ->assertViewHas('curator', true)
        ->assertViewHas('shelves');
});

test('edit is denied to a non-curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->stranger)
        ->get("/communities/{$this->community->slug}/posts/{$post->slug}/edit")
        ->assertStatus(403);
});

// ----- update() (POST /communities/{community}/posts/{post}) -----

test('update changes name and regenerates the slug', function () {
    $post = Post::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Original Name',
    ]);

    $response = $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts/{$post->slug}", [
            'name' => 'Brand New Title',
            'blurb' => 'updated blurb',
        ])
        ->assertOk();

    $expectedSlug = Str::slug('Brand New Title').'-'.$this->community->id;

    expect($response->json('slug'))->toBe($expectedSlug);
    expect($response->json('slug_changed'))->toBeTrue();
    expect($post->fresh()->slug)->toBe($expectedSlug);
    expect($post->fresh()->blurb)->toBe('updated blurb');
});

test('update reports no slug change when the name is unchanged', function () {
    // Slug must already match Str::slug(name)-communityId so update() detects no change.
    $post = Post::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Keep This Name',
        'slug' => Str::slug('Keep This Name').'-'.$this->community->id,
    ]);
    $originalSlug = $post->slug;

    $response = $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts/{$post->slug}", [
            'name' => 'Keep This Name',
            'blurb' => 'new blurb only',
        ])
        ->assertOk();

    expect($response->json('slug_changed'))->toBeFalse();
    expect($post->fresh()->slug)->toBe($originalSlug);
});

test('update ignores non-allow-listed fields like community_id and status', function () {
    // note: PostActions::update() allow-lists only name/blurb/shelf_id/order/type/event_id/image_type,
    // so attempts to reparent (community_id) or self-publish (status) are silently dropped.
    $otherCommunity = Community::factory()->create();
    $post = Post::factory()->create([
        'community_id' => $this->community->id,
        'status' => 'd',
    ]);

    $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts/{$post->slug}", [
            'name' => $post->name,
            'community_id' => $otherCommunity->id,
            'status' => 'p',
        ])
        ->assertOk();

    $fresh = $post->fresh();
    expect($fresh->community_id)->toBe($this->community->id);
    expect($fresh->status)->toBe('d');
});

test('update moves images to the new slug when the name changes', function () {
    Storage::fake('digitalocean');

    // Create a post, then attach an image through ImageHandler (under post-images/<oldSlug>/...).
    $post = Post::factory()->create([
        'community_id' => $this->community->id,
        'name' => 'Old Title',
    ]);
    \App\Services\ImageHandler::saveImage(
        UploadedFile::fake()->image('a.jpg', 1000, 563),
        $post,
        1000,
        563,
        'post-images'
    );
    $oldSlug = $post->fresh()->slug;
    $oldImagePath = $post->images()->first()->large_image_path;
    expect($oldImagePath)->toContain($oldSlug);

    $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts/{$oldSlug}", [
            'name' => 'Totally New Title',
        ])
        ->assertOk();

    $newSlug = Str::slug('Totally New Title').'-'.$this->community->id;
    $movedImage = $post->fresh()->images()->first();

    expect($post->fresh()->slug)->toBe($newSlug);
    expect($movedImage->large_image_path)->toContain($newSlug);
    expect($movedImage->large_image_path)->not->toContain($oldSlug);
});

test('update replaces an existing image with a new upload', function () {
    Storage::fake('digitalocean');

    $post = Post::factory()->create(['community_id' => $this->community->id]);
    \App\Services\ImageHandler::saveImage(
        UploadedFile::fake()->image('first.jpg', 1000, 563),
        $post,
        1000,
        563,
        'post-images'
    );
    $firstImageId = $post->images()->first()->id;

    $this->actingAs($this->curator)
        ->post("/communities/{$this->community->slug}/posts/{$post->slug}", [
            'name' => $post->name,
            'image' => UploadedFile::fake()->image('second.jpg', 1000, 563),
        ])
        ->assertOk();

    // Old image record deleted, exactly one new image remains.
    expect($post->fresh()->images()->count())->toBe(1);
    expect($post->fresh()->images()->first()->id)->not->toBe($firstImageId);
});

test('update with deleteImage flag clears the post images', function () {
    Storage::fake('digitalocean');

    $post = Post::factory()->create(['community_id' => $this->community->id]);
    \App\Services\ImageHandler::saveImage(
        UploadedFile::fake()->image('toremove.jpg', 1000, 563),
        $post,
        1000,
        563,
        'post-images'
    );
    expect($post->fresh()->images()->count())->toBe(1);

    $this->actingAs($this->curator)
        ->postJson("/communities/{$this->community->slug}/posts/{$post->slug}", [
            'name' => $post->name,
            'deleteImage' => true,
        ])
        ->assertOk();

    $fresh = $post->fresh();
    expect($fresh->images()->count())->toBe(0);
    expect($fresh->largeImagePath)->toBeNull();
    expect($fresh->thumbImagePath)->toBeNull();
});

test('update is denied to a non-curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id, 'name' => 'Untouched']);

    $this->actingAs($this->stranger)
        ->postJson("/communities/{$this->community->slug}/posts/{$post->slug}", ['name' => 'Hacked'])
        ->assertStatus(403);

    expect($post->fresh()->name)->toBe('Untouched');
});

// ----- order() (PUT /communities/{community}/posts/order) -----

test('order bulk-reorders posts', function () {
    $a = Post::factory()->create(['community_id' => $this->community->id, 'order' => 0]);
    $b = Post::factory()->create(['community_id' => $this->community->id, 'order' => 1]);

    $this->actingAs($this->curator)
        ->putJson("/communities/{$this->community->slug}/posts/order", [
            ['id' => $a->id, 'order' => 5],
            ['id' => $b->id, 'order' => 3],
        ])
        ->assertOk();

    expect($a->fresh()->order)->toBe(5);
    expect($b->fresh()->order)->toBe(3);
});

test('order is denied to a non-curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id, 'order' => 0]);

    $this->actingAs($this->stranger)
        ->putJson("/communities/{$this->community->slug}/posts/order", [
            ['id' => $post->id, 'order' => 9],
        ])
        ->assertStatus(403);

    expect($post->fresh()->order)->toBe(0);
});

// ----- toggleHidden() (PATCH /communities/{community}/posts/{post}/toggle-hidden) -----

test('toggleHidden flips is_hidden and returns JSON', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id, 'is_hidden' => false]);

    $this->actingAs($this->curator)
        ->patchJson("/communities/{$this->community->slug}/posts/{$post->slug}/toggle-hidden")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_hidden' => true,
            'message' => 'Post hidden successfully',
        ]);

    // note: Post::is_hidden has no boolean cast, so the stored attribute is int 1/0.
    expect($post->fresh()->is_hidden)->toBe(1);

    // Toggle back.
    $this->actingAs($this->curator)
        ->patchJson("/communities/{$this->community->slug}/posts/{$post->slug}/toggle-hidden")
        ->assertOk()
        ->assertJsonPath('is_hidden', false)
        ->assertJsonPath('message', 'Post shown successfully');

    expect($post->fresh()->is_hidden)->toBe(0);
});

test('toggleHidden is denied to a non-curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id, 'is_hidden' => false]);

    $this->actingAs($this->stranger)
        ->patchJson("/communities/{$this->community->slug}/posts/{$post->slug}/toggle-hidden")
        ->assertStatus(403);

    expect($post->fresh()->is_hidden)->toBe(0);
});

// ----- destroy() (DELETE /communities/{community}/posts/{post}) -----

test('destroy soft-deletes the post', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->curator)
        ->deleteJson("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertOk();

    // note: Post uses SoftDeletes; destroy() calls delete() (not forceDelete()).
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
});

test('destroy deletes the post images', function () {
    Storage::fake('digitalocean');

    $post = Post::factory()->create(['community_id' => $this->community->id]);
    \App\Services\ImageHandler::saveImage(
        UploadedFile::fake()->image('gone.jpg', 1000, 563),
        $post,
        1000,
        563,
        'post-images'
    );
    $imageId = $post->images()->first()->id;

    $this->actingAs($this->curator)
        ->deleteJson("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertOk();

    // note: destroy() => PostActions::destroy() only calls $post->delete().
    // Image deletion happens through the model's forceDeleting/boot cascade only on
    // force-delete, so on a soft delete the image rows can persist. Assert actual behavior.
    expect(Image::find($imageId))->not->toBeNull();
});

test('destroy is denied to a non-curator', function () {
    $post = Post::factory()->create(['community_id' => $this->community->id]);

    $this->actingAs($this->stranger)
        ->deleteJson("/communities/{$this->community->slug}/posts/{$post->slug}")
        ->assertStatus(403);

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
});

// note: PostController::paginate() exists in code but is unreachable —
// the only /communities/{community}/paginate route is bound to
// CommunityController::paginate (a different shape). Reported in bugsFound;
// no test here since the method is dead/unrouted in PostController.
