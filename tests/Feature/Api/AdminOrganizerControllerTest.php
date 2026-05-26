<?php

use App\Mail\Comments;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- getPending() -----

test('getPending returns only organizers with status r', function () {
    Organizer::factory()->count(2)->create(['status' => 'r']);
    Organizer::factory()->count(3)->create(['status' => 'p']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/organizers')
        ->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('getPending is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/approve/organizers')->assertStatus(403);
});

// ----- approve() -----

test('approve flips an in-review organizer to published and emails the owner', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertOk();

    expect($organizer->fresh()->status)->toBe('p');
    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('approve does not email when moderator approves their own organizer', function () {
    $organizer = Organizer::factory()->create(['user_id' => $this->moderator->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertOk();

    Mail::assertNothingSent();
});

test('approve is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertStatus(403);

    expect($organizer->fresh()->status)->toBe('r');
});

// ----- reject() -----

test('reject sets status to n and emails the owner with the reason', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [
            'reason' => 'Name conflicts with an existing org',
        ])
        ->assertOk()
        ->assertJsonPath('organizer.status', 'n');

    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('reject requires a reason', function () {
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('reject does not email when moderator rejects their own organizer', function () {
    $organizer = Organizer::factory()->create(['user_id' => $this->moderator->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [
            'reason' => 'changed my mind',
        ])
        ->assertOk();

    Mail::assertNothingSent();
});

test('reject is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", ['reason' => 'no'])
        ->assertStatus(403);
});

// ----- moveEvents() -----

test('moveEvents moves all source events to destination', function () {
    $source = Organizer::factory()->create();
    $destination = Organizer::factory()->create();
    Event::factory()->count(3)->create(['organizer_id' => $source->id]);
    Event::factory()->count(1)->create(['organizer_id' => $destination->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
        ])
        ->assertOk()
        ->assertJsonPath('moved_count', 3);

    expect(Event::where('organizer_id', $source->id)->count())->toBe(0);
    expect(Event::where('organizer_id', $destination->id)->count())->toBe(4);
});

test('moveEvents with swap_slug renames source to -old and destination to clean slug', function () {
    // Note: Organizer model auto-generates slugs from name on create. Using the
    // same name twice gives "the-music-center" and "the-music-center-1".
    $source = Organizer::factory()->create(['name' => 'The Music Center']);
    $destination = Organizer::factory()->create(['name' => 'The Music Center']);
    expect($source->slug)->toBe('the-music-center');
    expect($destination->slug)->toBe('the-music-center-1');

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
            'swap_slug' => true,
        ])
        ->assertOk();

    expect($source->fresh()->slug)->toBe('the-music-center-old');
    expect($destination->fresh()->slug)->toBe('the-music-center');
});

test('moveEvents picks -old-2 when -old is already taken', function () {
    $source = Organizer::factory()->create(['name' => 'Venue']);
    $destination = Organizer::factory()->create(['name' => 'Venue']);
    // Pre-existing "venue-old" — update slug post-create since the model's
    // creating hook auto-generates it.
    $taken = Organizer::factory()->create(['name' => 'Some Other Name']);
    $taken->slug = 'venue-old';
    $taken->saveQuietly();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
            'swap_slug' => true,
        ])
        ->assertOk();

    expect($source->fresh()->slug)->toBe('venue-old-2');
    expect($destination->fresh()->slug)->toBe('venue');
});

test('moveEvents leaves slugs alone when swap_slug is false', function () {
    $source = Organizer::factory()->create(['name' => 'Source Org']);
    $destination = Organizer::factory()->create(['name' => 'Destination Org']);
    $originalSourceSlug = $source->slug;
    $originalDestSlug = $destination->slug;

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
        ])
        ->assertOk();

    expect($source->fresh()->slug)->toBe($originalSourceSlug);
    expect($destination->fresh()->slug)->toBe($originalDestSlug);
});

test('moveEvents refuses to move to the same organizer', function () {
    $organizer = Organizer::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$organizer->slug}/move-events", [
            'destination_organizer_id' => $organizer->id,
        ])
        ->assertStatus(422);
});

test('moveEvents requires a valid destination', function () {
    $source = Organizer::factory()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => 99999,
        ])
        ->assertStatus(422);
});

test('moveEvents emails the source owner when admin is not the owner', function () {
    $owner = User::factory()->create();
    $source = Organizer::factory()->create(['user_id' => $owner->id]);
    $destination = Organizer::factory()->create();
    Event::factory()->count(2)->create(['organizer_id' => $source->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
        ])
        ->assertOk();

    Mail::assertSent(Comments::class, fn ($m) => $m->hasTo($owner->email));
});

test('moveEvents does NOT email when moderator is the source owner', function () {
    $source = Organizer::factory()->create(['user_id' => $this->moderator->id]);
    $destination = Organizer::factory()->create();
    Event::factory()->create(['organizer_id' => $source->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
        ])
        ->assertOk();

    Mail::assertNothingSent();
});

test('moveEvents is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $source = Organizer::factory()->create();
    $destination = Organizer::factory()->create();

    $this->actingAs($user)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
        ])
        ->assertStatus(403);
});
