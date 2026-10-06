<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Web-path parity tests for the UpdateEventAction extraction: every branch
 * of the old HostEventController@update body exercised through the REAL
 * web/api endpoints, exactly as the Vue wizard calls them — including the
 * web-only branches (multipart images, JSON videos) the MCP tools never use.
 */
function extractionUser(): User
{
    return User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
}

function extractionEvent(User $user, array $overrides = []): Event
{
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(array_merge([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'status' => '0',
    ], $overrides));
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

// ── location ───────────────────────────────────────────────────────────

test('web update saves location, mirrors location_latlon, and applies status', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'location' => [
            'venue' => 'Warehouse 13',
            'city' => 'Los Angeles',
            'latitude' => 34.05,
            'longitude' => -118.24,
        ],
        'status' => '5',
    ])->assertStatus(200);

    $event->refresh();
    expect($event->location->venue)->toBe('Warehouse 13');
    expect($event->location_latlon)->toEqual(['lat' => 34.05, 'lon' => -118.24]);
    expect($event->status)->toBe('5');
});

// ── attendance/category compatibility ──────────────────────────────────

test('web update dissociates an incompatible category and resets status to 1', function () {
    $user = extractionUser();
    // Category restricted to in-person (attendance type 1) only.
    $category = Category::factory()->create(['applicable_attendance_types' => [1]]);
    $event = extractionEvent($user, ['category_id' => $category->id, 'status' => '6']);

    // Switching the event to remote (2) is incompatible with the category.
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'attendance_type_id' => 2,
    ])->assertStatus(200);

    $event->refresh();
    expect($event->category_id)->toBeNull();
    expect($event->status)->toBe('1');
    expect((bool) $event->hasLocation)->toBeFalse();
});

test('web update keeps a compatible category and syncs hasLocation', function () {
    $user = extractionUser();
    $category = Category::factory()->create(['applicable_attendance_types' => null]); // supports all
    $event = extractionEvent($user, ['category_id' => $category->id]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'attendance_type_id' => 1,
    ])->assertStatus(200);

    $event->refresh();
    expect($event->category_id)->toBe($category->id);
    expect((bool) $event->hasLocation)->toBeTrue();
});

// ── shows / tickets / price range ──────────────────────────────────────

test('web update creates shows then tickets, price ranges, and closing date', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => ['2026-11-05 00:00:00', '2026-11-06 00:00:00'],
        'tickets' => [
            ['name' => 'PWYC', 'ticket_price' => 0, 'currency' => 'USD', 'description' => ''],
            ['name' => 'VIP', 'ticket_price' => 80, 'currency' => 'USD', 'description' => 'Front row'],
        ],
    ])->assertStatus(200);

    $event->refresh();
    expect($event->shows)->toHaveCount(2);
    expect($event->tickets)->toHaveCount(2);
    expect($event->priceranges)->toHaveCount(2);
    expect($event->price_range)->toBe('PWYC - $80');
    expect($event->closingDate)->not->toBeNull();
});

test('web update showtype change wipes and recreates shows and tickets', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'dateArray' => ['2026-11-05 00:00:00'],
        'tickets' => [['name' => 'GA', 'ticket_price' => 10, 'currency' => 'USD', 'description' => '']],
    ])->assertStatus(200);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 'a',
        'always_config' => ['endDate' => '2026-12-31 00:00:00'],
    ])->assertStatus(200);

    $event->refresh();
    expect($event->showtype)->toBe('a');
    expect($event->shows)->toHaveCount(1);
    // The tiers live on the event, so the new schedule keeps them.
    expect($event->tickets->pluck('name'))->toContain('GA');
});

// ── embargo flips ──────────────────────────────────────────────────────

test('web update flips published to embargoed when an embargo date is set', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['status' => 'p', 'showtype' => 'a']);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 'a',
        'always_config' => ['endDate' => '2026-12-31 00:00:00'],
        'embargo_date' => now()->addWeek()->format('Y-m-d H:i:s'),
    ])->assertStatus(200);

    expect($event->fresh()->status)->toBe('e');
});

test('web update flips embargoed back to published when the embargo clears', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['status' => 'e', 'showtype' => 'a']);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 'a',
        'always_config' => ['endDate' => '2026-12-31 00:00:00'],
        'embargo_date' => null,
    ])->assertStatus(200);

    expect($event->fresh()->status)->toBe('p');
});

// ── remote locations ───────────────────────────────────────────────────

test('web update firstOrCreates and syncs remote locations', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'remotelocations' => [['name' => 'Zoom'], ['name' => 'My Custom Platform']],
        'remote_description' => 'Join via the link emailed to you.',
    ])->assertStatus(200);

    $event->refresh();
    expect($event->remotelocations->pluck('name')->sort()->values()->all())
        ->toBe(['My Custom Platform', 'Zoom']);

    // Re-sync with one — the other detaches but is not deleted globally.
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'remotelocations' => [['name' => 'Zoom']],
    ])->assertStatus(200);

    expect($event->fresh()->remotelocations->pluck('name')->all())->toBe(['Zoom']);
});

// ── multipart images (web-only branch) ─────────────────────────────────

test('web update uploads, replaces, reorders, and deletes images via multipart', function () {
    Storage::fake('digitalocean');
    $user = extractionUser();
    $event = extractionEvent($user);

    // 1. Upload a primary (rank 0) and a gallery (rank 1) image.
    $this->actingAs($user)->post("/api/hosting/event/{$event->slug}", [
        'images' => [
            UploadedFile::fake()->image('primary.jpg', 900, 1200),
            UploadedFile::fake()->image('gallery.jpg', 1200, 800),
        ],
        'ranks' => [0, 1],
    ])->assertStatus(200);

    $event->refresh();
    expect($event->images)->toHaveCount(2);
    expect($event->largeImagePath)->not->toBeNull();

    // 2. Replace rank 0 — count stays 2, path changes.
    $oldPrimary = $event->images()->where('rank', 0)->first()->large_image_path;
    $this->actingAs($user)->post("/api/hosting/event/{$event->slug}", [
        'images' => [UploadedFile::fake()->image('newprimary.jpg', 900, 1200)],
        'ranks' => [0],
        'currentImages' => json_encode([['id' => $event->images()->where('rank', 1)->first()->id, 'rank' => 1]]),
    ])->assertStatus(200);

    $event->refresh();
    expect($event->images)->toHaveCount(2);
    expect($event->images()->where('rank', 0)->first()->large_image_path)->not->toBe($oldPrimary);

    // 3. Reorder: move the gallery image to rank 2.
    $galleryId = $event->images()->where('rank', 1)->first()->id;
    $this->actingAs($user)->post("/api/hosting/event/{$event->slug}", [
        'currentImages' => json_encode([['id' => $galleryId, 'rank' => 2]]),
    ])->assertStatus(200);

    expect($event->images()->find($galleryId)->rank)->toBe(2);

    // 4. Delete the gallery image by path.
    $galleryPath = $event->images()->find($galleryId)->large_image_path;
    $this->actingAs($user)->post("/api/hosting/event/{$event->slug}", [
        'deletedImages' => json_encode([$galleryPath]),
    ])->assertStatus(200);

    expect($event->images()->count())->toBe(1);
});

// ── videos (web-only JSON branch) ──────────────────────────────────────

test('web update replaces videos from the JSON field and stores slideshow preference', function () {
    $user = extractionUser();
    $event = extractionEvent($user);
    $event->videos()->create(['platform' => 'youtube', 'url' => 'https://youtube.com/old', 'rank' => 0]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'videos' => json_encode([
            ['platform' => 'tiktok', 'url' => 'https://tiktok.com/new', 'rank' => 0, 'id' => 'abc123'],
        ]),
        'videoSlideshow' => '1',
    ])->assertStatus(200);

    $event->refresh();
    expect($event->videos)->toHaveCount(1);
    expect($event->videos->first()->platform)->toBe('tiktok');
    expect($event->videos->first()->platform_video_id)->toBe('abc123');
    expect($event->video)->toBe('1');
});

// ── genres + cache invalidation ────────────────────────────────────────

test('web update on a published event clears the active-genres cache', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['status' => 'p']);
    Cache::forever('active-genres', ['stale']);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'genres' => [['name' => 'Sci-Fi']],
    ])->assertStatus(200);

    // saveGenres normalizes names: ucfirst(strtolower('Sci-Fi')) => 'Sci-fi'
    expect($event->fresh()->genres->pluck('name'))->toContain('Sci-fi');
    expect(Cache::has('active-genres'))->toBeFalse();
});

// ── misc single-field branches ─────────────────────────────────────────

test('web update sets contact level, interactive level, age limit, and call to action', function () {
    $user = extractionUser();
    $event = extractionEvent($user);
    $contact = \App\Models\Events\ContactLevel::create(['name' => 'None', 'user_id' => $user->id]);
    $interactive = \App\Models\Events\InteractiveLevel::create(['name' => 'Passive', 'description' => 'x', 'user_id' => $user->id]);
    $age = \App\Models\Events\AgeLimit::forceCreate(['name' => '18+', 'age' => 18]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'contactLevel' => ['id' => $contact->id, 'name' => $contact->name],
        'interactiveLevel' => ['id' => $interactive->id, 'name' => $interactive->name, 'description' => 'x'],
        'ageLimit' => ['id' => $age->id],
        'call_to_action' => 'Get Tickets Now',
    ])->assertStatus(200);

    $event->refresh();
    expect($event->contactLevels->first()->id)->toBe($contact->id);
    expect($event->interactive_level_id)->toBe($interactive->id);
    expect($event->age_limits_id)->toBe($age->id);
    expect($event->call_to_action)->toBe('Get Tickets Now');
});

test('web update advisories and wheelchair status persist on the advisories row', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'advisories' => ['sexual' => true, 'sexualDescription' => 'Brief nudity', 'audience' => 'Adults only'],
        'wheelchairReady' => true,
    ])->assertStatus(200);

    $advisories = $event->fresh()->advisories;
    expect((bool) $advisories->sexual)->toBeTrue();
    expect($advisories->sexualDescription)->toBe('Brief nudity');
    expect($advisories->audience)->toBe('Adults only');
    expect((bool) $advisories->wheelchairReady)->toBeTrue();
});

test('web update saves a partial wheelchair answer with its explanation', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'wheelchairAccess' => 'partial',
        'wheelchairDescription' => 'Upstairs is stairs only',
    ])->assertStatus(200);

    $advisories = $event->fresh()->advisories;
    expect($advisories->wheelchairAccess)->toBe('partial');
    expect($advisories->wheelchairDescription)->toBe('Upstairs is stairs only');
    // The old yes/no stays in step: only full access is a yes.
    expect((bool) $advisories->wheelchairReady)->toBeFalse();
});

test('web update refuses partial or no wheelchair access without an explanation', function (string $level) {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'wheelchairAccess' => $level,
        'wheelchairDescription' => '',
    ])->assertStatus(422)->assertJsonValidationErrors('wheelchairDescription');
})->with(['partial', 'none']);

test('web update to full wheelchair access clears the old explanation', function () {
    $user = extractionUser();
    $event = extractionEvent($user);
    $event->advisories()->update(['wheelchairAccess' => 'none', 'wheelchairDescription' => 'Stairs', 'wheelchairReady' => false]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'wheelchairAccess' => 'full',
    ])->assertStatus(200);

    $advisories = $event->fresh()->advisories;
    expect($advisories->wheelchairAccess)->toBe('full');
    expect($advisories->wheelchairDescription)->toBeNull();
    expect((bool) $advisories->wheelchairReady)->toBeTrue();
});

test('web update still accepts the old yes/no wheelchair answer', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'wheelchairReady' => false,
    ])->assertStatus(200);

    $advisories = $event->fresh()->advisories;
    expect($advisories->wheelchairAccess)->toBe('none');
    // Never left without an explanation.
    expect($advisories->wheelchairDescription)->toBe(\App\Models\Events\Advisory::WHEELCHAIR_DEFAULT_EXPLANATION);
});

test('web update refuses a blank wheelchair explanation sent on its own', function () {
    $user = extractionUser();
    $event = extractionEvent($user);
    $event->advisories()->update(['wheelchairAccess' => 'partial', 'wheelchairDescription' => 'Attic is ladder only', 'wheelchairReady' => false]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'name' => 'Renamed before the refusal',
        'wheelchairDescription' => '',
    ])->assertStatus(422)->assertJsonValidationErrors('wheelchairDescription');

    expect($event->fresh()->advisories->wheelchairDescription)->toBe('Attic is ladder only');
    // Refused before anything was written.
    expect($event->fresh()->name)->not->toBe('Renamed before the refusal');
});

test('an old browser tab sending no keeps a partial wheelchair answer partial', function () {
    $user = extractionUser();
    $event = extractionEvent($user);
    $event->advisories()->update(['wheelchairAccess' => 'partial', 'wheelchairDescription' => 'Attic is ladder only', 'wheelchairReady' => false]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'wheelchairReady' => false,
        'mobilityAdvisories' => [
            ['name' => 'Not Wheelchair Accessible'],
            ['name' => 'Uneven terrain'],
        ],
    ])->assertStatus(200);

    $advisories = $event->fresh()->advisories;
    expect($advisories->wheelchairAccess)->toBe('partial');
    // The old tab's chip is swapped for the one matching the answer.
    $slugs = $event->fresh()->mobilityAdvisories->pluck('slug');
    expect($slugs)->toContain('partially-wheelchair-accessible', 'uneven-terrain');
    expect($slugs)->not->toContain('not-wheelchair-accessible');
    expect($advisories->wheelchairDescription)->toBe('Attic is ladder only');
});

test('the old yes/no wins when code from before the change answered last', function () {
    $user = extractionUser();
    $event = extractionEvent($user);

    // Partial, then an old deploy wrote only "yes".
    $event->advisories()->update(['wheelchairAccess' => 'partial', 'wheelchairReady' => true]);
    expect($event->fresh()->advisories->wheelchairLevel())->toBe('full');

    // Full, then an old deploy wrote only "no".
    $event->advisories()->update(['wheelchairAccess' => 'full', 'wheelchairReady' => false]);
    expect($event->fresh()->advisories->wheelchairLevel())->toBe('none');
});

// ── show_times on the shared write path ────────────────────────────────

test('web update clears show_times when the wizard sends it empty', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['showtype' => 's', 'show_times' => 'Fridays 8pm']);
    $event->shows()->create(['date' => now()->addDays(10)->format('Y-m-d H:i:s')]);

    // Show::updateEvent only writes show_times when the request carries the key,
    // so that partial API/MCP edits stop blanking it. The wizard's Dates step
    // always sends the key (dates.vue), so clearing from the website must still
    // work — this is the guard on that.
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'dateArray' => [now()->addDays(10)->format('Y-m-d H:i:s')],
        'show_times' => null,
    ])->assertStatus(200);

    expect($event->fresh()->show_times)->toBeNull();
});

test('web update rewrites show_times when the wizard sends a new value', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['showtype' => 's', 'show_times' => 'Fridays 8pm']);
    $event->shows()->create(['date' => now()->addDays(10)->format('Y-m-d H:i:s')]);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'dateArray' => [now()->addDays(10)->format('Y-m-d H:i:s')],
        'show_times' => 'Saturdays 2pm & 8pm',
    ])->assertStatus(200);

    expect($event->fresh()->show_times)->toBe('Saturdays 2pm & 8pm');
});

test('a schedule edit that omits show_times leaves it standing', function () {
    $user = extractionUser();
    $event = extractionEvent($user, ['showtype' => 's', 'show_times' => 'Fridays 8pm']);
    $event->shows()->create(['date' => now()->addDays(10)->format('Y-m-d H:i:s')]);

    // The regression this whole change exists for: an absent key used to read as
    // null and blank the showtimes text.
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'dateArray' => [
            now()->addDays(10)->format('Y-m-d H:i:s'),
            now()->addDays(17)->format('Y-m-d H:i:s'),
        ],
    ])->assertStatus(200);

    $after = $event->fresh();
    expect($after->show_times)->toBe('Fridays 8pm')
        ->and($after->shows()->count())->toBe(2);
});
