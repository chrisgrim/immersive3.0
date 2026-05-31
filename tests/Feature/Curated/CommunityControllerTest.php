<?php

use App\Mail\CuratorInvitation as CuratorInvitationMail;
use App\Models\Curated\Community;
use App\Models\Curated\CuratorInvitation;
use App\Models\Curated\Shelf;
use App\Models\Image;
use App\Models\NameChangeRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    // The store/update flows save images to the 'digitalocean' disk via ImageHandler.
    Storage::fake('digitalocean');
});

// helper: attach a user as a curator on the pivot
function makeCurator(Community $community, User $user): User
{
    $community->curators()->attach($user->id);

    return $user->fresh();
}

// ----- store() POST /communities -----

test('store creates a community with two default shelves and attaches the creator as curator', function () {
    $user = User::factory()->create(['type' => 'u']);

    // note: store returns the bare model, so the HTTP status is 201 (created).
    $this->actingAs($user)
        ->post('/communities', [
            'name' => 'Immersive Wonders',
            'blurb' => 'A place for wonder',
            'description' => 'Long form description of the community.',
        ])
        ->assertStatus(201);

    $community = Community::where('name', 'Immersive Wonders')->first();
    expect($community)->not->toBeNull();
    expect($community->user_id)->toBe($user->id);
    expect($community->slug)->toBe('immersive-wonders');
    expect($community->blurb)->toBe('A place for wonder');
    expect($community->description)->toBe('Long form description of the community.');

    // Two default shelves: a normal one + an Archived one with status 'a'.
    $shelves = Shelf::where('community_id', $community->id)->get();
    expect($shelves)->toHaveCount(2);
    expect($shelves->firstWhere('name', 'Archived')?->status)->toBe('a');

    // Creator is attached as a curator on the pivot.
    expect($community->curators()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('store sets description to null when omitted', function () {
    $user = User::factory()->create(['type' => 'u']);

    // note: StoreCommunityRequest only requires `description` when the key is present,
    // so a community can be created without one; the action stores null.
    $this->actingAs($user)
        ->post('/communities', [
            'name' => 'No Description Co',
            'blurb' => 'Brief',
        ])
        ->assertStatus(201);

    $community = Community::where('name', 'No Description Co')->first();
    expect($community->description)->toBeNull();
});

test('store accepts an uploaded image and persists it on the digitalocean disk', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->post('/communities', [
            'name' => 'Picture Perfect',
            'blurb' => 'With an image',
            'description' => 'Has a picture.',
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 450),
        ])
        ->assertStatus(201);

    $community = Community::where('name', 'Picture Perfect')->first();
    expect($community->images()->exists())->toBeTrue();
    expect(Storage::disk('digitalocean')->allFiles())->not->toBeEmpty();
});

test('store with an empty name returns a 422 validation error', function () {
    $user = User::factory()->create(['type' => 'u']);

    // An empty `name` is coerced to null by ConvertEmptyStringsToNull but the key
    // still "exists", so the name rules (incl. UniqueSlugRule) are built. UniqueSlugRule
    // now tolerates a null name, so the 'required' rule yields a clean 422 instead of a
    // TypeError 500.
    $this->actingAs($user)
        ->postJson('/communities', [
            'name' => '',
            'blurb' => 'b',
            'description' => 'd',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect(Community::count())->toBe(0);
});

test('store rejects a name longer than 60 characters', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/communities', [
            'name' => str_repeat('x', 61),
            'blurb' => 'b',
            'description' => 'd',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('store rejects a blurb longer than 160 characters', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/communities', [
            'name' => 'Valid Name',
            'blurb' => str_repeat('x', 161),
            'description' => 'd',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['blurb']);
});

test('store rejects a description longer than 2000 characters', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/communities', [
            'name' => 'Valid Name',
            'blurb' => 'ok',
            'description' => str_repeat('x', 2001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

test('store redirects guests to login', function () {
    $this->post('/communities', [
        'name' => 'No Auth',
        'blurb' => 'b',
        'description' => 'd',
    ])->assertRedirect('/login');

    expect(Community::where('name', 'No Auth')->exists())->toBeFalse();
});

// ----- show() GET /communities/{community} (public) -----

test('show renders a published community to a guest', function () {
    $community = Community::factory()->published()->create();

    $this->get("/communities/{$community->slug}")->assertOk();
});

test('show still renders an unpublished community (no status gate on the show route)', function () {
    // note: CommunityController@show does NOT authorize('preview') nor filter by status,
    // so an unpublished (status 'r') community is served with a 200 to anyone. Only the
    // *shelves* are filtered to published. This is a visibility quirk worth flagging.
    $community = Community::factory()->pending()->create();

    $this->get("/communities/{$community->slug}")->assertOk();
});

test('show returns 404 for an unknown slug', function () {
    $this->get('/communities/does-not-exist')->assertNotFound();
});

// ----- update() POST /communities/{community} : regular field update -----

test('update changes regular fields for a draft community owned by the user', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create([
        'user_id' => $owner->id,
        'status' => 'd',
        'name' => 'Original Name',
    ]);
    makeCurator($community, $owner);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}", [
            'name' => 'Renamed Draft',
            'blurb' => 'Updated blurb',
            'description' => 'Updated description',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Community updated successfully')
        ->assertJsonPath('community.name', 'Renamed Draft');

    $community->refresh();
    // A draft updates the name directly.
    expect($community->name)->toBe('Renamed Draft');
    expect($community->blurb)->toBe('Updated blurb');
});

test('update on a PUBLISHED community defers a name change to a pending NameChangeRequest', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->published()->create([
        'user_id' => $owner->id,
        'name' => 'Public Name',
    ]);
    makeCurator($community, $owner);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}", [
            'name' => 'New Public Name',
            'blurb' => 'Still updates the blurb',
            'description' => 'desc',
        ])
        ->assertOk();

    $community->refresh();
    // Name is NOT changed yet — it goes through review.
    expect($community->name)->toBe('Public Name');
    // But other fields (blurb) still apply.
    expect($community->blurb)->toBe('Still updates the blurb');

    // A pending NameChangeRequest was created.
    $ncr = NameChangeRequest::where('requestable_type', Community::class)
        ->where('requestable_id', $community->id)
        ->first();
    expect($ncr)->not->toBeNull();
    expect($ncr->status)->toBe('pending');
    expect($ncr->current_name)->toBe('Public Name');
    expect($ncr->requested_name)->toBe('New Public Name');
});

test('update replaces the image, deleting old images first', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    makeCurator($community, $owner);

    // Pre-existing image attached to the community. ImageHandler::deleteImage()
    // requires a "<type>-images/slug/file" path structure, so build it explicitly.
    $old = Image::factory()->create([
        'imageable_id' => $community->id,
        'imageable_type' => Community::class,
        'large_image_path' => "community-images/{$community->slug}/old.webp",
        'thumb_image_path' => "community-images/{$community->slug}/old-thumb.webp",
    ]);

    $this->actingAs($owner)
        ->post("/communities/{$community->slug}", [
            'image' => UploadedFile::fake()->image('new.jpg', 800, 450),
        ])
        ->assertOk();

    // Old image record deleted, exactly one (new) image now attached.
    expect(Image::find($old->id))->toBeNull();
    expect($community->images()->count())->toBe(1);
});

test('update with curator_ids syncs curators and returns the refreshed community', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    makeCurator($community, $owner);

    $newCurator = User::factory()->create(['type' => 'u']);

    // Owner stays + new curator gets added (curator_ids drives add/remove diffing).
    $response = $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}", [
            'curator_ids' => [$owner->id, $newCurator->id],
        ])
        ->assertOk();

    $ids = $community->fresh()->curators->pluck('id')->all();
    expect($ids)->toContain($owner->id);
    expect($ids)->toContain($newCurator->id);

    // note: the curator_ids branch returns the bare community model (not the
    // {message, community} envelope used by the regular update path).
    expect($response->json('curators'))->not->toBeNull();
});

test('update with curator_ids and new_owner_id transfers ownership and keeps the old owner as a curator', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    makeCurator($community, $owner);

    $newOwner = User::factory()->create(['type' => 'u']);
    makeCurator($community, $newOwner);

    // curator_ids deliberately omits the old owner. The controller now captures the
    // previous owner before the transfer and re-adds them, so the outgoing owner keeps
    // curator access.
    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}", [
            'curator_ids' => [$newOwner->id],
            'new_owner_id' => $newOwner->id,
        ])
        ->assertOk();

    $community->refresh();
    // Ownership transferred.
    expect($community->user_id)->toBe($newOwner->id);

    // The previous owner is retained as a curator alongside the new owner.
    $ids = $community->curators->pluck('id')->all();
    expect($ids)->toContain($newOwner->id);
    expect($ids)->toContain($owner->id);
});

test('update is denied to a non-owner non-curator (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/communities/{$community->slug}", [
            'name' => 'Hostile Takeover',
            'blurb' => 'b',
            'description' => 'd',
        ])
        ->assertStatus(403);
});

test('update redirects guests to login', function () {
    $community = Community::factory()->create();

    $this->post("/communities/{$community->slug}", [
        'name' => 'x',
        'blurb' => 'b',
        'description' => 'd',
    ])->assertRedirect('/login');
});

// ----- edit() GET /communities/{community}/edit -----

test('edit is allowed for a curator', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    makeCurator($community, $owner);

    $this->actingAs($owner)->get("/communities/{$community->slug}/edit")->assertOk();
});

test('edit is denied to a stranger (403)', function () {
    $community = Community::factory()->create();
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)->get("/communities/{$community->slug}/edit")->assertStatus(403);
});

// ----- inviteCurator() POST /communities/{community}/curators/invite -----

test('inviteCurator creates an invitation and sends the invitation mail', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $invitee = User::factory()->create(['type' => 'u', 'email' => 'invitee@example.com']);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators/invite", [
            'email' => 'invitee@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Invitation sent successfully');

    $invitation = CuratorInvitation::where('community_id', $community->id)
        ->where('email', 'invitee@example.com')
        ->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->token)->not->toBeEmpty();
    expect($invitation->accepted_at)->toBeNull();

    Mail::assertSent(CuratorInvitationMail::class, fn ($mail) => $mail->hasTo('invitee@example.com'));
});

test('inviteCurator validates the email field', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators/invite", ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

test('inviteCurator fails when the email is not a registered EI user', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators/invite", [
            'email' => 'nobody@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

test('inviteCurator rejects an email already a curator', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $existing = User::factory()->create(['type' => 'u', 'email' => 'already@example.com']);
    makeCurator($community, $existing);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators/invite", [
            'email' => 'already@example.com',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('inviteCurator is denied to a non-owner curator (manageCurators, 403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $curator = makeCurator($community, User::factory()->create(['type' => 'u']));
    $invitee = User::factory()->create(['type' => 'u', 'email' => 'invitee2@example.com']);

    // A plain curator (not owner/admin) cannot manage curators.
    $this->actingAs($curator)
        ->postJson("/communities/{$community->slug}/curators/invite", [
            'email' => 'invitee2@example.com',
        ])
        ->assertStatus(403);
});

// ----- acceptInvitation() GET /communities/curator-invitations/{token} -----

test('acceptInvitation with a valid token attaches the curator and marks it accepted', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $invitee = User::factory()->create(['type' => 'u', 'email' => 'accept@example.com']);

    $invitation = CuratorInvitation::factory()->create([
        'community_id' => $community->id,
        'email' => 'accept@example.com',
    ]);

    $this->actingAs($invitee)
        ->get("/communities/curator-invitations/{$invitation->token}")
        ->assertRedirect("/communities/{$community->slug}/listings");

    expect($community->curators()->where('users.id', $invitee->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('acceptInvitation with an invalid token 404s', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->get('/communities/curator-invitations/totally-bogus-token')
        ->assertNotFound();
});

test('acceptInvitation for an already-accepted invitation redirects to edit with an info message', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $invitee = User::factory()->create(['type' => 'u', 'email' => 'done@example.com']);

    $invitation = CuratorInvitation::factory()->accepted()->create([
        'community_id' => $community->id,
        'email' => 'done@example.com',
    ]);

    $this->actingAs($invitee)
        ->get("/communities/curator-invitations/{$invitation->token}")
        ->assertRedirect("/communities/{$community->slug}/edit");
});

test('acceptInvitation for an expired invitation 404s', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $invitee = User::factory()->create(['type' => 'u', 'email' => 'late@example.com']);

    $invitation = CuratorInvitation::factory()->expired()->create([
        'community_id' => $community->id,
        'email' => 'late@example.com',
    ]);

    $this->actingAs($invitee)
        ->get("/communities/curator-invitations/{$invitation->token}")
        ->assertNotFound();

    expect($community->curators()->where('users.id', $invitee->id)->exists())->toBeFalse();
});

test('acceptInvitation while logged out stores the token and redirects to login', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);

    $invitation = CuratorInvitation::factory()->create([
        'community_id' => $community->id,
        'email' => 'guest@example.com',
    ]);

    // The curators.accept route is now public, so a guest reaches acceptInvitation(),
    // which stashes the token in the session and redirects to login (so the invite
    // resumes once they authenticate as the invited email).
    $this->get("/communities/curator-invitations/{$invitation->token}")
        ->assertRedirect(route('login'))
        ->assertSessionHas('pending_curator_invitation', $invitation->token);
});

test('acceptInvitation with an email mismatch logs the user out and redirects to login', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $invitation = CuratorInvitation::factory()->create([
        'community_id' => $community->id,
        'email' => 'intended@example.com',
    ]);
    $wrongUser = User::factory()->create(['type' => 'u', 'email' => 'someoneelse@example.com']);

    $this->actingAs($wrongUser)
        ->get("/communities/curator-invitations/{$invitation->token}")
        ->assertRedirect(route('login'));

    // Not attached, and the session no longer has an authenticated user.
    expect($community->curators()->where('users.id', $wrongUser->id)->exists())->toBeFalse();
    $this->assertGuest();
});

// ----- updateCurators() POST /communities/{community}/curators -----

test('updateCurators syncs the curator list', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    makeCurator($community, $owner);
    $a = User::factory()->create(['type' => 'u']);
    $b = User::factory()->create(['type' => 'u']);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators", [
            'curator_ids' => [$a->id, $b->id],
        ])
        ->assertOk();

    // sync() replaces — owner is no longer on the pivot, only a and b are.
    $ids = $community->fresh()->curators->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing([$a->id, $b->id]);
});

test('updateCurators is denied to a non-owner curator (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $curator = makeCurator($community, User::factory()->create(['type' => 'u']));

    $this->actingAs($curator)
        ->postJson("/communities/{$community->slug}/curators", [
            'curator_ids' => [$curator->id],
        ])
        ->assertStatus(403);
});

// ----- removeCurator() POST /communities/{community}/curators/remove -----

test('removeCurator detaches the given curator id', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    makeCurator($community, $owner);
    $other = makeCurator($community, User::factory()->create(['type' => 'u']));

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/curators/remove", ['id' => $other->id])
        ->assertOk();

    expect($community->curators()->where('users.id', $other->id)->exists())->toBeFalse();
});

test('removeCurator is denied to a non-owner curator (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $curator = makeCurator($community, User::factory()->create(['type' => 'u']));

    $this->actingAs($curator)
        ->postJson("/communities/{$community->slug}/curators/remove", ['id' => $curator->id])
        ->assertStatus(403);
});

// ----- removeSelf() DELETE /communities/{community}/curators/self -----

test('removeSelf detaches the calling curator', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $curator = makeCurator($community, User::factory()->create(['type' => 'u']));

    $this->actingAs($curator)
        ->deleteJson("/communities/{$community->slug}/curators/self")
        ->assertOk();

    expect($community->curators()->where('users.id', $curator->id)->exists())->toBeFalse();
});

test('removeSelf is denied to the owner (cannot self-remove)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    makeCurator($community, $owner);

    $this->actingAs($owner)
        ->deleteJson("/communities/{$community->slug}/curators/self")
        ->assertStatus(403);
});

test('removeSelf is denied to a stranger (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->deleteJson("/communities/{$community->slug}/curators/self")
        ->assertStatus(403);
});

// ----- submit() POST /communities/{community}/submit -----

test('submit sets the community status to r (review)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    makeCurator($community, $owner);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/submit")
        ->assertOk();

    expect($community->fresh()->status)->toBe('r');
});

test('submit is denied to a stranger (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id, 'status' => 'd']);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/communities/{$community->slug}/submit")
        ->assertStatus(403);

    expect($community->fresh()->status)->toBe('d');
});

// ----- requestNameChange() POST /communities/{community}/name-change -----

test('requestNameChange creates a pending NameChangeRequest and returns JSON', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->published()->create([
        'user_id' => $owner->id,
        'name' => 'Old Brand',
    ]);
    makeCurator($community, $owner);

    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/name-change", [
            'requested_name' => 'New Brand',
            'current_name' => 'Old Brand',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'community']);

    $ncr = NameChangeRequest::where('requestable_type', Community::class)
        ->where('requestable_id', $community->id)
        ->first();
    expect($ncr)->not->toBeNull();
    expect($ncr->status)->toBe('pending');
    expect($ncr->requested_name)->toBe('New Brand');

    // Name itself is unchanged until reviewed.
    expect($community->fresh()->name)->toBe('Old Brand');
});

test('requestNameChange returns a 422 with field errors for an invalid request', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    makeCurator($community, $owner);

    // Validation now runs outside the catch-all, so a missing requested_name/current_name
    // surfaces as a clean 422 with field errors instead of a swallowed 500.
    $this->actingAs($owner)
        ->postJson("/communities/{$community->slug}/name-change", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['requested_name', 'current_name']);
});

test('requestNameChange is denied to a stranger (403)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/communities/{$community->slug}/name-change", [
            'requested_name' => 'X',
            'current_name' => 'Y',
        ])
        ->assertStatus(403);
});

// ----- destroy() DELETE /communities/{community} -----

test('destroy deletes the community and its images', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    makeCurator($community, $owner);

    // ImageHandler::deleteImage requires a "<type>-images/slug/file" path structure.
    $image = Image::factory()->create([
        'imageable_id' => $community->id,
        'imageable_type' => Community::class,
        'large_image_path' => "community-images/{$community->slug}/cover.webp",
        'thumb_image_path' => "community-images/{$community->slug}/cover-thumb.webp",
    ]);

    // note: CommunityController@destroy has no route in routes/curated.php, so it is
    // exercised here through the action directly. destroy() ends by returning
    // auth()->user()->communities, so an authenticated user must be present.
    $this->actingAs($owner);
    app(\App\Actions\Curated\CommunityActions::class)->destroy($community);

    expect(Community::find($community->id))->toBeNull();
    expect(Image::find($image->id))->toBeNull();
});
