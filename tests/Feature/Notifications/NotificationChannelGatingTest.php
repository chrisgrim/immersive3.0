<?php

use App\Models\Event;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;

// The core requirement (Chris, item 9): the in-app feed must show updates
// even when the user has turned off email notifications — so 'database'
// must always be present, and 'mail' must be the only channel gated by the
// user's preference.

test('saved-event-new-dates always includes database, only includes mail when opted in', function () {
    $event = Event::factory()->published()->create();
    $notification = new SavedEventNewDatesNotification($event);

    $optedOut = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => false]]);
    expect($notification->via($optedOut))->toBe(['database']);

    $optedIn = User::factory()->create(['notification_preferences' => ['saved_event_new_dates' => true]]);
    expect($notification->via($optedIn))->toBe(['database', 'mail']);

    $default = User::factory()->create(['notification_preferences' => null]);
    expect($notification->via($default))->toBe(['database']);
});

test('followed-organizer-new-event always includes database, only includes mail when opted in', function () {
    $event = Event::factory()->published()->create();
    $notification = new FollowedOrganizerNewEventNotification($event);

    $optedOut = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => false]]);
    expect($notification->via($optedOut))->toBe(['database']);

    $optedIn = User::factory()->create(['notification_preferences' => ['followed_organizer_new_event' => true]]);
    expect($notification->via($optedIn))->toBe(['database', 'mail']);
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
