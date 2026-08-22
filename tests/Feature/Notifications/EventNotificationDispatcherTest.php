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

test('newDatesForSavedEvent only mails the favoriters whose per-item override is on, given the global setting is on', function () {
    $optedIn = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
    $optedOut = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
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

test('newDatesForSavedEvent falls back to the account-wide default when the favorite has no override', function () {
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite(); // notify_new_dates left null — never touched the per-item toggle

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertSentTo($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => in_array('mail', $channels));
});

test('newDatesForSavedEvent never mails when the global setting is off, even with the per-item override explicitly on', function () {
    // The master-switch behavior Chris asked for: the account-wide toggle
    // in Account Settings overrides every per-event "Get updates" toggle —
    // it can't be circumvented by opting one specific event back in.
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => false]]);
    $event = Event::factory()->published()->create();

    $this->actingAs($user);
    $event->favorite();
    $event->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => true]);

    (new EventNotificationDispatcher)->newDatesForSavedEvent($event);

    Notification::assertSentTo($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});

test('newDatesForSavedEvent only mails once within the cooldown window, even across several separate dispatches', function () {
    // An organizer adding dates across several separate edits in one day
    // (each edit calling this method independently, the way
    // UpdateEventAction does per save) should collapse into one email, not
    // one per edit — the in-app feed still records every occurrence.
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
    $event = Event::factory()->published()->create();
    $this->actingAs($user);
    $event->favorite();

    $dispatcher = new EventNotificationDispatcher;
    $dispatcher->newDatesForSavedEvent($event);
    $dispatcher->newDatesForSavedEvent($event);
    $dispatcher->newDatesForSavedEvent($event);

    Notification::assertSentTimes(SavedEventNewDatesNotification::class, 3); // database channel every time

    $mailed = Notification::sent($user, SavedEventNewDatesNotification::class, fn ($n, $channels) => in_array('mail', $channels));
    expect($mailed)->toHaveCount(1);
});

test('newDatesForSavedEvent mails again once the cooldown window has passed', function () {
    $user = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
    $event = Event::factory()->published()->create(['notified_new_dates_at' => now()->subHours(25)]);
    $this->actingAs($user);
    $event->favorite();

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
    $dispatcher->newEventFromFollowedOrganizer($event);

    Notification::assertSentToTimes($follower, FollowedOrganizerNewEventNotification::class, 1);
    expect($event->fresh()->organizer_notified_at)->not->toBeNull();
});

test('newEventFromFollowedOrganizer only mails the followers whose per-item override is on, given the global setting is on', function () {
    $optedIn = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => true]]);
    $optedOut = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => true]]);
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

test('newEventFromFollowedOrganizer falls back to the account-wide default when the follow has no override', function () {
    $user = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => true]]);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $organizer->follow(); // notify_new_events left null — never touched the per-item toggle

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertSentTo($user, FollowedOrganizerNewEventNotification::class, fn ($n, $channels) => in_array('mail', $channels));
});

test('newEventFromFollowedOrganizer never mails when the global setting is off, even with the per-item override explicitly on', function () {
    // Same master-switch behavior as the saved-events side.
    $user = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => false]]);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $organizer->follow();
    DB::table('organizer_followers')
        ->where('organizer_id', $organizer->id)->where('user_id', $user->id)
        ->update(['notify_new_events' => true]);

    (new EventNotificationDispatcher)->newEventFromFollowedOrganizer($event);

    Notification::assertSentTo($user, FollowedOrganizerNewEventNotification::class, fn ($n, $channels) => ! in_array('mail', $channels));
});
