<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;

/*
 * Account Settings > Notifications: opt-out email switches, each with the
 * fallback it reads while never set (User::wantsNotification), honored by
 * every sender. A switch only stops the EMAIL; the feed is unchanged.
 */

test('a member sees their switches, on by default; admins and owners see theirs', function () {
    $member = User::factory()->create(['type' => 'u', 'silence' => 'n']);
    $this->actingAs($member)->getJson('/api/account/notification-settings')
        ->assertOk()
        ->assertExactJson(['settings' => ['messages' => true, 'saved_event_dates' => true, 'followed_organizer_events' => true]]);

    $admin = User::factory()->create(['type' => 'a', 'silence' => 'n']);
    Organizer::factory()->create(['user_id' => $admin->id]);
    $keys = array_keys($this->actingAs($admin)->getJson('/api/account/notification-settings')->json('settings'));
    expect($keys)->toEqualCanonicalizing(['messages', 'saved_event_dates', 'followed_organizer_events', 'saved_search_matches', 'closing_soon', 'events', 'organizers', 'claims', 'show_run_requests']);

    // A moderator (not an admin) has the switch for the show run request emails they get.
    $moderator = User::factory()->create(['type' => 'm']);
    $this->actingAs($moderator)->patchJson('/api/account/notification-settings', ['show_run_requests' => false])
        ->assertOk()->assertJsonPath('settings.show_run_requests', false);
    expect($moderator->fresh()->wantsNotification('show_run_requests'))->toBeFalse();
});

test('the old "do not email me" setting starts messages and closing-soon off', function () {
    $user = User::factory()->create(['type' => 'u', 'silence' => 'y']);
    expect($user->wantsNotification('messages'))->toBeFalse()
        ->and($user->wantsNotification('closing_soon'))->toBeFalse()
        ->and($user->wantsNotification('saved_event_dates'))->toBeTrue();
});

test('claims follows organizers until it is set, and a change to organizers never moves it', function () {
    $admin = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => false]]);
    expect($admin->wantsNotification('claims'))->toBeFalse();

    // Turning organizers back on keeps claims off: its value is frozen first.
    $this->actingAs($admin)->patchJson('/api/account/notification-settings', ['organizers' => true])
        ->assertOk()->assertJsonPath('settings.organizers', true)->assertJsonPath('settings.claims', false);
});

test('only admins may change admin switches, and unknown keys are ignored', function () {
    $member = User::factory()->create(['type' => 'u']);
    $this->actingAs($member)->patchJson('/api/account/notification-settings', ['events' => false, 'nonsense' => false, 'messages' => false])->assertOk();

    $prefs = $member->fresh()->notification_preferences;
    expect($prefs)->not->toHaveKey('events')->not->toHaveKey('nonsense')
        ->and($prefs['messages'])->toBeFalse();
});

test('a guest cannot read or change them', function () {
    $this->getJson('/api/account/notification-settings')->assertUnauthorized();
    $this->patchJson('/api/account/notification-settings', ['messages' => false])->assertUnauthorized();
});

test('the follow switches only stop the email, and never start one for an item not turned on', function () {
    $event = Event::factory()->create();
    $on = User::factory()->create(['notification_preferences' => ['saved_event_dates' => true]]);
    $off = User::factory()->create(['notification_preferences' => ['saved_event_dates' => false]]);

    expect((new SavedEventNewDatesNotification($event, true))->via($on))->toBe(['database', 'mail'])
        ->and((new SavedEventNewDatesNotification($event, true))->via($off))->toBe(['database'])
        // Master on + the item never turned on: still no mail (opt-in per item).
        ->and((new SavedEventNewDatesNotification($event, null))->via($on))->toBe(['database']);

    $offOrg = User::factory()->create(['notification_preferences' => ['followed_organizer_events' => false]]);
    expect((new FollowedOrganizerNewEventNotification($event, true))->via($offOrg))->toBe(['database'])
        ->and((new FollowedOrganizerNewEventNotification($event, true))->via($on))->toBe(['database', 'mail']);
});

test('message emails honor the Messages switch, and the inbox still lights up', function () {
    Illuminate\Support\Facades\Mail::fake();
    $this->actingAs(User::factory()->create());
    $notify = (new ReflectionMethod(App\Http\Controllers\User\ConversationsController::class, 'notifyReceiver'));
    $conversation = (object) ['subject' => 'Your event', 'id' => 1];

    $wants = User::factory()->create(['unread' => null, 'silence' => 'n']);
    $optedOut = User::factory()->create(['unread' => null, 'notification_preferences' => ['messages' => false]]);
    foreach ([$wants, $optedOut] as $receiver) {
        $notify->invoke(app(App\Http\Controllers\User\ConversationsController::class), $receiver, null, $conversation);
    }

    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\Message::class, 1);
    Illuminate\Support\Facades\Mail::assertQueued(App\Mail\Message::class, fn ($mail) => $mail->hasTo($wants->email));
    expect($optedOut->fresh()->unread)->toBe('m');
});
