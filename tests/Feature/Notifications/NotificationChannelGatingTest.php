<?php

use App\Models\Event;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;
use Illuminate\Support\Facades\Log;

// The core requirement (Chris, item 9): the in-app feed must show updates
// even when a user has silenced email notifications — so 'database' must
// always be present, and 'mail' must be the only channel gated. Gating is
// purely the per-item override passed into the notification's own
// constructor (null defaults to notify) — there's no separate account-wide
// preference checked here; "Clear all notifications" is a one-time bulk
// write to those per-item rows, not a persistent flag read at send time.

test('saved-event-new-dates always includes database, gated only by the per-item override', function () {
    $user = User::factory()->create();

    expect((new SavedEventNewDatesNotification($event = Event::factory()->published()->create(), null))->via($user))
        ->toBe(['database', 'mail']); // no override → defaults to notify
    expect((new SavedEventNewDatesNotification($event, true))->via($user))->toBe(['database', 'mail']);
    expect((new SavedEventNewDatesNotification($event, false))->via($user))->toBe(['database']);
});

test('saved-event-new-dates mail is additionally gated by the mail-frequency throttle', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    expect((new SavedEventNewDatesNotification($event, true, mailAllowed: false))->via($user))->toBe(['database']);
    expect((new SavedEventNewDatesNotification($event, true, mailAllowed: true))->via($user))->toBe(['database', 'mail']);
});

test('followed-organizer-new-event always includes database, gated only by the per-item override', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    expect((new FollowedOrganizerNewEventNotification($event, null))->via($user))->toBe(['database', 'mail']);
    expect((new FollowedOrganizerNewEventNotification($event, true))->via($user))->toBe(['database', 'mail']);
    expect((new FollowedOrganizerNewEventNotification($event, false))->via($user))->toBe(['database']);
});

test('toDatabase payloads carry what the feed needs to render', function () {
    $event = Event::factory()->published()->create(['name' => 'Test Event']);
    $user = User::factory()->create();

    $data = (new SavedEventNewDatesNotification($event))->toDatabase($user);
    expect($data)->toMatchArray([
        'type' => 'saved_event_new_dates',
        'event_id' => $event->id,
        'event_slug' => $event->slug,
        'event_name' => 'Test Event',
    ]);
});

// ---------------------------------------------------------------------------
// Retry backoff + failure logging — a mail-provider outage shouldn't be
// hammered 3x back-to-back, and a permanently-failed send shouldn't just be
// an anonymous row in failed_jobs with no context.
// ---------------------------------------------------------------------------

test('both notifications escalate backoff across retries instead of retrying immediately', function () {
    $event = Event::factory()->published()->create();

    expect((new SavedEventNewDatesNotification($event))->backoff)->toBe([60, 300, 900]);
    expect((new FollowedOrganizerNewEventNotification($event))->backoff)->toBe([60, 300, 900]);
});

test('a permanently-failed saved-event-new-dates send logs which event/error it was', function () {
    Log::spy();
    $event = Event::factory()->published()->create(['name' => 'Failure Test Event']);
    $exception = new Exception('mail server unreachable');

    (new SavedEventNewDatesNotification($event))->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message, $context) => $message === '[notifications] saved_event_new_dates permanently failed'
            && $context['event_id'] === $event->id
            && $context['event_slug'] === $event->slug
            && $context['exception'] === 'mail server unreachable'
    );
});

test('a permanently-failed followed-organizer-new-event send logs which event/error it was', function () {
    Log::spy();
    $event = Event::factory()->published()->create(['name' => 'Failure Test Event']);
    $exception = new Exception('mail server unreachable');

    (new FollowedOrganizerNewEventNotification($event))->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message, $context) => $message === '[notifications] followed_organizer_new_event permanently failed'
            && $context['event_id'] === $event->id
            && $context['event_slug'] === $event->slug
            && $context['exception'] === 'mail server unreachable'
    );
});
