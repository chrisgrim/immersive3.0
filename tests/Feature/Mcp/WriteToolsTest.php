<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
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

test('create-organizer rejects invalid input', function () {
    $response = EiServer::actingAs(writeToolUser())->tool(CreateOrganizer::class, [
        'name' => '!!!',
        'description' => 'x',
    ]);

    $response->assertHasErrors();
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

test('create-event-draft enforces the 5 unpublished cap for non-admins', function () {
    $user = writeToolUser();
    $organizer = writeToolOrganizer($user);
    Event::factory()->count(5)->create(['organizer_id' => $organizer->id, 'status' => '0']);

    $response = EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
    ]);

    $response->assertHasErrors();
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

test('update-event saves shows and tickets together in the right order', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user);

    $response = EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => ['2026-09-04 23:00:00', '2026-09-05 23:00:00'],
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

// ── submit-event-for-review ────────────────────────────────────────────

function completeDraft(User $user): Event
{
    $organizer = writeToolOrganizer($user);
    $category = Category::factory()->create(['remote' => false]);
    $contactLevel = \App\Models\Events\ContactLevel::query()->first()
        ?? \App\Models\Events\ContactLevel::create(['name' => 'None', 'user_id' => $user->id]);
    $interactiveLevel = \App\Models\Events\InteractiveLevel::query()->first()
        ?? \App\Models\Events\InteractiveLevel::create(['name' => 'Passive', 'description' => 'Watch only', 'user_id' => $user->id]);

    $event = draftFor($organizer, $user, [
        'name' => 'Complete Event',
        'description' => 'Fully filled in.',
        'category_id' => $category->id,
        'hasLocation' => true,
        'largeImagePath' => 'event-images/x/large.webp',
        'interactive_level_id' => $interactiveLevel->id,
    ]);
    $event->location->update(['latitude' => 40.0, 'longitude' => -74.0]);
    $event->contactLevels()->sync([$contactLevel->id]);
    $show = $event->shows()->create(['date' => '2026-10-01 23:00:00']);
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

test('submit-event-for-review blocks incomplete drafts with a missing list', function () {
    $user = writeToolUser();
    $event = draftFor(writeToolOrganizer($user), $user, ['description' => null]);

    $response = EiServer::actingAs($user)->tool(SubmitEventForReview::class, [
        'event_slug' => $event->slug,
    ]);

    $response->assertHasErrors();
    expect($event->fresh()->status)->toBe('0');
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
