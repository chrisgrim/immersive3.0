<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

// Regression coverage for the bug behind "Get updates still not working":
// the toggle used to read/write two GLOBAL notification_preferences keys
// shared across every event/organizer, so turning it on for one item made it
// appear "already on" for every other item too. These per-item routes and
// the notifyUpdates field in FavoriteController::index() are what actually
// scope it to a single favorite/follow row — see FavoriteController::updateNotify().

test('turning on updates for one saved event does not affect another', function () {
    $user = User::factory()->create();
    $organizerA = Organizer::factory()->create(['status' => 'p']);
    $organizerB = Organizer::factory()->create(['status' => 'p']);
    $eventA = Event::factory()->published()->create(['organizer_id' => $organizerA->id]);
    $eventB = Event::factory()->published()->create(['organizer_id' => $organizerB->id]);

    $this->actingAs($user);
    $eventA->favorite();
    $eventB->favorite();

    // eventB explicitly muted beforehand, to prove toggling eventA doesn't leak into it.
    $this->actingAs($user)->patchJson("/api/hub/events/{$eventB->slug}/notify-updates", ['enabled' => false])->assertOk();

    $this->actingAs($user)
        ->patchJson("/api/hub/events/{$eventA->slug}/notify-updates", ['enabled' => true])
        ->assertOk()
        ->assertJson(['notifyUpdates' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $bySlug = collect($response->json('events.data'))->keyBy('slug');

    expect($bySlug[$eventA->slug]['notifyUpdates'])->toBeTrue();
    expect($bySlug[$eventB->slug]['notifyUpdates'])->toBeFalse();
});

test('turning on updates follows the organizer if not already following', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();

    expect($organizer->isFollowed())->toBeFalse();

    $this->actingAs($user)
        ->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => true])
        ->assertOk();

    expect($organizer->isFollowed())->toBeTrue();
    $this->assertDatabaseHas('organizer_followers', [
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'notify_new_events' => 1,
    ]);
});

test('turning off updates does not unfollow the organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();
    $this->actingAs($user)->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => true])->assertOk();
    $this->actingAs($user)->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => false])->assertOk();

    expect($organizer->isFollowed())->toBeTrue();
    $this->assertDatabaseHas('organizer_followers', [
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'notify_new_events' => 0,
    ]);
});

test('toggling updates for an event the user never favorited 404s', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();

    $this->actingAs($user)
        ->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => true])
        ->assertStatus(404);
});

test('a guest cannot toggle notify updates', function () {
    $event = Event::factory()->published()->create();

    $this->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => true])
        ->assertStatus(401);
});

test('an untouched favorite defaults to NOT notifying (opt-in)', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $data = collect($response->json('events.data'))->firstWhere('slug', $event->slug);

    // Opt-in: saving an event and following an organizer are not by
    // themselves requests to be emailed. Only an explicit toggle is.
    expect($data['notifyUpdates'])->toBeFalse();
});

test('an explicit per-item override wins over the default', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();
    $this->actingAs($user)->patchJson("/api/hub/events/{$event->slug}/notify-updates", ['enabled' => false])->assertOk();

    $response = $this->actingAs($user)->getJson('/api/hub/events');
    $data = collect($response->json('events.data'))->firstWhere('slug', $event->slug);

    expect($data['notifyUpdates'])->toBeFalse();
});

test('the per-event notify flag alone decides notifyUpdates when there is no organizer', function () {
    // Isolation matters here: notifyUpdates ANDs the favorite's own
    // notify_new_dates with the organizer-follow's notify_new_events, and an
    // unfollowed organizer contributes false — which masks the event flag in
    // every other test on this endpoint. A mutation flipping the favorite
    // side's opt-in default back to "on" therefore survived the whole suite.
    // An event with no organizer is the one case where the event flag is
    // decisive on its own.
    $user = User::factory()->create();
    $event = Event::factory()->published()->create(['organizer_id' => null]);

    $this->actingAs($user);
    $event->favorite();

    $read = fn () => collect($this->getJson('/api/hub/events')->json('events.data'))
        ->firstWhere('slug', $event->slug)['notifyUpdates'];

    // Untouched favorite: opt-in means off.
    expect($read())->toBeFalse();

    $event->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => true]);
    expect($read())->toBeTrue();
});

test('an organizer follow that never opted in keeps notifyUpdates off, even when the event half is on', function () {
    // The mirror of the test above, isolating the OTHER half. Reachable by
    // opting in (which follows the organizer and sets both true), then
    // unfollowing and following again — the new follow row starts null while
    // the favorite's own flag stays true. Opt-in has to hold on both halves
    // independently, or one of them silently reverts to emailing people.
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();
    $event->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => true]);
    DB::table('organizer_followers')
        ->where(['organizer_id' => $organizer->id, 'user_id' => $user->id])
        ->update(['notify_new_events' => null]);

    $data = collect($this->getJson('/api/hub/events')->json('events.data'))
        ->firstWhere('slug', $event->slug);

    expect($data['notifyUpdates'])->toBeFalse();
});
