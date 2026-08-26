<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;
use App\Services\EventNotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

// ---------------------------------------------------------------------------
// newDatesForSavedEvent()
// ---------------------------------------------------------------------------

test('newDatesForSavedEvent notifies every user who favorited the event', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $notFavorited = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($userA);
    $event->favorite();
    $this->actingAs($userB);
    $event->favorite();

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertSentTo($userA, SavedEventNewDatesNotification::class);
    Notification::assertSentTo($userB, SavedEventNewDatesNotification::class);
    Notification::assertNotSentTo($notFavorited, SavedEventNewDatesNotification::class);
});

test('newDatesForSavedEvent sends nothing when nobody favorited the event', function () {
    $event = Event::factory()->published()->create();

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertNothingSent();
});

test('newDatesForSavedEvent notifies each favoriter only once', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $this->actingAs($user);
    $event->favorite();

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertSentToTimes($user, SavedEventNewDatesNotification::class, 1);
});

test('newDatesForSavedEvent only mails favoriters whose per-item override is on', function () {
    $optedIn = User::factory()->create();
    $optedOut = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($optedIn);
    $event->favorite();
    $event->favorites()->where('user_id', $optedIn->id)->update(['notify_new_dates' => true]);

    $this->actingAs($optedOut);
    $event->favorite();
    $event->favorites()->where('user_id', $optedOut->id)->update(['notify_new_dates' => false]);

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    // Both still land in the in-app feed (database channel) regardless —
    // only the mail channel is gated by the per-item override.
    Notification::assertSentTo($optedIn, SavedEventNewDatesNotification::class, fn ($n, $channels) => in_array('mail', $channels));
    Notification::assertSentTo($optedOut, SavedEventNewDatesNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});

test('newDatesForSavedEvent does NOT mail when the favorite has no override', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite(); // notify_new_dates left null — hearting an event is not a request to be emailed about it

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    // Still lands in the in-app feed; just no email.
    Notification::assertSentTo($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});

test('newDatesForSavedEvent only mails once within the cooldown window, even across several separate dispatches', function () {
    // An organizer adding dates across several separate edits in one day
    // (each edit calling this method independently, the way
    // UpdateEventAction does per save) should collapse into one email, not
    // one per edit — the in-app feed still records every occurrence.
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $this->actingAs($user);
    $event->favorite();
    // Explicit opt-in: mail is off by default now, so without this the
    // cooldown under test would never be reached at all.
    $event->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => true]);

    // Each dispatch is moved a second apart deliberately. Tests run on
    // MySQL, whose UPDATE reports rows CHANGED rather than rows matched, so
    // three same-second calls all write an identical now() and the 2nd/3rd
    // report 0 affected regardless of the cooldown clause. That made
    // $mailAllowed false for the wrong reason: deleting the cooldown
    // entirely still left this test green (verified by mutating it away).
    // Spacing the calls means only the real cooldown can hold mail to one.
    $dispatcher = new EventNotificationDispatcher;
    $dispatcher->newDatesForSavedEvent($event);
    $this->travel(2)->seconds();
    $dispatcher->newDatesForSavedEvent($event);
    $this->travel(2)->seconds();
    $dispatcher->newDatesForSavedEvent($event);
    $this->travelBack();

    Notification::assertSentTimes(SavedEventNewDatesNotification::class, 3); // database channel every time

    $mailed = Notification::sent($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => in_array('mail', $channels));
    expect($mailed)->toHaveCount(1);
});

test('newDatesForSavedEvent mails again once the cooldown window has passed', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['notified_new_dates_at' => now()->subHours(25)]);
    $this->actingAs($user);
    $event->favorite();
    $event->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => true]); // opt in; see the cooldown test above

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertSentTo($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => in_array('mail', $channels));
    expect($event->fresh()->notified_new_dates_at)->toBeGreaterThan(now()->subMinute());
});

// ---------------------------------------------------------------------------
// newEventFromFollowedOrganizer()
// ---------------------------------------------------------------------------

test('newEventFromFollowedOrganizer notifies every follower of the events organizer', function () {
    $followerA = User::factory()->create();
    $followerB = User::factory()->create();
    $notFollowing = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($followerA);
    $organizer->follow();
    $this->actingAs($followerB);
    $organizer->follow();

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertSentTo($followerA, FollowedOrganizerNewEventNotification::class);
    Notification::assertSentTo($followerB, FollowedOrganizerNewEventNotification::class);
    Notification::assertNotSentTo($notFollowing, FollowedOrganizerNewEventNotification::class);
});

test('newEventFromFollowedOrganizer sends nothing when the organizer has no followers', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertNothingSent();
});

test('newEventFromFollowedOrganizer sends nothing when the event has no organizer', function () {
    $event = Event::factory()->published()->create(['organizer_id' => null]);

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertNothingSent();
});

test('newEventFromFollowedOrganizer only notifies once even when called twice for the same event', function () {
    // Regression test for the race Codex caught: the followed-organizer
    // trigger has 3 separate hook points (embargo cron, admin approval,
    // embargo-lift-on-edit) that could independently decide "this just
    // published" for the same event — e.g. overlapping cron runs, or a cron
    // racing an admin approving the same embargoed event. The atomic
    // organizer_notified_at claim in the dispatcher is what prevents the
    // second caller from double-notifying, simulated here by simply calling
    // it twice in a row for the same event.
    $follower = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    $this->actingAs($follower);
    $organizer->follow();

    $dispatcher = new EventNotificationDispatcher;
    $dispatcher->newEventFromFollowedOrganizer($event);
    // The travel is load-bearing. Tests run on MySQL, whose UPDATE reports
    // rows CHANGED, not rows matched — so back-to-back calls in the same
    // second write an identical now() timestamp, change nothing, and report
    // 0 affected. Without moving the clock, the second call bails out on
    // that accident rather than on the whereNull claim, and this test passes
    // even with the claim deleted (verified by mutating it away). One second
    // later, only the real guard can stop the second dispatch.
    $this->travel(2)->seconds();
    $dispatcher->newEventFromFollowedOrganizer($event);
    $this->travelBack();

    Notification::assertSentToTimes($follower, FollowedOrganizerNewEventNotification::class, 1);
    expect($event->fresh()->organizer_notified_at)->not->toBeNull();
});

test('newEventFromFollowedOrganizer only mails followers whose per-item override is on', function () {
    $optedIn = User::factory()->create();
    $optedOut = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($optedIn);
    $organizer->follow();
    DB::table('organizer_followers')
        ->where('organizer_id', $organizer->id)->where('user_id', $optedIn->id)
        ->update(['notify_new_events' => true]);

    $this->actingAs($optedOut);
    $organizer->follow();
    DB::table('organizer_followers')
        ->where('organizer_id', $organizer->id)->where('user_id', $optedOut->id)
        ->update(['notify_new_events' => false]);

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertSentTo($optedIn, FollowedOrganizerNewEventNotification::class, fn ($n, $channels) => in_array('mail', $channels));
    Notification::assertSentTo($optedOut, FollowedOrganizerNewEventNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});

test('newEventFromFollowedOrganizer does NOT mail when the follow has no override', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $organizer->follow(); // notify_new_events left null — following is not a request to be emailed

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertSentTo($user, FollowedOrganizerNewEventNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});
