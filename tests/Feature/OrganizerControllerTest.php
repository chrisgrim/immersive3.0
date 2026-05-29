<?php

use App\Models\Event;
use App\Models\Image;
use App\Models\NameChangeRequest;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // store()/update() save images to the 'digitalocean' disk via ImageHandler.
    Storage::fake('digitalocean');
});

// ----- show() -----

test('show renders the organizers.show view for a published organizer', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);

    $this->get("/organizers/{$organizer->slug}")
        ->assertOk()
        ->assertViewIs('organizers.show')
        ->assertViewHas('organizer');
});

test('show renders for a published organizer even for a guest', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);

    $this->get("/organizers/{$organizer->slug}")
        ->assertOk();
});

test('show redirects an unauthenticated guest to home for a non-published organizer', function () {
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->get("/organizers/{$organizer->slug}")
        ->assertRedirect('/');
});

test('show redirects a logged-in non-member to home for a non-published organizer', function () {
    $organizer = Organizer::factory()->create(['status' => 'r']);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->get("/organizers/{$organizer->slug}")
        ->assertRedirect('/');
});

test('show lets the owner view their own non-published organizer', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r', 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get("/organizers/{$organizer->slug}")
        ->assertOk()
        ->assertViewIs('organizers.show');
});

test('show lets a pivot member view a non-published organizer', function () {
    $member = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r']);
    $organizer->users()->attach($member->id, ['role' => 'editor']);

    $this->actingAs($member)
        ->get("/organizers/{$organizer->slug}")
        ->assertOk();
});

test('show lets an admin view a non-published organizer', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($admin)
        ->get("/organizers/{$organizer->slug}")
        ->assertOk();
});

test('show 404s for an unknown organizer slug', function () {
    $this->get('/organizers/no-such-organizer')
        ->assertNotFound();
});

// ----- store() -----

test('store creates an organizer, attaches the owner pivot, and sets the current team', function () {
    $user = User::factory()->create(['type' => 'u']);

    $response = $this->actingAs($user)
        ->postJson('/organizers', [
            'name' => 'My First Org',
            'description' => 'We make immersive things.',
        ])
        ->assertOk();

    $organizer = Organizer::where('name', 'My First Org')->first();
    expect($organizer)->not->toBeNull();
    expect($organizer->user_id)->toBe($user->id);
    expect($organizer->status)->toBe('r');

    // Owner attached to pivot with the 'owner' role.
    expect($organizer->users()->where('user_id', $user->id)->wherePivot('role', 'owner')->exists())->toBeTrue();

    // current_team_id updated on the user.
    expect($user->fresh()->current_team_id)->toBe($organizer->id);
});

test('store creates a new event and redirects to its edit page for a first-time organizer', function () {
    $user = User::factory()->create(['type' => 'u']);

    $response = $this->actingAs($user)
        ->postJson('/organizers', [
            'name' => 'Brand New Org',
            'description' => 'First org ever.',
        ])
        ->assertOk();

    $organizer = Organizer::where('name', 'Brand New Org')->first();
    $event = Event::where('organizer_id', $organizer->id)->first();

    expect($event)->not->toBeNull();
    expect($response->json('redirect'))->toContain("/hosting/event/{$event->slug}/edit");
});

test('store redirects to hosting events when the user already owns an organizer', function () {
    $user = User::factory()->create(['type' => 'u']);
    // Pre-existing organizer owned by the user.
    Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);

    $response = $this->actingAs($user)
        ->postJson('/organizers', [
            'name' => 'Second Org',
            'description' => 'Another one.',
        ])
        ->assertOk();

    expect($response->json('redirect'))->toContain('/hosting/events');
    // No new event was created for the second organizer.
    $second = Organizer::where('name', 'Second Org')->first();
    expect(Event::where('organizer_id', $second->id)->exists())->toBeFalse();
});

test('store accepts an optional image and persists it on the digitalocean disk', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->post('/organizers', [
            'name' => 'Org With Image',
            'description' => 'Has a logo.',
            'image' => UploadedFile::fake()->image('logo.jpg', 800, 800),
        ])
        ->assertOk();

    $organizer = Organizer::where('name', 'Org With Image')->first();
    expect($organizer->images()->exists())->toBeTrue();
    expect(Storage::disk('digitalocean')->allFiles())->not->toBeEmpty();
});

test('store requires authentication', function () {
    // note: web auth middleware redirects guests to the login route.
    $this->post('/organizers', [
        'name' => 'No Auth Org',
        'description' => 'should fail',
    ])->assertRedirect('/login');
});

test('store without a description returns a 500 (validation gap)', function () {
    // note: StoreOrganizerRequest only validates `description` when it is present
    // in the payload, so a missing description passes validation. The controller
    // then tries to persist an organizer with a NULL description, the DB insert
    // fails, and the broad catch (\Exception) block masks it as a generic 500
    // instead of a 422. Asserting the real behavior here (see bugsFound).
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/organizers', [
            'name' => 'Missing Description',
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to create organizer.');
});

test('store rejects a name over 80 characters', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/organizers', [
            'name' => str_repeat('a', 81),
            'description' => 'valid',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

// ----- update() -----

test('update changes social fields on a draft organizer', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create([
        'user_id' => $owner->id,
        'status' => 'd',
    ]);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}", [
            'instagramHandle' => 'myinsta',
            'website' => 'https://example.com',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Organizer updated successfully');

    $organizer->refresh();
    expect($organizer->instagramHandle)->toBe('myinsta');
    expect($organizer->website)->toBe('https://example.com');
});

test('update on a draft organizer changes the name directly', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create([
        'user_id' => $owner->id,
        'status' => 'd',
        'name' => 'Old Draft Name',
    ]);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}", [
            'name' => 'New Draft Name',
            'description' => 'desc',
        ])
        ->assertOk();

    expect($organizer->fresh()->name)->toBe('New Draft Name');
    expect(NameChangeRequest::count())->toBe(0);
});

test('update on a published organizer ignores a direct name change', function () {
    // note: for a published org, update() unsets the name from the payload, so the
    // name is NOT updated by update() itself (a name change must go through
    // requestNameChange). update() does not create the NameChangeRequest here.
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create([
        'user_id' => $owner->id,
        'status' => 'p',
        'name' => 'Published Name',
    ]);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}", [
            'name' => 'Attempted New Name',
            'description' => 'desc',
        ])
        ->assertOk();

    expect($organizer->fresh()->name)->toBe('Published Name');
    expect(NameChangeRequest::count())->toBe(0);
});

test('update replaces the image when a new file is uploaded', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create([
        'user_id' => $owner->id,
        'status' => 'd',
    ]);
    // Seed an existing image with a valid deletable path structure.
    Image::factory()->create([
        'imageable_id' => $organizer->id,
        'imageable_type' => Organizer::class,
        'large_image_path' => 'organizer-images/'.$organizer->slug.'/old.webp',
        'thumb_image_path' => 'organizer-images/'.$organizer->slug.'/old-thumb.webp',
        'rank' => 0,
    ]);

    $this->actingAs($owner)
        ->post("/organizers/{$organizer->slug}", [
            'image' => UploadedFile::fake()->image('new.jpg', 800, 800),
        ])
        ->assertOk();

    // Old image record removed, a new one created.
    expect($organizer->images()->count())->toBe(1);
    expect($organizer->images()->where('large_image_path', 'like', '%new%')->exists())->toBeFalse();
    expect(Storage::disk('digitalocean')->allFiles())->not->toBeEmpty();
});

test('update is denied to a non-member', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/organizers/{$organizer->slug}", [
            'instagramHandle' => 'hacker',
        ])
        ->assertStatus(403);

    expect($organizer->fresh()->instagramHandle)->toBeNull();
});

test('update requires authentication', function () {
    $organizer = Organizer::factory()->create(['status' => 'd']);

    $this->post("/organizers/{$organizer->slug}", [
        'instagramHandle' => 'x',
    ])->assertRedirect('/login');
});

// ----- switchTeam() -----

test('switchTeam updates current_team_id and redirects to hosting events', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    $this->actingAs($owner)
        ->post("/teams/switch/{$organizer->slug}")
        ->assertRedirect('/hosting/events');

    expect($owner->fresh()->current_team_id)->toBe($organizer->id);
});

test('switchTeam is denied for a user with no relationship to the organizer', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->post("/teams/switch/{$organizer->slug}")
        ->assertStatus(403);

    expect($stranger->fresh()->current_team_id)->toBeNull();
});

// ----- teams() -----

test('teams renders the teams view for a user who belongs to an organizer', function () {
    $owner = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    $this->actingAs($owner)
        ->get('/teams')
        ->assertOk()
        ->assertViewIs('organizers.teams');
});

test('teams is denied to a user who belongs to no organizer', function () {
    // note: the /teams route is gated by can:viewAny — a regular user with no
    // organizers cannot view it.
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->get('/teams')
        ->assertStatus(403);
});

test('teams renders for a moderator regardless of membership', function () {
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)
        ->get('/teams')
        ->assertOk()
        ->assertViewIs('organizers.teams');
});

// ----- requestNameChange() -----

test('requestNameChange creates a pending name change request', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create([
        'user_id' => $owner->id,
        'status' => 'p',
        'name' => 'Current Org Name',
    ]);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}/name-change", [
            'requested_name' => 'Fancy New Name',
            'current_name' => 'Current Org Name',
        ])
        ->assertOk();

    $request = NameChangeRequest::first();
    expect($request)->not->toBeNull();
    expect($request->requested_name)->toBe('Fancy New Name');
    expect($request->status)->toBe('pending');
    expect($request->requestable_id)->toBe($organizer->id);
    expect($request->requestable_type)->toBe(Organizer::class);
});

test('requestNameChange missing requested_name returns a 500 (swallowed validation)', function () {
    // note: requestNameChange() calls $request->validate() INSIDE its try block,
    // so the thrown ValidationException is caught by the broad catch (\Exception)
    // and converted into a generic 500 instead of bubbling up as a 422 with
    // field-level errors. Asserting the real behavior here (see bugsFound).
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}/name-change", [
            'current_name' => $organizer->name,
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to submit name change request.');

    expect(NameChangeRequest::count())->toBe(0);
});

test('requestNameChange with a requested_name over 80 characters returns a 500 (swallowed validation)', function () {
    // note: same swallowed-validation quirk as above — a too-long name yields 500.
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    $this->actingAs($owner)
        ->postJson("/organizers/{$organizer->slug}/name-change", [
            'requested_name' => str_repeat('z', 81),
            'current_name' => $organizer->name,
        ])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Failed to submit name change request.');

    expect(NameChangeRequest::count())->toBe(0);
});

test('requestNameChange is denied to a non-member', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/organizers/{$organizer->slug}/name-change", [
            'requested_name' => 'Hijacked',
            'current_name' => $organizer->name,
        ])
        ->assertStatus(403);

    expect(NameChangeRequest::count())->toBe(0);
});
