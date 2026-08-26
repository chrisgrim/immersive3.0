<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Category;
use App\Models\Event;
use App\Models\NameChangeRequest;
use App\Models\Organizer;
use App\Models\User;
use App\Support\RecurringDates;
use App\Support\Validation\EventUpdateRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function writeToolUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function writeToolOrganizer(User $user): Organizer
{
    return Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
}

/** A draft created the way production creates them (location + advisories rows exist). */
function draftFor(Organizer $organizer, User $user, array $overrides = []): Event
{
    $event = Event::factory()->create(array_merge([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'status' => '0',
    ], $overrides));
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

/** A UTC "Y-m-d H:i:s" at $hour local in $tz, $days from today (negative = past). */
function scheduleDayAt(int $days, int $hour, string $tz = 'America/Toronto'): string
{
    return now($tz)->addDays($days)->setTime($hour, 0, 0)->utc()->format('Y-m-d H:i:s');
}

/** A UTC "Y-m-d H:i:s" at noon in $tz, $days from today (negative = past). */
function scheduleDay(int $days, string $tz = 'America/Toronto'): string
{
    return scheduleDayAt($days, 12, $tz);
}

/** Number of DB queries run while $fn executes. */
function queriesDuring(callable $fn): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $fn();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(fn () => Mail::fake());

// ── create-organizer ───────────────────────────────────────────────────

test('create-organizer creates a review-status organizer with owner pivot', function () {
    $user = writeToolUser();

    $response = EiServer::actingAs($user)->tool(CreateOrganizer::class, [
        'name' => 'Midnight Masquerade Co',
        'description' => 'We make immersive masquerades.',
    ]);

    $response->assertOk()->assertSee('Midnight Masquerade Co');

    $organizer = Organizer::where('name', 'Midnight Masquerade Co')->first();
    expect($organizer)->not->toBeNull();
    expect($organizer->status)->toBe('r');
    expect($organizer->users()->first()->membership->role ?? $organizer->users()->first()->pivot->role)->toBe('owner');
    expect($user->fresh()->current_team_id)->toBe($organizer->id);
});

test('create-organizer strips a leading @ from twitter/instagram handles', function () {
    $user = writeToolUser();

    EiServer::actingAs($user)->tool(CreateOrganizer::class, [
        'name' => 'Doubled At Co',
        'description' => 'We make immersive things.',
        'instagramHandle' => '@doubledat',
        'twitterHandle' => '@doubledat',
    ])->assertOk();

    $organizer = Organizer::where('name', 'Doubled At Co')->first();
    expect($organizer->instagramHandle)->toBe('doubledat');
    expect($organizer->twitterHandle)->toBe('doubledat');
});

test('create-organizer surfaces duplicates and honors acknowledge_duplicate', function () {
    $user = writeToolUser();
    Organizer::factory()->create(['name' => 'Echo Chamber', 'status' => 'p', 'user_id' => writeToolUser()->id]);

    $first = EiServer::actingAs($user)->tool(CreateOrganizer::class, [
        'name' => 'Echo Chamber',
        'description' => 'A new group.',
    ]);
    $first->assertOk()->assertSee('duplicate_name');
    expect(Organizer::where('name', 'Echo Chamber')->count())->toBe(1);

    $second = EiServer::actingAs($user)->tool(CreateOrganizer::class, [
        'name' => 'Echo Chamber',
        'description' => 'A new group.',
        'acknowledge_duplicate' => true,
    ]);
    $second->assertOk();
    expect(Organizer::where('name', 'Echo Chamber')->count())->toBe(2);
});

test('create-organizer blocks a fourth pending organizer for non-moderators', function () {
    $user = writeToolUser();
    Organizer::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'r']);

    $response = EiServer::actingAs($user)->tool(CreateOrganizer::class, [
        'name' => 'One Too Many',
        'description' => 'Should be blocked.',
    ]);

    $response->assertHasErrors();
    expect(Organizer::where('name', 'One Too Many')->exists())->toBeFalse();

    // Approved/draft organizers do not count against the pending cap.
    $other = writeToolUser();
    Organizer::factory()->count(3)->create(['user_id' => $other->id, 'status' => 'p']);
    EiServer::actingAs($other)->tool(CreateOrganizer::class, [
        'name' => 'Fine To Create',
        'description' => 'Published ones do not count.',
    ])->assertOk();
});

test('moderators bypass the pending organizer cap', function () {
    $moderator = writeToolUser('m');
    Organizer::factory()->count(3)->create(['user_id' => $moderator->id, 'status' => 'r']);

    EiServer::actingAs($moderator)->tool(CreateOrganizer::class, [
        'name' => 'Moderator Overflow',
        'description' => 'Allowed for staff.',
    ])->assertOk();
});

test('create-organizer rejects invalid input', function () {
    $response = EiServer::actingAs(writeToolUser())->tool(CreateOrganizer::class, [
        'name' => '!!!',
        'description' => 'x',
    ]);

    $response->assertHasErrors();
});

// ── update-organizer ───────────────────────────────────────────────────

test('update-organizer adds contact and social fields after creation', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);

    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'website' => 'https://thelostland.com',
        'instagramHandle' => 'thelostland',
        'email' => 'hello@thelostland.com',
    ]);

    $response->assertOk();
    $organizer->refresh();
    expect($organizer->website)->toBe('https://thelostland.com');
    expect($organizer->instagramHandle)->toBe('thelostland');
    expect($organizer->email)->toBe('hello@thelostland.com');
});

test('update-organizer strips a leading @ from twitter/instagram handles', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);

    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'instagramHandle' => '@thelostland',
        'twitterHandle' => '@thelostland',
    ]);

    $response->assertOk();
    $organizer->refresh();
    expect($organizer->instagramHandle)->toBe('thelostland');
    expect($organizer->twitterHandle)->toBe('thelostland');
});

test('update-organizer denies non-members', function () {
    $organizer = writeToolOrganizer(writeToolUser());
    $stranger = writeToolUser();

    $response = EiServer::actingAs($stranger)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'description' => 'Hijacked.',
    ]);

    $response->assertHasErrors();
});

test('update-organizer files a name-change request when renaming a published organizer', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user); // status 'p'
    $originalName = $organizer->name;

    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'The Lost Island',
    ]);

    $response->assertOk()->assertSee('submitted for admin review');

    // The live name is untouched — it only changes once an admin approves.
    expect($organizer->fresh()->name)->toBe($originalName);

    // A pending request was filed the same way the website files it.
    $req = NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)
        ->first();
    expect($req)->not->toBeNull();
    expect($req->status)->toBe('pending');
    expect($req->current_name)->toBe($originalName);
    expect($req->requested_name)->toBe('The Lost Island');
    expect($req->user_id)->toBe($user->id);
});

test('update-organizer applies other fields but refuses a second pending name change', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user); // status 'p'
    $originalName = $organizer->name;

    // First rename files a pending request.
    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'First Requested Name',
    ])->assertOk();

    // Second attempt bundles a real field change: the website applies, but the
    // duplicate rename is rejected without touching the existing request.
    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'Second Requested Name',
        'website' => 'https://thelostisland.example.com',
    ]);

    $response->assertOk()->assertSee('already have a pending name change');

    $organizer->refresh();
    expect($organizer->website)->toBe('https://thelostisland.example.com');
    expect($organizer->name)->toBe($originalName);

    $pending = NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)
        ->where('status', 'pending')->get();
    expect($pending)->toHaveCount(1);
    expect($pending->first()->requested_name)->toBe('First Requested Name');
});

test('update-organizer renames a draft organizer directly without a request', function () {
    $user = writeToolUser();
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'd']);

    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'Renamed Draft',
    ])->assertOk();

    expect($organizer->fresh()->name)->toBe('Renamed Draft');
    expect(NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)->exists())->toBeFalse();
});

test('update-organizer files no name-change request when the name is unchanged', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user); // status 'p'

    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => $organizer->name, // same name — must not file a request
        'description' => 'A fresh blurb.',
    ])->assertOk();

    expect(NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)->exists())->toBeFalse();
    expect($organizer->fresh()->description)->toBe('A fresh blurb.');
});

test('update-organizer does not orphan a name-change request when the image download fails', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user); // status 'p'
    // Skip DNS-based URL validation so Http::fake can answer the request.
    app()->bind(\App\Services\RemoteImageIngest::class, fn () => new \App\Services\RemoteImageIngest(fn () => true));
    Http::fake(['images.example.com/*' => Http::response('', 404)]);

    // A published rename bundled with a logo whose download fails: the whole
    // call errors, and crucially no pending name-change request is left behind.
    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'Renamed Via Failed Call',
        'image_url' => 'https://images.example.com/logo.png',
    ]);

    $response->assertHasErrors();
    expect(NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)->exists())->toBeFalse();

    // The rename can still be filed on a clean retry (the guard is not tripped).
    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'name' => 'Renamed Via Failed Call',
    ])->assertOk();
    expect(NameChangeRequest::where('requestable_type', Organizer::class)
        ->where('requestable_id', $organizer->id)->where('status', 'pending')->count())->toBe(1);
});

test('update-organizer refuses any edit while the organizer is under review', function () {
    $user = writeToolUser();
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'r']);

    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'description' => 'Trying to sneak an edit in.',
    ]);

    $response->assertHasErrors();
    expect($organizer->fresh()->description)->not->toBe('Trying to sneak an edit in.');
});

test('moderators can edit an organizer under review', function () {
    $moderator = writeToolUser('m');
    $organizer = Organizer::factory()->create(['user_id' => $moderator->id, 'status' => 'r']);

    EiServer::actingAs($moderator)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'description' => 'Moderator fix during review.',
    ])->assertOk();

    expect($organizer->fresh()->description)->toBe('Moderator fix during review.');
});

test('update-event refuses edits while the event is under review', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'r', 'name' => 'Submitted Event']);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Sneaky Rename',
        'acknowledge_duplicate' => true,
    ]);

    $response->assertHasErrors();
    expect($event->fresh()->name)->toBe('Submitted Event');
});

test('attach-event-image refuses while the event is under review', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'r']);

    $response = EiServer::actingAs($user)->tool(\App\Mcp\Tools\AttachEventImage::class, [
        'event_slug' => $event->slug,
        'image_url' => 'https://images.example.com/pixel.png',
        'rank' => 0,
    ]);

    $response->assertHasErrors();
    expect($event->images()->count())->toBe(0);
});

test('update-organizer rejects a non-https website', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);

    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateOrganizer::class, [
        'organizer_slug' => $organizer->slug,
        'website' => 'http://insecure.com',
    ])->assertHasErrors();
});

// ── create-event-draft ─────────────────────────────────────────────────

test('create-event-draft creates a draft with location and advisories rows', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);

    $response = EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
        'name' => 'The Vanishing Hotel',
    ]);

    $response->assertOk()->assertSee('The Vanishing Hotel');

    $event = Event::withoutGlobalScopes()->where('organizer_id', $organizer->id)->first();
    expect($event->status)->toBe('0');
    expect($event->location)->not->toBeNull();
    expect($event->advisories)->not->toBeNull();
    expect($event->user_id)->toBe($user->id);
});

test('create-event-draft refuses an organizer the user does not belong to', function () {
    $user = writeToolUser();
    writeToolOrganizer($user); // has a team, passes can:host
    $othersOrg = writeToolOrganizer(writeToolUser());

    $response = EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $othersOrg->id,
    ]);

    $response->assertHasErrors();
    expect(Event::withoutGlobalScopes()->where('organizer_id', $othersOrg->id)->count())->toBe(0);
});

test('create-event-draft refuses users with no teams', function () {
    $organizer = writeToolOrganizer(writeToolUser());

    $response = EiServer::actingAs(writeToolUser())->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
    ]);

    $response->assertHasErrors();
});

test('create-event-draft enforces the unpublished cap for non-admins', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);

    // One below the cap still goes through...
    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS - 1)
        ->create(['organizer_id' => $organizer->id, 'status' => '0']);

    EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
    ])->assertOk()->assertSee('Draft created.');

    // ...and that draft puts the organizer at the cap, so the next one stops.
    EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
    ])->assertHasErrors();
});

test('create-event-draft duplicate names require acknowledgement', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);
    Event::factory()->create(['name' => 'Sleep No More', 'organizer_id' => $organizer->id, 'status' => 'p']);

    $blocked = EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
        'name' => 'Sleep No More',
    ]);
    $blocked->assertOk()->assertSee('duplicate_name');

    $allowed = EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
        'name' => 'Sleep No More',
        'acknowledge_duplicate' => true,
    ]);
    $allowed->assertOk()->assertSee('Draft created');
});

// ── update-event ───────────────────────────────────────────────────────

test('update-event updates basic fields', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);
    $event = draftFor($organizer, $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Renamed Experience',
        'tag_line' => 'You will not believe it',
        'description' => 'A fully immersive walk-through experience.',
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->name)->toBe('Renamed Experience');
    expect($event->tag_line)->toBe('You will not believe it');
});

test('update-event cannot set status or publish', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);
    $event = draftFor($organizer, $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'status' => 'p',
        'name' => 'Sneaky Publish',
    ]);

    $response->assertOk();
    expect($event->fresh()->status)->toBe('0');
});

test('update-event denies non-members', function () {
    $owner = writeToolUser();
    $event = draftFor(writeToolOrganizer($owner), $owner);

    $stranger = writeToolUser();
    writeToolOrganizer($stranger);

    $response = EiServer::actingAs($stranger)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Hijacked',
    ]);

    $response->assertHasErrors();
    expect($event->fresh()->name)->not->toBe('Hijacked');
});

test('update-event saves location and mirrors location_latlon', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
        'location' => [
            'venue' => 'The Old Mill',
            'city' => 'Brooklyn',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ],
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->location->venue)->toBe('The Old Mill');
    expect($event->location_latlon['lat'])->toBe(40.7128);
});

test('update-event persists top-level fields even when location is in the same call', function () {
    // The shared action skips the top-level mass-assign when `location` is
    // present (web wizard sends location in its own step); the MCP tool must
    // split the call so combined payloads lose nothing.
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $category = Category::factory()->create(['remote' => false]);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'description' => 'Combined-call description',
        'category_id' => $category->id,
        'attendance_type_id' => 1,
        'location' => ['venue' => 'Combined Hall', 'latitude' => 41.0, 'longitude' => -73.0],
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->description)->toBe('Combined-call description');
    expect($event->category_id)->toBe($category->id);
    expect((bool) $event->hasLocation)->toBeTrue();
    expect($event->location->venue)->toBe('Combined Hall');
    expect($event->location_latlon['lat'])->toEqual(41.0);
});

test('update-event saves shows and tickets together in the right order', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [scheduleDay(30, 'America/New_York'), scheduleDay(31, 'America/New_York')],
        'tickets' => [
            ['name' => 'General', 'ticket_price' => 35.00, 'currency' => '$', 'description' => 'Standard entry'],
        ],
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->shows)->toHaveCount(2);
    expect($event->shows->first()->tickets)->toHaveCount(1);
    expect($event->price_range)->toContain('35');
    expect($event->showtype)->toBe('s');
});

test('update-event enforces the ticket tier cap it advertises', function () {
    // The cap used to exist only in the wizard UI, so this path could create
    // any number of tiers while the tool's own description claimed a limit.
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $tiers = collect(range(1, \App\Support\Validation\EventUpdateRules::MAX_TICKET_TIERS + 1))
        ->map(fn ($i) => ['name' => "Tier {$i}", 'ticket_price' => $i, 'currency' => '$', 'description' => ''])
        ->all();

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [scheduleDay(30, 'America/New_York')],
        'tickets' => $tiers,
    ]);

    // UpdateEvent reports validation failures as a successful response whose
    // CONTENT names the failure, not as a protocol-level error — so
    // assertHasErrors() finds nothing here even though the call was rejected
    // (which is how this test first passed against an unenforced cap).
    $response->assertOk()->assertSee('validation_failed')->assertSee('tickets');

    $event->refresh();
    expect($event->shows->first()?->tickets ?? collect())->toHaveCount(0);
});

test('update-event accepts a tier count right at the cap', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $tiers = collect(range(1, \App\Support\Validation\EventUpdateRules::MAX_TICKET_TIERS))
        ->map(fn ($i) => ['name' => "Tier {$i}", 'ticket_price' => $i, 'currency' => '$', 'description' => ''])
        ->all();

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [scheduleDay(30, 'America/New_York')],
        'tickets' => $tiers,
    ])->assertOk();

    $event->refresh();
    expect($event->shows->first()->tickets)->toHaveCount(\App\Support\Validation\EventUpdateRules::MAX_TICKET_TIERS);
});

test('update-event refuses tickets before dates exist', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tickets' => [['name' => 'General', 'ticket_price' => 10, 'currency' => '$']],
    ]);

    $response->assertHasErrors();
});

test('update-event rejects invalid category ids with field errors', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'category_id' => 999999,
    ]);

    $response->assertOk()->assertSee('validation_failed')->assertSee('category_id');
});

test('update-event applies category and genres', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $category = Category::factory()->create(['remote' => false]);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'category_id' => $category->id,
        'genres' => [['name' => 'Horror']],
    ]);

    $response->assertOk();
    $event->refresh();
    expect($event->category_id)->toBe($category->id);
    expect($event->genres->pluck('name'))->toContain('Horror');
});

test('update-event auto-adds the sexual content chip like the wizard does', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'advisories' => ['sexual' => false, 'audience' => 'Watchers'],
    ])->assertOk();

    expect($event->fresh()->contentAdvisories->pluck('slug'))->toContain('no-sexual-content');

    // Flipping the answer swaps the chip instead of stacking both.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'advisories' => ['sexual' => true, 'sexualDescription' => 'Brief nudity'],
    ])->assertOk();

    $slugs = $event->fresh()->contentAdvisories->pluck('slug');
    expect($slugs)->toContain('sexual-content');
    expect($slugs)->not->toContain('no-sexual-content');
});

test('update-event auto-adds the wheelchair chip and preserves other advisories', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'mobilityAdvisories' => [['name' => 'Extended standing']],
    ])->assertOk();

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'wheelchairReady' => true,
    ])->assertOk();

    $slugs = $event->fresh()->mobilityAdvisories->pluck('slug');
    expect($slugs)->toContain('wheelchair-accessible');
    expect($slugs)->toContain('extended-standing');
});

test('update-event requires sexualDescription when sexual content is true', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'advisories' => ['sexual' => true],
    ]);

    $response->assertOk()->assertSee('validation_failed')->assertSee('sexualDescription');
    expect($event->fresh()->advisories->sexual)->toBeNull();
});

// ── live-edit confirmation ─────────────────────────────────────────────

test('editing a published event returns a diff and applies nothing without confirmation', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'p', 'name' => 'Live Show']);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Renamed Live Show',
        'acknowledge_duplicate' => true,
    ]);

    $response->assertOk()
        ->assertSee('confirm_live_edit')
        ->assertSee('Live Show')
        ->assertSee('Renamed Live Show');
    expect($event->fresh()->name)->toBe('Live Show');
});

test('editing a published event applies with confirm_live_edit', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'p', 'name' => 'Live Show']);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Renamed Live Show',
        'acknowledge_duplicate' => true,
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->name)->toBe('Renamed Live Show');
    expect($event->fresh()->status)->toBe('p');
});

test('draft edits never require live-edit confirmation', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'Straight Through',
    ])->assertOk()->assertDontSee('confirm_live_edit');

    expect($event->fresh()->name)->toBe('Straight Through');
});

test('update-event advances the wizard step marker to the first unfinished step', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user); // status '0', attendance null

    // Steps 1-2 done (event type + name + tagline); Category (step 3) is the gap.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
        'name' => 'Marker Test',
        'tag_line' => 'A tagline worth reading.',
        'acknowledge_duplicate' => true,
    ])->assertOk()->assertSee('web_wizard_resumes_at')->assertSee('Category');
    expect($event->fresh()->status)->toBe('2');

    // Description (step 6) set while Category/Genres/Location are still missing:
    // the contiguous marker must NOT leap past the gap.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'description' => 'A complete-enough description of the odyssey.',
    ])->assertOk();
    expect($event->fresh()->status)->toBe('2');

    // Close steps 3-5; now 1-6 are contiguous and Dates (step 7) is the gap.
    $category = Category::factory()->create(['remote' => false]);
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'category_id' => $category->id,
        'genres' => [['name' => 'Immersive']],
        'location' => ['city' => 'Petaluma', 'latitude' => 38.24, 'longitude' => -122.63],
    ])->assertOk();
    expect($event->fresh()->status)->toBe('6');
});

test('update-event never turns a published status into a step marker', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'p', 'name' => 'Live Show']);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
        'tag_line' => 'Now with a tagline.',
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->status)->toBe('p');
});

test('update-event never overwrites a needs-revision status with a step marker', function () {
    $user = writeToolUser();
    // 'n' is not behind the r-lock or the live-edit confirmation, so it reaches
    // syncWizardStep directly — the guard must still leave the lifecycle status.
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => 'n', 'name' => 'Rejected Show']);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
        'tag_line' => 'Revised tagline.',
    ])->assertOk();

    expect($event->fresh()->status)->toBe('n');
});

test('update-event marks a fully complete draft at Mobility and resumes at Review', function () {
    $user = writeToolUser();
    $event = completeDraft($user); // every readiness item satisfied, status '0'

    // completeDraft never set the event type; one call closes step 1, and every
    // later step is already complete, so the marker reaches the last data step.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
    ])->assertOk()->assertSee('Review');

    expect($event->fresh()->status)->toBe('C');
});

test('update-event reports Remote (not Location) as the resume step for a remote event', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $category = Category::factory()->create(['remote' => true]);

    // Steps 1-4 done for a remote event; step 5 (Remote platforms) is the gap.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 2,
        'name' => 'Remote Odyssey',
        'tag_line' => 'Joined from anywhere.',
        'category_id' => $category->id,
        'genres' => [['name' => 'Immersive']],
        'acknowledge_duplicate' => true,
    ])->assertOk()->assertSee('Remote');

    expect($event->fresh()->status)->toBe('4');
});

test('update-event does not regress the wizard step marker', function () {
    $user = writeToolUser();
    // Artificially parked at a high marker with little underlying data (its
    // event type was never set, so the contiguous target is far lower).
    $event = draftFor(writeToolOrganizer($user), $user, ['status' => '9']);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tag_line' => 'A late tagline.',
    ])->assertOk();

    // The monotonic high-water mark must never drop below where it already was.
    expect($event->fresh()->status)->toBe('9');
});

test('update-event collapses multiple datetimes on the same day to one show', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => [
            scheduleDayAt(10, 12, 'America/Los_Angeles'), // noon Pacific
            // 7 PM Pacific is 02:00/03:00 the NEXT UTC day whether it's PDT or
            // PST, so this keeps testing the UTC-rollover case year-round.
            scheduleDayAt(10, 19, 'America/Los_Angeles'), // SAME local day
            scheduleDayAt(13, 12, 'America/Los_Angeles'), // a different local day
        ],
    ])->assertOk();

    // The two same-local-day datetimes collapse to one show; the third is its own.
    expect($event->fresh()->shows()->count())->toBe(2);
});

test('update-event applies a dateArray change even when showtype is omitted', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Los_Angeles';
    $original = [scheduleDay(1, $tz), scheduleDay(2, $tz), scheduleDay(3, $tz)];
    $replacement = [scheduleDay(8, $tz), scheduleDay(9, $tz)];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $original,
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(3);

    // Replace the dates WITHOUT re-sending showtype: must still apply (default
    // to the current showtype), not silently no-op leaving the old shows.
    // (confirm_schedule_replace because this drops the original three.)
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => $tz,
        'dateArray' => $replacement,
        'confirm_schedule_replace' => true,
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(2);
});

test('update-event confirms before deleting existing shows', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Los_Angeles';
    $original = [scheduleDay(1, $tz), scheduleDay(2, $tz), scheduleDay(3, $tz)];
    $replacement = [scheduleDay(8, $tz)];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $original,
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(3);

    // A replace that drops shows returns a confirmation instead of applying.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $replacement,
    ])->assertOk()->assertSee('confirm_schedule_replace')->assertSee('shows_to_remove');
    expect($event->fresh()->shows()->count())->toBe(3); // untouched until confirmed

    // With the confirmation it applies.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $replacement,
        'confirm_schedule_replace' => true,
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(1);
});

test('update-event does not confirm when only adding shows', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Los_Angeles';
    $original = [scheduleDay(1, $tz), scheduleDay(2, $tz)];
    // Same two dates plus one more, so nothing is dropped.
    $extended = [...$original, scheduleDay(3, $tz)];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $original,
    ])->assertOk();

    // Adding a date while keeping the existing two removes nothing — applies
    // straight through with no confirmation gate.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => $extended,
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(3);
});

test('update-event rejects a new show scheduled in the past', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    // 2020 is well before today — the web calendar could never pick it. Left
    // hardcoded on purpose: this date must stay in the past forever.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => ['2020-01-01 20:00:00', scheduleDay(1, 'America/Los_Angeles')],
    ])->assertOk()->assertSee('past_dates')->assertSee('2020-01-01');
    expect($event->fresh()->shows()->count())->toBe(0); // nothing saved
});

test('update-event preserves an existing past show when re-saving', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $event->update(['showtype' => 's']);
    $tz = 'America/Los_Angeles';
    $upcoming = scheduleDay(1, $tz);
    $event->shows()->create(['date' => '2020-01-01 12:00:00']); // a show that already happened
    $event->shows()->create(['date' => $upcoming]);

    // Re-sending the current dates (including the past one) must NOT be rejected —
    // the guard only blocks NEW past dates, not existing occurrences.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => ['2020-01-01 12:00:00', $upcoming],
    ])->assertOk();
    expect($event->fresh()->shows()->count())->toBe(2);
});

test('update-event preserves a past show a non-staff user tries to remove, and reports it', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $event->update(['showtype' => 's']);
    $tz = 'America/Los_Angeles';
    $past = '2020-01-01 12:00:00';
    $future = scheduleDay(5, $tz);
    $event->shows()->create(['date' => $past]);
    $event->shows()->create(['date' => $future]);

    // A regular user sends a schedule that drops the past show — Show::saveShows()
    // must keep it instead of deleting it, and the tool must say so rather than
    // implying the requested schedule was applied exactly as sent.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => [$future],
        'confirm_schedule_replace' => true,
    ])->assertOk()->assertSee('preserved_past_dates')->assertSee('2020-01-01');

    $event->refresh();
    expect($event->shows()->count())->toBe(2);
    expect($event->shows()->pluck('date')->map(fn ($d) => (string) $d)->all())->toContain($past);
});

test('update-event lets a moderator remove a past show with no preserved-dates notice', function () {
    $moderator = writeToolUser('m');
    $event = draftFor(writeToolOrganizer($moderator), $moderator);
    $event->update(['showtype' => 's']);
    $tz = 'America/Los_Angeles';
    $past = '2020-01-01 12:00:00';
    $future = scheduleDay(5, $tz);
    $event->shows()->create(['date' => $past]);
    $event->shows()->create(['date' => $future]);

    EiServer::actingAs($moderator)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => $tz,
        'dateArray' => [$future],
        'confirm_schedule_replace' => true,
    ])->assertOk()->assertDontSee('preserved_past_dates');

    $event->refresh();
    expect($event->shows()->count())->toBe(1);
    expect($event->shows()->first()->date)->not->toBe($past);
});

test('update-event lets an admin backfill a recent historical date', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);

    // Admins may backfill HISTORICAL shows (mirrors the web calendar's 10-year
    // admin lookback). A past date within that window saves rather than being
    // rejected as it would be for a regular user.
    $recentPast = now('America/Los_Angeles')->subMonths(2)->format('Y-m-d').' 19:00:00';
    $future = now('America/Los_Angeles')->addMonths(2)->format('Y-m-d').' 19:00:00';

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => [$recentPast, $future],
    ])->assertOk()->assertDontSee('past_dates');
    expect($event->fresh()->shows()->count())->toBe(2);
});

test('update-event still blocks an admin date more than a decade back', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);

    // Beyond the 10-year window is almost always a wrong year — rejected even
    // for an admin, with the offending day named.
    $farPastDay = now('America/Los_Angeles')->subYears(11)->format('Y-m-d');
    $future = now('America/Los_Angeles')->addMonths(2)->format('Y-m-d').' 19:00:00';

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => [$farPastDay.' 19:00:00', $future],
    ])->assertOk()->assertSee('past_dates')->assertSee($farPastDay);
    expect($event->fresh()->shows()->count())->toBe(0);
});

// ── ongoing recurrence: server-side expansion + batched saving ──────────

test('update-event expands ongoing_config into shows without an explicit dateArray', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';
    $days = [3, 4, 5, 6];
    $start = scheduleDay(8, $tz);   // all future, so a regular user passes the past-date guard
    $end = scheduleDay(40, $tz);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => $tz,
        'ongoing_config' => ['startDate' => $start, 'endDate' => $end, 'daysOfWeek' => $days],
    ])->assertOk();

    // The stored shows match the deterministic expansion exactly — no dateArray sent.
    $expected = RecurringDates::expand($days, $start, $end, $tz);
    expect($expected)->not->toBeEmpty();

    $stored = $event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->sort()->values()->all();
    expect($stored)->toBe($expected);
});

test('an explicit dateArray overrides ongoing_config expansion (exception handling)', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';

    // Two hand-picked dates PLUS a recipe that would expand to ~50 — the explicit
    // list must win so a caller can drop exception days.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => $tz,
        'dateArray' => [scheduleDay(10, $tz), scheduleDay(11, $tz)],
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(60, $tz), 'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6]],
    ])->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('an ongoing_config that expands to nothing returns empty_schedule and saves no shows', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    // start after end → the recurrence yields zero dates.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => 'UTC',
        'ongoing_config' => ['startDate' => scheduleDay(40), 'endDate' => scheduleDay(8), 'daysOfWeek' => [1, 2, 3]],
    ])->assertOk()->assertSee('empty_schedule');

    expect($event->fresh()->shows()->count())->toBe(0);
});

test('an admin can backfill a historical recurring run via ongoing_config', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'America/Toronto';
    $days = [4, 5, 6, 0, 1]; // Thu–Mon
    $start = scheduleDay(-60, $tz); // starts in the past …
    $end = scheduleDay(20, $tz);    // … runs into the future

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => $tz,
        'ongoing_config' => ['startDate' => $start, 'endDate' => $end, 'daysOfWeek' => $days],
    ])->assertOk()->assertDontSee('past_dates');

    $expected = RecurringDates::expand($days, $start, $end, $tz);
    expect(count($expected))->toBeGreaterThan(20);
    expect($event->fresh()->shows()->count())->toBe(count($expected));
});

test('saving a large recurring schedule does not scale queries with date count (no N+1)', function () {
    $admin = writeToolUser('a');
    $tz = 'America/Toronto';
    $all = [0, 1, 2, 3, 4, 5, 6];

    $small = draftFor(writeToolOrganizer($admin), $admin);
    $qSmall = queriesDuring(fn () => EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $small->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(21, $tz), 'daysOfWeek' => $all],
    ])->assertOk());

    $large = draftFor(writeToolOrganizer($admin), $admin);
    $qLarge = queriesDuring(fn () => EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $large->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(98, $tz), 'daysOfWeek' => $all],
    ])->assertOk());

    // ~14 shows vs ~90 shows. The old updateOrCreate-per-date path added roughly
    // 4 queries per extra show (~300 more here); batched, the count barely moves.
    expect($large->fresh()->shows()->count())->toBeGreaterThan(70);
    expect($qLarge - $qSmall)->toBeLessThanOrEqual(5);
});

test('re-saving an identical recurring schedule creates no new show rows', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';
    $payload = [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(40, $tz), 'daysOfWeek' => [3, 4, 5, 6]],
    ];

    EiServer::actingAs($user)->tool(UpdateEvent::class, $payload)->assertOk();
    $firstIds = $event->fresh()->shows()->pluck('id')->sort()->values()->all();

    EiServer::actingAs($user)->tool(UpdateEvent::class, $payload)->assertOk();
    $secondIds = $event->fresh()->shows()->pluck('id')->sort()->values()->all();

    // Batched save preserves surviving rows — identical re-save is a no-op.
    expect($secondIds)->toBe($firstIds);
});

test('a partial schedule change keeps surviving shows, drops removed ones, and adds new ones', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'America/Toronto';
    $a = scheduleDay(10, $tz);
    $b = scheduleDay(11, $tz);
    $c = scheduleDay(12, $tz);
    $d = scheduleDay(13, $tz);
    $norm = fn ($x) => Carbon::parse($x, 'UTC')->format('Y-m-d H:i:s');

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 's', 'timezone' => $tz, 'dateArray' => [$a, $b, $c],
    ])->assertOk();
    $keptId = $event->fresh()->shows()->where('date', $norm($b))->first()->id;

    // Drop $a, keep $b/$c, add $d. Dropping a show requires the replace confirmation.
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 's', 'timezone' => $tz,
        'dateArray' => [$b, $c, $d], 'confirm_schedule_replace' => true,
    ])->assertOk();

    $final = $event->fresh()->shows()->pluck('date')->map(fn ($x) => (string) $x)->sort()->values()->all();
    expect($final)->toBe(collect([$b, $c, $d])->map($norm)->sort()->values()->all());

    // The kept show ($b) retained its original row rather than being re-created.
    expect($event->fresh()->shows()->where('date', $norm($b))->first()->id)->toBe($keptId);
});

test('extending a ticketed schedule copies the ticket tiers onto the newly added shows', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'America/Toronto';

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 's', 'timezone' => $tz,
        'dateArray' => [scheduleDay(10, $tz), scheduleDay(11, $tz)],
    ])->assertOk();

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tickets' => [['name' => 'GA', 'ticket_price' => 25, 'currency' => '$', 'description' => '']],
    ])->assertOk();

    // Add a third date — the new show should inherit the GA tier via the batched copy.
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 's', 'timezone' => $tz,
        'dateArray' => [scheduleDay(10, $tz), scheduleDay(11, $tz), scheduleDay(12, $tz)],
    ])->assertOk();

    $shows = $event->fresh()->shows;
    expect($shows)->toHaveCount(3);
    $shows->each(fn ($s) => expect($s->tickets()->where('name', 'GA')->exists())->toBeTrue());
});

test('switching show type wipes every existing show and its tickets', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'America/Toronto';

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 's', 'timezone' => $tz,
        'dateArray' => [scheduleDay(10, $tz), scheduleDay(11, $tz)],
    ])->assertOk();
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tickets' => [['name' => 'GA', 'ticket_price' => 25, 'currency' => '$', 'description' => '']],
    ])->assertOk();

    $oldShowIds = $event->fresh()->shows()->pluck('id')->all();

    // Switch to always-available — this deletes the old shows and recreates one.
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'a', 'timezone' => $tz, 'confirm_schedule_replace' => true,
    ])->assertOk();

    expect($event->fresh()->shows()->count())->toBe(1);
    expect(\App\Models\Events\Ticket::where('ticket_type', \App\Models\Events\Show::class)->whereIn('ticket_id', $oldShowIds)->count())->toBe(0);
});

// ── regression guards: never silently wipe / never over-expand (review findings) ──

test('echoing showtype on an ongoing event without schedule data leaves the shows intact', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(40, $tz), 'daysOfWeek' => [3, 4, 5, 6]],
    ])->assertOk();
    $before = $event->fresh()->shows()->count();
    expect($before)->toBeGreaterThan(0);

    // A bare showtype echo (e.g. alongside a show_times change) must NOT wipe the
    // schedule via whereNotIn([]) — the show_times still applies, the shows stay.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'show_times' => 'Doors 7pm',
    ])->assertOk();

    expect($event->fresh()->shows()->count())->toBe($before);
    expect($event->fresh()->show_times)->toBe('Doors 7pm');
});

test('an incomplete ongoing_config errors with empty_schedule and does not wipe the schedule', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(40, $tz), 'daysOfWeek' => [3, 4, 5, 6]],
    ])->assertOk();
    $before = $event->fresh()->shows()->count();

    // Missing endDate + daysOfWeek — the recurrence can't be built. Must error,
    // NOT fall through to a wipe.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz)],
    ])->assertOk()->assertSee('empty_schedule');

    expect($event->fresh()->shows()->count())->toBe($before);
});

test('an over-long recurring span is rejected instead of expanded', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);

    // A far-future end year would expand to millions of rows without the cap.
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => 'UTC',
        'ongoing_config' => ['startDate' => scheduleDay(8, 'UTC'), 'endDate' => '9999-12-31 12:00:00', 'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6]],
    ])->assertOk()->assertSee('schedule_too_long');

    expect($event->fresh()->shows()->count())->toBe(0);
});

test('a multi-year run past the old 1000-show cap expands end to end', function () {
    // Regression: the cap was 1000, stricter than the web wizard (which caps
    // nothing), so a real listing — Faulty Towers London, Thursday through
    // Sunday over five years — was rejected via MCP but buildable by hand.
    // Admin, because the run starts in the past and only admins may backfill.
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'Europe/London';

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => [
            'startDate' => scheduleDay(-365 * 4, $tz),
            'endDate' => scheduleDay(365, $tz),
            'daysOfWeek' => [4, 5, 6, 0], // Thu, Fri, Sat, Sun
        ],
    ])->assertOk()->assertSee('Event updated.');

    // Five years of four-day weeks is ~1,040 shows — over the old cap, and every
    // one of them persisted (the bulk insert chunks, so none are dropped).
    $shows = $event->fresh()->shows()->withoutGlobalScopes()->count();
    expect($shows)->toBeGreaterThan(1000)
        ->and($shows)->toBe(count(RecurringDates::expand(
            [4, 5, 6, 0], scheduleDay(-365 * 4, $tz), scheduleDay(365, $tz), $tz
        )));
});

test('an empty dateArray alongside a valid ongoing_config still expands the recurrence', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';
    $days = [3, 4, 5, 6];
    $start = scheduleDay(8, $tz);
    $end = scheduleDay(40, $tz);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'dateArray' => [], // present but empty — must NOT be rejected when a recipe is given
        'ongoing_config' => ['startDate' => $start, 'endDate' => $end, 'daysOfWeek' => $days],
    ])->assertOk()->assertDontSee('empty_schedule');

    expect($event->fresh()->shows()->count())->toBe(count(RecurringDates::expand($days, $start, $end, $tz)));
});

test('saveShows refuses to wipe an existing schedule when handed an empty target set', function () {
    $user = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($user), $user, ['showtype' => 's']);
    $event->shows()->create(['date' => scheduleDay(10)]);
    $event->shows()->create(['date' => scheduleDay(11)]);

    // Directly exercise the shared save path (as the web wizard reaches it) with
    // an empty target — it must be a no-op, never a whereNotIn([]) wipe.
    $request = (object) ['showtype' => 's', 'dateArray' => [], 'timezone' => 'UTC'];
    \App\Models\Events\Show::saveShows($request, $event->fresh());

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('a bare showtype echo preserves the stored recurrence rule, start date and closing date', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $tz = 'America/Toronto';
    $days = [3, 4, 5, 6];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => scheduleDay(8, $tz), 'endDate' => scheduleDay(40, $tz), 'daysOfWeek' => $days],
    ])->assertOk();
    $before = $event->fresh();
    expect($before->showtype_config)->not->toBeNull();

    // A show_times-only edit that still echoes showtype must NOT recompute (and
    // thereby null) the saved recurrence metadata.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'show_times' => 'Doors 7pm',
    ])->assertOk();
    $after = $event->fresh();

    expect($after->show_times)->toBe('Doors 7pm')
        ->and($after->showtype_config)->toEqual($before->showtype_config)
        ->and((string) $after->start_date)->toBe((string) $before->start_date)
        ->and((string) $after->closingDate)->toBe((string) $before->closingDate);
});

test('a recurrence spanning multiple insert chunks saves every show', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin);
    $tz = 'UTC';
    $days = [0, 1, 2, 3, 4, 5, 6];
    $start = scheduleDay(8, $tz);
    $end = scheduleDay(608, $tz); // ~600 daily shows — crosses the 500-row INSERT_CHUNK boundary

    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => $tz,
        'ongoing_config' => ['startDate' => $start, 'endDate' => $end, 'daysOfWeek' => $days],
    ])->assertOk();

    $expected = RecurringDates::expand($days, $start, $end, $tz);
    expect(count($expected))->toBeGreaterThan(500); // proves we actually cross a chunk boundary

    $stored = $event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->sort()->values()->all();
    expect($stored)->toBe($expected);
});

test('an invalid timezone returns a validation error instead of a 500', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'timezone' => 'America/Los_Angles', // typo
        'ongoing_config' => ['startDate' => scheduleDay(8, 'UTC'), 'endDate' => scheduleDay(40, 'UTC'), 'daysOfWeek' => [3, 4, 5, 6]],
    ])->assertOk()->assertSee('validation_failed');

    expect($event->fresh()->shows()->count())->toBe(0);
});

test('switching a specific-date event to ongoing with no dates is rejected, keeping type and shows consistent', function () {
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin, ['showtype' => 's']);
    $event->shows()->create(['date' => scheduleDay(10)]);

    // Switch to ongoing but supply no dates and no recipe, even with the replace
    // confirmation. This must be rejected — never leave an 'o' event backed by
    // the old 's' shows (or with zero shows).
    EiServer::actingAs($admin)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug, 'showtype' => 'o', 'confirm_schedule_replace' => true,
    ])->assertOk()->assertSee('empty_schedule');

    $fresh = $event->fresh();
    expect($fresh->showtype)->toBe('s')            // type unchanged
        ->and($fresh->shows()->count())->toBe(1);  // the original show survived
});

test('the shared save path skips an invalid empty show-type switch instead of corrupting the event', function () {
    // Exercise UpdateEventAction directly (as the web POST path does) to prove
    // the backstop: an s->o switch with no dates must not flip showtype while the
    // old shows survive.
    $admin = writeToolUser('a');
    $event = draftFor(writeToolOrganizer($admin), $admin, ['showtype' => 's']);
    $event->shows()->create(['date' => scheduleDay(10)]);

    $request = new \Illuminate\Http\Request(['showtype' => 'o']);
    app(\App\Actions\Events\UpdateEventAction::class)->handle($event->fresh(), ['showtype' => 'o'], $request);

    $fresh = $event->fresh();
    expect($fresh->showtype)->toBe('s')
        ->and($fresh->shows()->count())->toBe(1);
});

test('update-event refuses to empty a schedule', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);
    $event->update(['showtype' => 's']);
    $event->shows()->create(['date' => scheduleDay(1, 'America/Los_Angeles')]);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => [],
    ])->assertOk()->assertSee('empty_schedule');
    expect($event->fresh()->shows()->count())->toBe(1); // untouched
});

// ── submit-event-for-review ────────────────────────────────────────────

function completeDraft(User $user): Event
{
    $organizer = writeToolOrganizer($user);
    $category = Category::factory()->create(['remote' => false]);
    $contactLevel = \App\Models\Events\ContactLevel::query()->first()
        ?? \App\Models\Events\ContactLevel::create(['name' => 'None', 'user_id' => $user->id]);
    $interactiveLevel = \App\Models\Events\InteractiveLevel::query()->first()
        ?? \App\Models\Events\InteractiveLevel::create(['name' => 'Passive', 'description' => 'Watch only', 'user_id' => $user->id]);
    $ageLimit = \App\Models\Events\AgeLimit::query()->first()
        ?? \App\Models\Events\AgeLimit::forceCreate(['name' => 'All ages', 'age' => 0]);

    $event = draftFor($organizer, $user, [
        'name' => 'Complete Event',
        'tag_line' => 'You will not forget it.',
        'description' => 'Fully filled in.',
        'category_id' => $category->id,
        'hasLocation' => true,
        'largeImagePath' => 'event-images/x/large.webp',
        'interactive_level_id' => $interactiveLevel->id,
        'age_limits_id' => $ageLimit->id,
        'ticketUrl' => 'https://example.com/tickets',
        'call_to_action' => 'Get Tickets',
    ]);
    $event->location->update(['latitude' => 40.0, 'longitude' => -74.0]);
    $event->contactLevels()->sync([$contactLevel->id]);
    $event->advisories()->update(['sexual' => false, 'wheelchairReady' => true, 'audience' => 'Observers']);
    $event->contentAdvisories()->sync([
        \App\Models\Events\ContentAdvisory::firstOrCreate(['slug' => 'no-sexual-content'], ['name' => 'No sexual content', 'user_id' => $user->id])->id,
        \App\Models\Events\ContentAdvisory::firstOrCreate(['slug' => 'loud-noises'], ['name' => 'Loud noises', 'user_id' => $user->id])->id,
    ]);
    $event->mobilityAdvisories()->sync([
        \App\Models\Events\MobilityAdvisory::firstOrCreate(['slug' => 'wheelchair-accessible'], ['name' => 'Wheelchair accessible', 'user_id' => $user->id])->id,
        \App\Models\Events\MobilityAdvisory::firstOrCreate(['slug' => 'extended-standing'], ['name' => 'Extended standing', 'user_id' => $user->id])->id,
    ]);
    $event->genres()->sync([
        \App\Models\Genre::firstOrCreate(['slug' => 'horror'], ['name' => 'Horror', 'user_id' => $user->id, 'admin' => false])->id,
    ]);
    $show = $event->shows()->create(['date' => scheduleDay(30)]);
    $show->tickets()->create(['name' => 'GA', 'ticket_price' => 20, 'currency' => '$', 'ticket_id' => $show->id, 'ticket_type' => get_class($show), 'description' => '']);

    return $event;
}

test('submit-event-for-review submits a complete draft and notifies admins', function () {
    User::factory()->create(['type' => 'a']); // an admin to notify
    $user = writeToolUser();
    $event = completeDraft($user);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertOk()->assertSee('submitted for review');
    expect($event->fresh()->status)->toBe('r');
    Mail::assertSent(\App\Mail\EventSubmittedNotification::class);
});

test('submit-event-for-review rejects an event whose only image is a gallery image', function () {
    $user = writeToolUser();
    $event = completeDraft($user);
    // Swap the primary-image marker for a gallery-only image (rank 1).
    $event->update(['largeImagePath' => null]);
    $event->images()->create([
        'large_image_path' => "event-images/{$event->slug}/gallery.webp",
        'thumb_image_path' => "event-images/{$event->slug}/gallery-thumb.webp",
        'rank' => 1,
    ]);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertHasErrors();
    expect($event->fresh()->status)->toBe('0');
});

test('submit-event-for-review blocks incomplete drafts with a missing list', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['description' => null]);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertHasErrors();
    expect($event->fresh()->status)->toBe('0');
});

test('submit enforces the wizard-parity requirements one by one', function () {
    $user = writeToolUser();

    // Each override removes exactly one wizard requirement from a complete draft.
    $cases = [
        'tag_line' => fn (Event $e) => $e->update(['tag_line' => null]),
        'ticket_url' => fn (Event $e) => $e->update(['ticketUrl' => null]),
        'ticket_button_text' => fn (Event $e) => $e->update(['call_to_action' => '']),
        'genres' => fn (Event $e) => $e->genres()->sync([]),
        'age_limit' => fn (Event $e) => $e->update(['age_limits_id' => null]),
        'audience_role' => fn (Event $e) => $e->advisories()->update(['audience' => null]),
        'sexual_content_answered' => fn (Event $e) => $e->advisories()->update(['sexual' => null]),
        'wheelchair_answered' => fn (Event $e) => $e->advisories()->update(['wheelchairReady' => null]),
        // Only the auto chip left => "at least one beyond the chip" fails.
        'content_advisories' => fn (Event $e) => $e->contentAdvisories()->sync([
            \App\Models\Events\ContentAdvisory::where('slug', 'no-sexual-content')->first()->id,
        ]),
        'mobility_advisories' => fn (Event $e) => $e->mobilityAdvisories()->sync([
            \App\Models\Events\MobilityAdvisory::where('slug', 'wheelchair-accessible')->first()->id,
        ]),
    ];

    foreach ($cases as $expectedMissing => $break) {
        $event = completeDraft($user);
        $break($event);

        $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
            'event_slug' => $event->slug,
        ]);

        $response->assertHasErrors()->assertSee($expectedMissing);
        expect($event->fresh()->status)->toBe('0');
    }
});

test('a secret location requires an explanation before submit', function () {
    $user = writeToolUser();
    $event = completeDraft($user);
    $event->location->update(['hiddenLocationToggle' => true, 'hiddenLocation' => null]);

    EiServer::actingAs($user)->tool(SubmitEventForReview::class, ['event_slug' => $event->slug])
        ->assertHasErrors()
        ->assertSee('secret_location_explained');

    $event->location->update(['hiddenLocation' => 'Address emailed the day before.']);

    EiServer::actingAs($user)->tool(SubmitEventForReview::class, ['event_slug' => $event->slug])
        ->assertOk();
});

test('submit-event-for-review blocks already-submitted events', function () {
    $user = writeToolUser();
    $event = completeDraft($user);
    $event->update(['status' => 'r']);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertHasErrors();
});

test('a rejected event can be resubmitted', function () {
    $user = writeToolUser();
    $event = completeDraft($user);
    $event->update(['status' => 'n']);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertOk();
    expect($event->fresh()->status)->toBe('r');
});

test('update-event accepts a ticket tier with no description', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    // description is the only optional ticket key. Reading it unconditionally in
    // Ticket::handleTickets turned an omitted description into an "Undefined
    // array key" error — the web wizard always sends '' so only API/MCP clients
    // could hit it.
    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [scheduleDay(30, 'America/New_York')],
        'tickets' => [['name' => 'General Admission', 'ticket_price' => 31.00, 'currency' => '$']],
    ]);

    $response->assertOk();

    $event->refresh();
    $ticket = $event->shows->first()->tickets->first();
    expect($ticket->name)->toBe('General Admission');
    expect((float) $ticket->ticket_price)->toBe(31.00);
    expect($ticket->description)->toBe('');
    expect($event->price_range)->toContain('31');
});

test('update-event reports a field error for a tier missing price or currency', function () {
    $user = writeToolUser();

    foreach (['ticket_price', 'currency'] as $omitted) {
        $event = draftFor(writeToolOrganizer($user), $user);
        $tier = array_diff_key(
            ['name' => 'General', 'ticket_price' => 25.00, 'currency' => '$'],
            [$omitted => null]
        );

        EiServer::actingAs($user)->tool(UpdateEvent::class, [
            'event_slug' => $event->slug,
            'timezone' => 'America/New_York',
            'showtype' => 's',
            'dateArray' => [scheduleDay(30, 'America/New_York')],
            'tickets' => [$tier],
        ])->assertOk()->assertSee(['validation_failed', "tickets.0.{$omitted}"]);

        expect($event->fresh()->shows->first()?->tickets ?? collect())->toHaveCount(0);
    }
});

test('update-event saves a secret-location explanation through the tool', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'attendance_type_id' => 1,
        'location' => [
            'venue' => 'Secret Warehouse',
            'city' => 'Brooklyn',
            'latitude' => 40.7,
            'longitude' => -73.9,
            'hiddenLocationToggle' => true,
            'hiddenLocation' => 'Address emailed after booking.',
        ],
    ])->assertOk();

    $location = $event->fresh()->location;
    expect((bool) $location->hiddenLocationToggle)->toBeTrue();
    expect($location->hiddenLocation)->toBe('Address emailed after booking.');
});

test('a moderator can draft an event under an organizer they do not belong to', function () {
    $stranger = writeToolOrganizer(writeToolUser());
    $moderator = writeToolUser('m');

    $response = EiServer::actingAs($moderator)->tool(CreateEventDraft::class, [
        'organizer_id' => $stranger->id,
        'name' => 'Public Broadcast Immersive',
    ]);

    $response->assertOk();
    expect(Event::where('organizer_id', $stranger->id)->exists())->toBeTrue();
});

test('the duplicate-organizer warning points moderators at the existing id', function () {
    $owner = writeToolUser();
    $existing = writeToolOrganizer($owner);

    // `claimable: false` describes the website claim flow only — a moderator can
    // just use the id, and the message has to say so or clients give up here.
    EiServer::actingAs(writeToolUser('m'))->tool(CreateOrganizer::class, [
        'name' => $existing->name,
        'description' => str_repeat('A public media organization. ', 5),
    ])->assertOk()->assertSee([
        'duplicate_name',
        'passing its id to create-event-draft',
    ]);

    EiServer::actingAs(writeToolUser())->tool(CreateOrganizer::class, [
        'name' => $existing->name,
        'description' => str_repeat('A public media organization. ', 5),
    ])->assertOk()->assertSee('claim it on its page on the website');
});

// ── schedule edits on LIVE events (reported from the field) ────────────

/** A published event with a real schedule already on it. */
function liveEvent(User $user, string $showtype, array $dates, array $overrides = []): Event
{
    $event = draftFor(writeToolOrganizer($user), $user, array_merge([
        'status' => 'p', 'showtype' => $showtype, 'timezone' => 'America/Toronto',
    ], $overrides));

    foreach ($dates as $date) {
        $event->shows()->create(['date' => $date]);
    }

    return $event->fresh();
}

test('ongoing_config sent to an always-available event is refused, not silently dropped', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $event = liveEvent($user, 'a', [scheduleDay(20, $tz)], ['closingDate' => scheduleDay(20, $tz)]);
    $closingBefore = (string) $event->closingDate;

    // Show::updateEvent only reads ongoing_config for showtype 'o'. This used to
    // answer "Event updated." with ongoing_config in updated_fields while the
    // schedule was untouched — AND reset closingDate to a default six months out.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'ongoing_config' => ['startDate' => scheduleDay(1, $tz), 'endDate' => scheduleDay(120, $tz), 'daysOfWeek' => [5, 6]],
        'confirm_live_edit' => true,
        'confirm_schedule_replace' => true,
    ])->assertOk()
        ->assertSee(['showtype_mismatch', 'always_config'])
        ->assertDontSee('Event updated.');

    $after = $event->fresh();
    expect((string) $after->closingDate)->toBe($closingBefore)
        ->and($after->shows()->count())->toBe(1);
});

test('always_config sent to a specific-dates event is refused with the field that applies', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $event = liveEvent($user, 's', [scheduleDay(5, $tz), scheduleDay(9, $tz)]);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'always_config' => ['endDate' => scheduleDay(120, $tz)],
        'confirm_live_edit' => true,
    ])->assertOk()->assertSee(['showtype_mismatch', 'dateArray']);

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('always_config extends an always-available run — the supported path', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $event = liveEvent($user, 'a', [scheduleDay(20, $tz)], ['closingDate' => scheduleDay(20, $tz)]);
    $newEnd = scheduleDay(200, $tz);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'always_config' => ['endDate' => $newEnd],
        'confirm_live_edit' => true,
    ])->assertOk()->assertSee('Event updated.')->assertDontSee('confirm_schedule_replace');

    $after = $event->fresh();
    expect(substr((string) $after->closingDate, 0, 10))->toBe(Carbon::parse($newEnd, 'UTC')->format('Y-m-d'))
        ->and($after->shows()->count())->toBe(1);
});

test('a legacy limited-type event refuses a schedule edit instead of collapsing it', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $event = liveEvent($user, 'l', [scheduleDay(-10, $tz), scheduleDay(5, $tz), scheduleDay(12, $tz)]);

    // 'l' would flow into Show::saveShows, whose 'l' branch discards the supplied
    // dates and replaces the whole schedule with one sentinel show six months out
    // — and showsThatWouldBeRemoved reported 0, so nothing asked first.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'dateArray' => [scheduleDay(5, $tz), scheduleDay(12, $tz), scheduleDay(19, $tz)],
        'confirm_live_edit' => true,
        'confirm_schedule_replace' => true,
    ])->assertOk()->assertSee(['showtype_required', 'retired']);

    expect($event->fresh()->shows()->count())->toBe(3);
});

test('a legacy limited-type event converts when the caller names a showtype', function () {
    $user = writeToolUser('a');
    $tz = 'America/Toronto';
    $event = liveEvent($user, 'l', [scheduleDay(-10, $tz), scheduleDay(5, $tz)]);
    $dates = [scheduleDay(-10, $tz), scheduleDay(5, $tz), scheduleDay(19, $tz)];

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'dateArray' => $dates,
        'confirm_live_edit' => true,
        'confirm_schedule_replace' => true,
    ])->assertOk()->assertSee('Event updated.');

    $after = $event->fresh();
    expect($after->showtype)->toBe('s')
        ->and($after->shows()->pluck('date')->map(fn ($d) => (string) $d)->sort()->values()->all())
        ->toBe(collect($dates)->sort()->values()->all());
});

test('a schedule edit with no showtype anywhere is refused rather than dropped', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['showtype' => null]);

    // The old guard skipped the showtype default when the event had none, so
    // saveShows never ran and the tool still reported success.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'dateArray' => [scheduleDay(10)],
    ])->assertOk()->assertSee('showtype_required')->assertDontSee('Event updated.');

    expect($event->fresh()->shows()->count())->toBe(0);
});

test('collapsing a multi-show always-available event still asks before deleting', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    // Legacy shape: an 'a' event that somehow carries a full schedule. Saving it
    // keeps one sentinel and hard-deletes the rest, which used to count as 0.
    $event = liveEvent($user, 'a', [scheduleDay(5, $tz), scheduleDay(9, $tz), scheduleDay(14, $tz)]);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'always_config' => ['endDate' => scheduleDay(120, $tz)],
        'confirm_live_edit' => true,
    ])->assertOk()->assertSee(['confirm_schedule_replace', '"shows_to_remove":2']);

    expect($event->fresh()->shows()->count())->toBe(3);
});

test('extending a published ongoing run keeps its showtimes text and past shows', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $days = [5, 6];
    $start = scheduleDay(-30, $tz);
    $event = liveEvent($user, 'o', RecurringDates::expand($days, $start, scheduleDay(10, $tz), $tz), [
        'show_times' => 'Fridays 8pm, Saturdays 6pm & 9pm',
    ]);
    $before = $event->shows()->count();

    // A partial update sends only what is changing. Show::updateEvent used to read
    // show_times off the request unconditionally, so an absent key blanked it.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'ongoing_config' => ['startDate' => $start, 'endDate' => scheduleDay(90, $tz), 'daysOfWeek' => $days],
        'confirm_live_edit' => true,
    ])->assertOk()->assertSee('Event updated.');

    $after = $event->fresh();
    expect($after->show_times)->toBe('Fridays 8pm, Saturdays 6pm & 9pm')
        ->and($after->shows()->count())->toBeGreaterThan($before)
        ->and($after->shows()->where('date', '<', now()->format('Y-m-d H:i:s'))->count())->toBeGreaterThan(0);
});

test('a partial edit does not publish an embargoed event early', function () {
    $user = writeToolUser();
    $embargo = now()->addDays(20)->format('Y-m-d H:i:s');
    $event = liveEvent($user, 's', [scheduleDay(30)], ['status' => 'e', 'embargo_date' => $embargo]);

    // Absent embargo_date used to read as null, which the embargo branch took as
    // "the user removed the embargo" and published the event immediately.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'dateArray' => [scheduleDay(30), scheduleDay(40)],
        'confirm_live_edit' => true,
    ])->assertOk()->assertSee('Event updated.');

    $after = $event->fresh();
    expect($after->status)->toBe('e')
        ->and((string) $after->embargo_date)->toBe($embargo)
        ->and($after->shows()->count())->toBe(2);
});

test('an explicit embargo_date still lifts and applies an embargo', function () {
    $user = writeToolUser();
    $event = liveEvent($user, 's', [scheduleDay(30)], [
        'status' => 'e', 'embargo_date' => now()->addDays(20)->format('Y-m-d H:i:s'),
    ]);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'embargo_date' => null,
        'dateArray' => [scheduleDay(30), scheduleDay(40)],
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->status)->toBe('p');
});

test('an always-available switch leaves an embargo alone unless it is cleared explicitly', function () {
    $user = writeToolUser('a');
    $tz = 'America/Toronto';
    $embargo = now()->addDays(20)->format('Y-m-d H:i:s');
    $event = liveEvent($user, 's', [scheduleDay(30, $tz)], ['status' => 'e', 'embargo_date' => $embargo]);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'a',
        'always_config' => ['endDate' => scheduleDay(200, $tz)],
        'confirm_live_edit' => true,
        'confirm_schedule_replace' => true,
    ])->assertOk()->assertSee('Event updated.');

    $after = $event->fresh();
    expect($after->status)->toBe('e')->and((string) $after->embargo_date)->toBe($embargo);

    // Lifting it is an explicit act, not a side effect of the schedule change.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'embargo_date' => null,
        'showtype' => 'a',
        'always_config' => ['endDate' => scheduleDay(200, $tz)],
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->status)->toBe('p');
});

test('switching show type refreshes the closing date and clears the old recurrence rule', function () {
    $user = writeToolUser('a');
    $tz = 'America/Toronto';
    $event = draftFor(writeToolOrganizer($user), $user, [
        'showtype' => 'o', 'timezone' => $tz,
        'closingDate' => scheduleDay(20, $tz),
        'start_date' => scheduleDay(-30, $tz),
        'showtype_config' => ['type' => 'ongoing', 'days_of_week' => [5, 6], 'start_date' => scheduleDay(-30, $tz)],
    ]);
    $event->shows()->create(['date' => scheduleDay(5, $tz)]);
    $event->shows()->create(['date' => scheduleDay(12, $tz)]);

    // Switch to always-available WITHOUT always_config — supported: the schedule
    // defaults to six months out. Show::saveShows writes the new showtype onto
    // the event before Show::updateEvent reads it, so the "did the type change?"
    // check always said no and the metadata block was skipped: the event became
    // always-available while keeping the ongoing run's closingDate (so it still
    // expired) and its ongoing recurrence rule.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'a',
        'confirm_schedule_replace' => true,
    ])->assertOk();

    $after = $event->fresh();
    $sentinel = (string) $after->shows()->first()->date;

    expect($after->showtype)->toBe('a')
        ->and($after->shows()->count())->toBe(1)
        // closingDate now tracks the new sentinel show rather than the old run.
        ->and(substr((string) $after->closingDate, 0, 10))->toBe(substr($sentinel, 0, 10))
        ->and($after->showtype_config)->toBeNull()
        ->and($after->start_date)->toBeNull();
});

// ── ticket currency ────────────────────────────────────────────────────

/** Give an event a schedule so tickets have shows to attach to. */
function eventReadyForTickets(User $user): Event
{
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [scheduleDay(30, 'America/New_York')],
    ])->assertOk();

    return $event->fresh();
}

test('an ISO currency code is normalized to the symbol the site renders', function () {
    $user = writeToolUser();
    $event = eventReadyForTickets($user);

    // "USD" is 3 chars, so the old max:3 rule accepted it — and the symbol is
    // printed verbatim beside the price, so the listing read "USD17.00".
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tickets' => [['name' => 'General', 'ticket_price' => 17.00, 'currency' => 'USD']],
    ])->assertOk()->assertSee('Event updated.');

    expect($event->fresh()->shows->first()->tickets->first()->currency)->toBe('$');
});

test('the other unambiguous currency codes normalize too', function () {
    $user = writeToolUser();

    foreach (['EUR' => '€', 'gbp' => '£', 'JPY' => '¥', 'CAD' => 'C$', 'MXN' => 'MX$', ' usd ' => '$'] as $sent => $stored) {
        $event = eventReadyForTickets($user);

        EiServer::actingAs($user)->tool(UpdateEvent::class, [
            'event_slug' => $event->slug,
            'tickets' => [['name' => 'General', 'ticket_price' => 20, 'currency' => $sent]],
        ])->assertOk();

        expect($event->fresh()->shows->first()->tickets->first()->currency)->toBe($stored);
    }
});

test('an unrecognised currency is refused with the list of valid symbols', function () {
    $user = writeToolUser();
    $event = eventReadyForTickets($user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'tickets' => [['name' => 'General', 'ticket_price' => 17.00, 'currency' => 'BTC']],
    ])->assertOk()->assertSee([
        'validation_failed',
        'tickets.0.currency',
        // The message has to name the valid options, or the client just guesses again.
        'not the ISO code',
    ]);

    expect($event->fresh()->shows->first()->tickets)->toHaveCount(0);
});

test('a symbol the picker offers is stored untouched', function () {
    $user = writeToolUser();

    foreach (EventUpdateRules::CURRENCIES as $symbol) {
        $event = eventReadyForTickets($user);

        EiServer::actingAs($user)->tool(UpdateEvent::class, [
            'event_slug' => $event->slug,
            'tickets' => [['name' => 'General', 'ticket_price' => 20, 'currency' => $symbol]],
        ])->assertOk();

        expect($event->fresh()->shows->first()->tickets->first()->currency)->toBe($symbol);
    }
});

// ── fields this tool refuses to set ────────────────────────────────────

test('a closingDate-only edit names the field and points at the schedule', function () {
    $user = writeToolUser();
    $tz = 'America/Toronto';
    $event = liveEvent($user, 'o', [scheduleDay(5, $tz), scheduleDay(12, $tz)]);

    // closingDate is stripped before validation, which left nothing to validate
    // and produced "No updatable fields were provided" — reading, to the caller,
    // as the platform silently dropping the edit.
    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'closingDate' => scheduleDay(400, $tz),
        'confirm_live_edit' => true,
    ])->assertOk()
        ->assertSee(['fields_not_editable_here', 'closingDate', 'ongoing_config'])
        ->assertDontSee('No updatable fields were provided');

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('a status-only edit points at the submit tool', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'status' => 'p',
    ])->assertOk()->assertSee(['fields_not_editable_here', 'submit-event-for-review']);
});

test('a stripped field alongside a real one applies the edit and reports the drop', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'description' => 'A description long enough to be worth saving.',
        'closingDate' => scheduleDay(400),
    ])->assertOk()
        ->assertSee(['Event updated.', 'ignored_fields', 'closingDate']);

    expect($event->fresh()->description)->toBe('A description long enough to be worth saving.');
});

test('a normal edit reports no ignored fields', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'description' => 'Nothing here should be dropped.',
    ])->assertOk()->assertDontSee('ignored_fields');
});
