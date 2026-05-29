<?php

use App\Models\Image;
use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

// note: ProfilesController only has show/edit/update wired in routes/web.php — all
// three live INSIDE the ['auth'] middleware group, so even show() (intended to be
// "public" per its makeHidden() field stripping) actually requires authentication;
// unauthenticated requests redirect to login. destroy() has NO route at all, so it
// is exercised by calling the controller method directly. See bugsFound/skipped.

beforeEach(function () {
    $this->user = User::factory()->create(['type' => 'u']);
});

// ----- show() -----

test('show renders the profile view with the user', function () {
    $owner = User::factory()->create(['type' => 'u']);

    $this->actingAs($this->user)
        ->get("/users/{$owner->id}")
        ->assertOk()
        ->assertViewIs('auth.user-profile')
        ->assertViewHas('user', fn ($u) => $u->id === $owner->id);
});

test('show hides sensitive fields in the array form', function () {
    $owner = User::factory()->create([
        'type' => 'u',
        'email' => 'secret@example.com',
        'newsletter_type' => 'a',
    ]);

    $response = $this->actingAs($this->user)->get("/users/{$owner->id}");
    $response->assertOk();

    $array = $response->viewData('user')->toArray();
    expect($array)->not->toHaveKey('email');
    expect($array)->not->toHaveKey('newsletter_type');
    expect($array)->not->toHaveKey('type');
    expect($array)->not->toHaveKey('stripe_id');
});

test('show sets the image attribute from the first image', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $image = Image::factory()->create([
        'imageable_id' => $owner->id,
        'imageable_type' => User::class,
    ]);

    $response = $this->actingAs($this->user)->get("/users/{$owner->id}");
    $response->assertOk();

    expect($response->viewData('user')->image->id)->toBe($image->id);
});

test('show requires authentication', function () {
    $owner = User::factory()->create(['type' => 'u']);

    // note: show() is inside the auth-middleware group, so it is not actually public.
    $this->get("/users/{$owner->id}")->assertRedirect();
});

// ----- edit() -----

test('edit renders the edit view for the owner', function () {
    $this->actingAs($this->user)
        ->get("/users/{$this->user->id}/edit")
        ->assertOk()
        ->assertViewIs('auth.user-edit')
        ->assertViewHas('user', fn ($u) => $u->id === $this->user->id)
        ->assertViewHas('owner', fn ($u) => $u->id === $this->user->id);
});

test('edit makes newsletter_type and silence visible', function () {
    $this->user->update(['newsletter_type' => 'm', 'silence' => 'y']);

    $response = $this->actingAs($this->user)->get("/users/{$this->user->id}/edit");
    $response->assertOk();

    $array = $response->viewData('user')->toArray();
    expect($array)->toHaveKey('newsletter_type');
    expect($array)->toHaveKey('silence');
    expect($array['newsletter_type'])->toBe('m');
});

test('edit is allowed for a moderator editing someone else', function () {
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)
        ->get("/users/{$this->user->id}/edit")
        ->assertOk();
});

test('edit is denied to a different non-moderator user', function () {
    $other = User::factory()->create(['type' => 'u']);

    $this->actingAs($other)
        ->get("/users/{$this->user->id}/edit")
        ->assertStatus(403);
});

test('edit requires authentication', function () {
    $this->get("/users/{$this->user->id}/edit")->assertRedirect();
});

// ----- update(): regular fields path -----

test('update saves name and email for the owner', function () {
    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'name' => 'New Name',
            'email' => $this->user->email, // unchanged so no re-verification
        ])
        ->assertOk();

    expect($this->user->fresh()->name)->toBe('New Name');
});

test('update defaults newsletter_type to n and silence to y when omitted', function () {
    $this->user->update(['newsletter_type' => 'a', 'silence' => 'n']);

    // note: the regular-fields path unconditionally injects defaults
    // newsletter_type='n' and silence='y' when those inputs are absent, so an
    // update that only changes the name silently resets the newsletter prefs.
    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", ['name' => 'Just A Name'])
        ->assertOk();

    $fresh = $this->user->fresh();
    expect($fresh->newsletter_type)->toBe('n');
    expect($fresh->silence)->toBe('y');
});

test('update persists provided newsletter_type and silence', function () {
    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'name' => 'Name',
            'newsletter_type' => 'm',
            'silence' => 'n',
        ])
        ->assertOk();

    $fresh = $this->user->fresh();
    expect($fresh->newsletter_type)->toBe('m');
    expect($fresh->silence)->toBe('n');
});

test('update response makes newsletter_type and silence visible', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'name' => 'Visible Name',
            'newsletter_type' => 'u',
        ]);

    $response->assertOk()
        ->assertJsonPath('newsletter_type', 'u')
        ->assertJsonPath('silence', 'y'); // default applied
});

// ----- update(): email-change path -----

test('update clears email_verified_at and sends verification when email changes', function () {
    Notification::fake();

    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'name' => $this->user->name,
            'email' => 'changed@example.com',
        ])
        ->assertOk();

    expect($this->user->fresh()->email)->toBe('changed@example.com');
    expect($this->user->fresh()->email_verified_at)->toBeNull();

    Notification::assertSentTo($this->user->fresh(), VerifyEmail::class);
});

test('update does not re-verify when email is unchanged', function () {
    Notification::fake();
    $verifiedAt = $this->user->email_verified_at;

    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'name' => 'Same Email',
            'email' => $this->user->email,
        ])
        ->assertOk();

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

// ----- update(): validation -----

test('update rejects a duplicate email', function () {
    $taken = User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'email' => 'taken@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('update rejects an invalid newsletter_type', function () {
    $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'newsletter_type' => 'z',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['newsletter_type']);
});

test('update rejects an invalid silence value', function () {
    $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'silence' => 'maybe',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['silence']);
});

test('update rejects a non-image upload', function () {
    $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('update rejects an oversized image', function () {
    $this->actingAs($this->user)
        ->postJson("/users/{$this->user->id}", [
            'image' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

// ----- update(): image-only path -----

test('update with an image only saves the image and returns the user', function () {
    Storage::fake('digitalocean');

    $response = $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'image' => UploadedFile::fake()->image('avatar.jpg', 800, 800),
        ]);

    $response->assertOk();

    expect($this->user->fresh()->images()->count())->toBe(1);
    // a webp + jpg (full + thumb) should have been written to the faked disk
    expect(Storage::disk('digitalocean')->allFiles())->not->toBeEmpty();
});

test('update with a new image deletes the old image first', function () {
    Storage::fake('digitalocean');

    // Seed an existing image with a deletable path structure (type-images/slug/file).
    $old = $this->user->images()->create([
        'large_image_path' => 'user-images/old-slug/old-file.webp',
        'thumb_image_path' => 'user-images/old-slug/old-file-thumb.webp',
        'rank' => 0,
    ]);

    $this->actingAs($this->user)
        ->post("/users/{$this->user->id}", [
            'image' => UploadedFile::fake()->image('avatar.jpg', 800, 800),
        ])
        ->assertOk();

    // The old image record is gone; exactly one (the new) image remains.
    expect(Image::find($old->id))->toBeNull();
    expect($this->user->fresh()->images()->count())->toBe(1);
});

// ----- update(): authorization -----

test('update is denied to a different non-moderator user', function () {
    $other = User::factory()->create(['type' => 'u']);

    $this->actingAs($other)
        ->postJson("/users/{$this->user->id}", ['name' => 'Hacked'])
        ->assertStatus(403);

    expect($this->user->fresh()->name)->not->toBe('Hacked');
});

test('update is allowed for a moderator editing someone else', function () {
    $moderator = User::factory()->create(['type' => 'm']);

    // note: omit email — see "unique rule ignores the wrong user" bug below.
    $this->actingAs($moderator)
        ->postJson("/users/{$this->user->id}", [
            'name' => 'Mod Edited',
        ])
        ->assertOk();

    expect($this->user->fresh()->name)->toBe('Mod Edited');
});

test('moderator updating another user with that users own email is wrongly rejected', function () {
    // note: BUG — StoreProfileRequest's unique rule ignores $this->user()->id (the
    // authenticated moderator) rather than the route target. So submitting the
    // target user's existing email trips "email has already been taken".
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)
        ->postJson("/users/{$this->user->id}", [
            'name' => 'Mod Edited',
            'email' => $this->user->email,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('update requires authentication', function () {
    $this->post("/users/{$this->user->id}", ['name' => 'X'])->assertRedirect();
});

// ----- destroy() -----
// note: destroy() is defined on the controller but NOT wired to any route, so it
// is invoked directly. It detaches the user's conversation pivots then deletes
// the user (no soft deletes on User).

test('destroy detaches conversations and deletes the user', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create();
    $owner->conversations()->attach($conversation->id);

    $this->actingAs($owner);
    app(\App\Http\Controllers\User\ProfilesController::class)->destroy($owner);

    expect(User::find($owner->id))->toBeNull();
    expect(\Illuminate\Support\Facades\DB::table('conversation_user')
        ->where('user_id', $owner->id)->exists())->toBeFalse();
    // The conversation row itself is left intact.
    expect(Conversation::find($conversation->id))->not->toBeNull();
});

test('destroy is denied to a different non-moderator user via policy authorize', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $other = User::factory()->create(['type' => 'u']);

    $this->actingAs($other);

    expect(fn () => app(\App\Http\Controllers\User\ProfilesController::class)->destroy($owner))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    expect(User::find($owner->id))->not->toBeNull();
});
