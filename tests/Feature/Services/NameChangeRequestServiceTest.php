<?php

use App\Mail\NameChangeNotification;
use App\Models\Image;
use App\Models\NameChangeRequest;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ImageHandler;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    $this->service = new NameChangeRequestService;
    $this->user = User::factory()->create();
});

// ----- handleNameChange() / createNameChangeRequest() -----

test('handleNameChange creates a pending request with correct current and requested names', function () {
    $this->actingAs($this->user);
    $organizer = Organizer::factory()->create(['user_id' => $this->user->id, 'name' => 'Old Name']);

    $result = $this->service->handleNameChange($organizer, 'Brand New Name', 'rebranding');

    expect($result['success'])->toBeTrue();
    expect($result['requiresRefresh'])->toBeFalse();
    expect($result['message'])->toBe('Name change request submitted for review');

    $request = $organizer->nameChangeRequests()->first();
    expect($request)->not->toBeNull();
    expect($request->status)->toBe('pending');
    expect($request->current_name)->toBe('Old Name');
    expect($request->requested_name)->toBe('Brand New Name');
    expect($request->reason)->toBe('rebranding');
    expect($request->user_id)->toBe($this->user->id);
});

test('createNameChangeRequest emails every admin', function () {
    $this->actingAs($this->user);
    $organizer = Organizer::factory()->create(['user_id' => $this->user->id]);
    $admin1 = User::factory()->create(['type' => 'a']);
    $admin2 = User::factory()->create(['type' => 'a']);
    // A non-admin must not be notified.
    User::factory()->create(['type' => 'u']);

    $this->service->handleNameChange($organizer, 'Some New Name');

    // One notification per admin, and none to non-admins.
    Mail::assertSent(NameChangeNotification::class, User::where('type', 'a')->count());
    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($admin1->email));
    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($admin2->email));
});

test('handleNameChange records a null reason when none is provided', function () {
    $this->actingAs($this->user);
    $organizer = Organizer::factory()->create(['user_id' => $this->user->id]);

    $this->service->handleNameChange($organizer, 'No Reason Name');

    expect($organizer->nameChangeRequests()->first()->reason)->toBeNull();
});

test('handleNameChange refuses a second request while one is still pending', function () {
    $this->actingAs($this->user);
    $organizer = Organizer::factory()->create(['user_id' => $this->user->id]);

    // First request succeeds and is left pending.
    $this->service->handleNameChange($organizer, 'First Attempt');

    $result = $this->service->handleNameChange($organizer, 'Second Attempt');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('You already have a pending name change request');
    expect($result['requiresRefresh'])->toBeFalse();

    // Still only the original request; the second was not created.
    expect($organizer->nameChangeRequests()->count())->toBe(1);
    expect($organizer->nameChangeRequests()->first()->requested_name)->toBe('First Attempt');
});

test('handleNameChange allows a new request when prior requests are not pending', function () {
    $this->actingAs($this->user);
    $organizer = Organizer::factory()->create(['user_id' => $this->user->id]);
    // A previously-resolved (approved) request should not block a new one.
    NameChangeRequest::factory()->for($organizer, 'requestable')->create([
        'status' => 'approved',
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->handleNameChange($organizer, 'Fresh Request');

    expect($result['success'])->toBeTrue();
    expect($organizer->nameChangeRequests()->where('status', 'pending')->count())->toBe(1);
});

// ----- processAdminDirectChange() -----

test('processAdminDirectChange updates name and slug and notifies the owner', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'Original Org', 'slug' => 'original-org']);

    $result = $this->service->processAdminDirectChange($organizer, 'Renamed Org');

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Name updated successfully');
    // Slug changed, so a refresh is required.
    expect($result['requiresRefresh'])->toBeTrue();

    $organizer->refresh();
    expect($organizer->name)->toBe('Renamed Org');
    // note: Organizer's `updating` boot hook regenerates the slug from the name,
    // which here coincides with the service's Str::slug($newName) = 'renamed-org'.
    expect($organizer->slug)->toBe('renamed-org');

    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('processAdminDirectChange returns requiresRefresh false when the slug is unchanged', function () {
    $owner = User::factory()->create();
    // Changing only capitalization/punctuation that slugifies the same keeps the slug stable.
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'stable name', 'slug' => 'stable-name']);

    $result = $this->service->processAdminDirectChange($organizer, 'Stable Name');

    $organizer->refresh();
    expect($organizer->name)->toBe('Stable Name');
    expect($organizer->slug)->toBe('stable-name');
    // Slug did not change, so no refresh required.
    expect($result['requiresRefresh'])->toBeFalse();

    // Owner is still notified regardless.
    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('processAdminDirectChange moves images to the new slug directory when the slug changes', function () {
    Storage::fake('digitalocean');
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'Img Org', 'slug' => 'img-org']);

    // Save a real image under the old slug directory.
    $image = ImageHandler::saveImage(
        \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 800, 600),
        $organizer,
        800,
        600,
        'organizer-images',
        0
    );
    $oldDir = dirname($image->large_image_path); // organizer-images/img-org

    $this->service->processAdminDirectChange($organizer, 'Moved Org');

    $image->refresh();
    // Image record rewritten to the new slug directory.
    expect($image->large_image_path)->toMatch('#^organizer-images/moved-org/moved-org-[0-9a-f]+\.webp$#');

    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    // Old directory cleaned up.
    Storage::disk('digitalocean')->assertMissing("public/{$oldDir}");
});

test('processAdminDirectChange moves images using the persisted slug when the new name is CJK', function () {
    Storage::fake('digitalocean');
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'Img Org', 'slug' => 'img-org']);

    $image = ImageHandler::saveImage(
        \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 800, 600),
        $organizer,
        800,
        600,
        'organizer-images',
        0
    );

    // '東京' reduces to '' under Str::slug; the model hook regenerates a non-empty
    // 'organizer-{random}' slug, and the image must follow it — NOT be relocated to
    // an empty-slug path 'organizer-images//...'.
    $this->service->processAdminDirectChange($organizer, '東京');

    $organizer->refresh();
    $image->refresh();
    expect($organizer->slug)->toStartWith('organizer-');
    $slug = preg_quote($organizer->slug, '#');
    expect($image->large_image_path)->toMatch("#^organizer-images/{$slug}/{$slug}-[0-9a-f]+\.webp$#")
        ->and($image->large_image_path)->not->toContain('//');
    Storage::disk('digitalocean')->assertExists("public/{$image->large_image_path}");
});

test('processAdminDirectChange does not move images when the model has none', function () {
    Storage::fake('digitalocean');
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'No Img Org', 'slug' => 'no-img-org']);

    $result = $this->service->processAdminDirectChange($organizer, 'No Img Renamed');

    expect($result['requiresRefresh'])->toBeTrue();
    expect($organizer->images()->count())->toBe(0);
    Mail::assertSent(NameChangeNotification::class);
});

test('processAdminDirectChange notifies the owner for a Community (resolved via user_id)', function () {
    $owner = User::factory()->create();
    $community = \App\Models\Curated\Community::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Old Community',
    ]);

    $result = $this->service->processAdminDirectChange($community, 'New Community');

    expect($result['success'])->toBeTrue();
    expect($community->fresh()->name)->toBe('New Community');

    // Community has no user() relation (only owner()); resolving the owner via user_id means
    // it is now correctly notified — previously Mail::to(null) threw and was swallowed.
    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});
