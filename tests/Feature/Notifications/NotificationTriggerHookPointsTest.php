<?php

use App\Actions\Events\UpdateEventAction;
use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Mail::fake(); // AdminEventController::approve also sends a Comments mail — not under test here
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ---------------------------------------------------------------------------
// Hook 1: UpdateEventAction — new dates added to a published event
// ---------------------------------------------------------------------------

test('adding a new show date to a published event notifies favoriters', function () {
    $event = Event::factory()->published()->create(['showtype' => 's']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(5)]);
    $favoriter = User::factory()->create();
    $this->actingAs($favoriter);
    $event->favorite();

    $newDate = now()->addDays(20)->format('Y-m-d');
    (new UpdateEventAction)->handle($event, [
        'showtype' => 's',
        'dateArray' => [$newDate],
    ], Request::create('/', 'POST', ['showtype' => 's', 'dateArray' => [$newDate]]));

    Notification::assertSentTo($favoriter, SavedEventNewDatesNotification::class);
});

test('re-saving the exact same dates does not notify anyone', function () {
    $event = Event::factory()->published()->create(['showtype' => 's']);
    $date = now()->addDays(5);
    Show::factory()->create(['event_id' => $event->id, 'date' => $date]);
    $favoriter = User::factory()->create();
    $this->actingAs($favoriter);
    $event->favorite();

    // Show::saveShows/updateEvent's exact contract for an unchanged-date
    // "echo" isn't the focus here — dateArray omitted entirely (no showtype
    // dates touched at all) is the simplest true no-op through this action.
    (new UpdateEventAction)->handle($event, [
        'name' => $event->name,
    ], Request::create('/', 'POST'));

    Notification::assertNothingSent();
});

test('adding new show dates locks the event row, so a concurrent save cannot read the same stale before-snapshot', function () {
    // Regression test for a race the review caught: the before/after date
    // diff in notifyIfNewDatesAdded used two unguarded, unlocked SELECTs, so
    // two near-simultaneous saves for the same event could both snapshot the
    // same "before" state and both decide the same new dates were just
    // added — double-notifying every favoriter. The fix wraps the diff+save
    // in a transaction that takes a row lock on the event first, serializing
    // concurrent editors the same way newEventFromFollowedOrganizer's atomic
    // claim does for its own trigger. Asserting the actual SQL is the only
    // way to verify the lock exists without standing up real concurrency.
    $event = Event::factory()->published()->create(['showtype' => 's']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $newDate = now()->addDays(20)->format('Y-m-d');
    (new UpdateEventAction)->handle($event, [
        'showtype' => 's',
        'dateArray' => [$newDate],
    ], Request::create('/', 'POST', ['showtype' => 's', 'dateArray' => [$newDate]]));

    expect(collect($queries)->contains(fn ($sql) => str_contains(strtolower($sql), 'for update')))->toBeTrue();
});

test('a draft events new dates never notify anyone (favorites cannot exist on an unpublished event)', function () {
    $event = Event::factory()->create(['showtype' => 's', 'status' => 'd']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(5)]);

    $newDate = now()->addDays(20)->format('Y-m-d');
    (new UpdateEventAction)->handle($event, [
        'showtype' => 's',
        'dateArray' => [$newDate],
    ], Request::create('/', 'POST', ['dateArray' => [$newDate]]));

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Hook 2: PublishEventsCommand — the embargo cron
// ---------------------------------------------------------------------------

test('the embargo cron notifies followers of the organizer when it publishes an event', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'e',
        'embargo_date' => now()->subMinute(),
    ]);

    $this->artisan('ei:publish-embargoed')->assertExitCode(0);

    Notification::assertSentTo($follower, FollowedOrganizerNewEventNotification::class);
});

test('the embargo cron does not notify anyone for an event whose embargo has not passed', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'e',
        'embargo_date' => now()->addWeek(),
    ]);

    $this->artisan('ei:publish-embargoed')->assertExitCode(0);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Hook 3: AdminEventController::approve()
// ---------------------------------------------------------------------------

test('admin approval that goes straight to published notifies followers', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    $event = Event::factory()->inReview()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'embargo_date' => null,
    ]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'p');

    Notification::assertSentTo($follower, FollowedOrganizerNewEventNotification::class);
});

test('admin approval that results in embargoed status does not notify followers yet', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    $event = Event::factory()->inReview()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'embargo_date' => now()->addWeek(),
    ]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'e');

    Notification::assertNotSentTo($follower, FollowedOrganizerNewEventNotification::class);
});

// ---------------------------------------------------------------------------
// Hook 3b (same trigger, different entry point): embargo lifted via a direct edit
// ---------------------------------------------------------------------------

test('lifting an embargo via a direct edit notifies followers', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'e',
        'embargo_date' => now()->addWeek(),
    ]);

    (new UpdateEventAction)->handle($event, [
        'status' => 'p',
    ], Request::create('/', 'POST'));

    Notification::assertSentTo($follower, FollowedOrganizerNewEventNotification::class);
});

test('an edit that keeps an event embargoed does not notify followers', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $follower = User::factory()->create();
    $this->actingAs($follower);
    $organizer->follow();

    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'e',
        'embargo_date' => now()->addWeek(),
    ]);

    (new UpdateEventAction)->handle($event, [
        'name' => 'A tweaked name',
    ], Request::create('/', 'POST'));

    Notification::assertNothingSent();
});
