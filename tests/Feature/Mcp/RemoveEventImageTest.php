<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\RemoveEventImage;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

function removeToolUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function removeToolEvent(User $user, string $status = '0'): Event
{
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => $status]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

/**
 * Image rows at the given ranks, with their four stored variants on the fake
 * disk. Returns rank => image id.
 */
function seedImages(Event $event, array $ranks): array
{
    $ids = [];
    foreach ($ranks as $rank) {
        $base = "event-images/{$event->slug}/img-{$rank}";
        $ids[$rank] = $event->images()->create([
            'large_image_path' => "{$base}.webp",
            'thumb_image_path' => "{$base}-thumb.webp",
            'rank' => $rank,
        ])->id;
        foreach (["{$base}.webp", "{$base}.jpg", "{$base}-thumb.webp", "{$base}-thumb.jpg"] as $file) {
            Storage::disk('digitalocean')->put("/public/{$file}", 'bytes');
        }
    }

    if (in_array(0, $ranks, true)) {
        $event->update([
            'largeImagePath' => "event-images/{$event->slug}/img-0.webp",
            'thumbImagePath' => "event-images/{$event->slug}/img-0-thumb.webp",
        ]);
    }

    return $ids;
}

function removeImage(User $user, Event $event, int $imageId)
{
    return EiServer::actingAs($user)->tool(RemoveEventImage::class, [
        'event_slug' => $event->slug,
        'image_id' => $imageId,
    ]);
}

beforeEach(fn () => Storage::fake('digitalocean'));

test('remove-event-image deletes a draft\'s primary image row, its files, and the event\'s own image columns', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 1]);

    $response = removeImage($user, $event, $ids[0]);

    $response->assertOk();
    $response->assertSee('before it can be submitted');
    $event->refresh();

    expect($event->images()->where('rank', 0)->exists())->toBeFalse();
    expect($event->largeImagePath)->toBeNull();
    expect($event->thumbImagePath)->toBeNull();
    Storage::disk('digitalocean')->assertMissing("/public/event-images/{$event->slug}/img-0.webp");
    Storage::disk('digitalocean')->assertMissing("/public/event-images/{$event->slug}/img-0-thumb.jpg");

    // The gallery is untouched.
    expect($event->images()->pluck('rank')->all())->toBe([1]);
    Storage::disk('digitalocean')->assertExists("/public/event-images/{$event->slug}/img-1.webp");
});

test('remove-event-image closes the gap in the gallery like the wizard does', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 1, 2, 3, 4]);

    removeImage($user, $event, $ids[2])->assertOk();

    $remaining = $event->images()->orderBy('rank')->get();
    expect($remaining->pluck('rank')->all())->toBe([0, 1, 2, 3]);
    // The images that were at 3 and 4 are now at 2 and 3 — same rows, same files.
    expect($remaining->pluck('id')->all())->toBe([$ids[0], $ids[1], $ids[3], $ids[4]]);
    expect($remaining->pluck('large_image_path')->all())->toBe([
        "event-images/{$event->slug}/img-0.webp",
        "event-images/{$event->slug}/img-1.webp",
        "event-images/{$event->slug}/img-3.webp",
        "event-images/{$event->slug}/img-4.webp",
    ]);
    // Primary columns survive a gallery removal.
    expect($event->fresh()->largeImagePath)->toBe("event-images/{$event->slug}/img-0.webp");
});

test('removing two gallery images by id deletes the right files even though ranks shifted in between', function () {
    // The reason the tool takes an id, not a rank: after the first removal
    // the image that was at rank 3 sits at rank 2, so a second call by
    // (pre-read) rank would delete the wrong file.
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 1, 2, 3, 4]);

    removeImage($user, $event, $ids[2])->assertOk();
    removeImage($user, $event, $ids[3])->assertOk();

    expect($event->images()->orderBy('rank')->pluck('id')->all())->toBe([$ids[0], $ids[1], $ids[4]]);
    expect($event->images()->orderBy('rank')->pluck('rank')->all())->toBe([0, 1, 2]);
    Storage::disk('digitalocean')->assertMissing("/public/event-images/{$event->slug}/img-2.webp");
    Storage::disk('digitalocean')->assertMissing("/public/event-images/{$event->slug}/img-3.webp");
    Storage::disk('digitalocean')->assertExists("/public/event-images/{$event->slug}/img-4.webp");
});

test('the renumbering never touches another event\'s gallery', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 1, 2, 3]);

    $other = removeToolEvent(removeToolUser());
    $otherIds = seedImages($other, [0, 1, 2, 3]);

    removeImage($user, $event, $ids[1])->assertOk();

    expect($event->images()->orderBy('rank')->pluck('rank')->all())->toBe([0, 1, 2]);
    expect($other->images()->orderBy('rank')->pluck('id')->all())->toBe(array_values($otherIds));
    expect($other->images()->orderBy('rank')->pluck('rank')->all())->toBe([0, 1, 2, 3]);
});

test('the surviving gallery is renumbered 1..n like the wizard does, even when it started with gaps', function () {
    // attach-event-image accepts any rank 0-4, so a gallery can be sparse.
    // The wizard normalizes on every save; after a removal here the result
    // must be what the wizard would have produced.
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 2, 4]);

    removeImage($user, $event, $ids[2])->assertOk();

    $remaining = $event->images()->orderBy('rank')->get();
    expect($remaining->pluck('rank')->all())->toBe([0, 1]);
    expect($remaining->pluck('id')->all())->toBe([$ids[0], $ids[4]]);
    // Files are untouched by the renumber — only the rank column moved.
    Storage::disk('digitalocean')->assertExists("/public/event-images/{$event->slug}/img-4.webp");
    expect($remaining->last()->large_image_path)->toBe("event-images/{$event->slug}/img-4.webp");
});

test('a legacy cover backed by a non-zero rank is protected on a live event and cleared properly on a draft', function () {
    // Older rows can have the event's cover columns pointing at a gallery
    // row with no rank-0 row at all. ImageHandler::deleteImage only clears
    // the columns for rank 0, so without care the cards and map pins would
    // keep URLs to deleted files.
    $user = removeToolUser();

    $live = removeToolEvent($user, 'p');
    $liveIds = seedImages($live, [1, 2]);
    $live->update([
        'largeImagePath' => "event-images/{$live->slug}/img-1.webp",
        'thumbImagePath' => "event-images/{$live->slug}/img-1-thumb.webp",
    ]);

    $response = removeImage($user, $live, $liveIds[1]);
    $response->assertHasErrors();
    $response->assertSee('must keep a primary image');
    Storage::disk('digitalocean')->assertExists("/public/event-images/{$live->slug}/img-1.webp");
    // The other gallery image is still removable.
    removeImage($user, $live, $liveIds[2])->assertOk();

    $draft = removeToolEvent($user);
    $draftIds = seedImages($draft, [1, 2]);
    $draft->update([
        'largeImagePath' => "event-images/{$draft->slug}/img-1.webp",
        'thumbImagePath' => "event-images/{$draft->slug}/img-1-thumb.webp",
    ]);

    removeImage($user, $draft, $draftIds[1])->assertOk();
    $draft->refresh();
    expect($draft->largeImagePath)->toBeNull();
    expect($draft->thumbImagePath)->toBeNull();
    expect($draft->images()->pluck('rank')->all())->toBe([1]);
});

test('remove-event-image lists the event\'s images when the id is unknown or belongs to another event', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user);
    $ids = seedImages($event, [0, 2]);

    $other = removeToolEvent($user);
    $otherIds = seedImages($other, [0]);

    // Someone else's image id, sent with an event the user CAN manage: the
    // lookup is scoped to the named event, so it is simply "not found".
    $response = removeImage($user, $event, $otherIds[0]);

    $response->assertHasErrors();
    $response->assertSee('No image with that id on this event');
    $response->assertSee("id {$ids[0]} (rank 0), id {$ids[2]} (rank 2)");
    expect($event->images()->count())->toBe(2);
    expect($other->images()->count())->toBe(1);

    removeImage($user, $event, 999999)->assertHasErrors();
});

test('remove-event-image denies non-members with the same message as an unknown slug', function () {
    $owner = removeToolUser();
    $event = removeToolEvent($owner);
    $ids = seedImages($event, [0]);
    $stranger = removeToolUser();

    $denied = removeImage($stranger, $event, $ids[0]);
    $unknown = EiServer::actingAs($stranger)->tool(RemoveEventImage::class, ['event_slug' => 'no-such-event', 'image_id' => $ids[0]]);

    $denied->assertHasErrors();
    $unknown->assertHasErrors();
    $denied->assertSee('No event with that slug that you can edit');
    $unknown->assertSee('No event with that slug that you can edit');
    expect($event->images()->count())->toBe(1);
});

test('a moderator whose token lacks mcp:moderate is treated like a stranger', function () {
    $owner = removeToolUser();
    $event = removeToolEvent($owner);
    $ids = seedImages($event, [0, 1]);
    $moderator = removeToolUser('m');

    Passport::actingAs($moderator, ['mcp:use']);
    EiServer::actingAs($moderator, 'api')
        ->tool(RemoveEventImage::class, ['event_slug' => $event->slug, 'image_id' => $ids[1]])
        ->assertHasErrors();
    expect($event->images()->count())->toBe(2);

    Passport::actingAs($moderator, ['mcp:use', 'mcp:moderate']);
    EiServer::actingAs($moderator, 'api')
        ->tool(RemoveEventImage::class, ['event_slug' => $event->slug, 'image_id' => $ids[1]])
        ->assertOk();
    expect($event->images()->count())->toBe(1);
});

test('remove-event-image refuses an event under review unless the caller is a moderator', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user, 'r');
    $ids = seedImages($event, [0, 1]);

    removeImage($user, $event, $ids[1])->assertHasErrors();
    expect($event->images()->count())->toBe(2);

    removeImage(removeToolUser('m'), $event, $ids[1])->assertOk();
    expect($event->images()->count())->toBe(1);
});

test('remove-event-image respects the 90-day edit lock', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user, 'p');
    $event->update(['closingDate' => now()->subDays(Event::EDIT_WINDOW_DAYS + 1)]);
    $ids = seedImages($event, [0, 1]);

    removeImage($user, $event, $ids[1])->assertHasErrors();
    expect($event->images()->count())->toBe(2);
});

test('remove-event-image keeps the primary image on a submitted or live event, for moderators too, while gallery removal still works', function (string $status) {
    $user = removeToolUser();
    $event = removeToolEvent($user, $status);
    $ids = seedImages($event, [0, 1]);
    $moderator = removeToolUser('m');

    // Under review, the organizer is locked out entirely; the moderator is
    // the one who could otherwise strip the cover and then approve it bare.
    $actor = $status === 'r' ? $moderator : $user;

    $response = removeImage($actor, $event, $ids[0]);

    $response->assertHasErrors();
    $response->assertSee('must keep a primary image');
    expect($event->fresh()->largeImagePath)->not->toBeNull();
    Storage::disk('digitalocean')->assertExists("/public/event-images/{$event->slug}/img-0.webp");

    removeImage($moderator, $event, $ids[0])->assertHasErrors();

    removeImage($actor, $event, $ids[1])->assertOk();
    expect($event->images()->pluck('rank')->all())->toBe([0]);
})->with(['r', 'p', 'e']);

test('a rejected event can drop its primary image so it can be redone before resubmission', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user, 'n');
    $ids = seedImages($event, [0]);

    removeImage($user, $event, $ids[0])->assertOk();
    expect($event->fresh()->largeImagePath)->toBeNull();
});

test('remove-event-image validates its input', function () {
    $user = removeToolUser();
    $event = removeToolEvent($user);

    EiServer::actingAs($user)->tool(RemoveEventImage::class, ['event_slug' => $event->slug, 'image_id' => 'abc'])
        ->assertHasErrors();
    EiServer::actingAs($user)->tool(RemoveEventImage::class, ['event_slug' => $event->slug])
        ->assertHasErrors();
});
