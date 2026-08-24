<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// GET /api/hub/notification-preferences/counts
// ---------------------------------------------------------------------------

test('a guest cannot read notification counts', function () {
    $this->getJson('/api/hub/notification-preferences/counts')->assertStatus(401);
});

test('counts only include favorites/follows currently notifying, not the raw totals', function () {
    $user = User::factory()->create();
    $notifying = Event::factory()->published()->create();
    $silenced = Event::factory()->published()->create();
    $this->actingAs($user);
    $notifying->favorite();
    $silenced->favorite();
    $silenced->favorites()->where('user_id', $user->id)->update(['notify_new_dates' => false]);

    $orgNotifying = Organizer::factory()->create(['status' => 'p']);
    $orgSilenced = Organizer::factory()->create(['status' => 'p']);
    $orgNotifying->follow();
    $orgSilenced->follow();
    DB::table('organizer_followers')->where(['organizer_id' => $orgSilenced->id, 'user_id' => $user->id])->update(['notify_new_events' => false]);

    $response = $this->actingAs($user)->getJson('/api/hub/notification-preferences/counts')->assertOk();

    // 2 saved/2 followed total, but only 1 of each still notifies.
    expect($response->json())->toBe([
        'saved_events_count' => 1,
        'followed_organizers_count' => 1,
    ]);
});

test('an untouched favorite/follow (no override yet) counts as notifying', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();

    $response = $this->actingAs($user)->getJson('/api/hub/notification-preferences/counts')->assertOk();

    expect($response->json())->toBe([
        'saved_events_count' => 1,
        'followed_organizers_count' => 1,
    ]);
});

test('counts do not include another users favorites or follows', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($other);
    Event::factory()->published()->create()->favorite();
    Organizer::factory()->create(['status' => 'p'])->follow();

    $response = $this->actingAs($user)->getJson('/api/hub/notification-preferences/counts')->assertOk();

    expect($response->json())->toBe(['saved_events_count' => 0, 'followed_organizers_count' => 0]);
});

// ---------------------------------------------------------------------------
// POST /api/hub/notification-preferences/clear-all
// ---------------------------------------------------------------------------

test('a guest cannot clear all notifications', function () {
    $this->postJson('/api/hub/notification-preferences/clear-all')->assertStatus(401);
});

test('clearing sets notify_new_dates false on every one of the users favorites', function () {
    $user = User::factory()->create();
    $eventA = Event::factory()->published()->create();
    $eventB = Event::factory()->published()->create();
    $this->actingAs($user);
    $eventA->favorite();
    $eventB->favorite();

    $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    expect($eventA->favorites()->where('user_id', $user->id)->value('notify_new_dates'))->toBe(0);
    expect($eventB->favorites()->where('user_id', $user->id)->value('notify_new_dates'))->toBe(0);
});

test('clearing sets notify_new_events false on every one of the users followed organizers', function () {
    $user = User::factory()->create();
    $orgA = Organizer::factory()->create(['status' => 'p']);
    $orgB = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($user);
    $orgA->follow();
    $orgB->follow();

    $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    expect(DB::table('organizer_followers')->where(['organizer_id' => $orgA->id, 'user_id' => $user->id])->value('notify_new_events'))->toBe(0);
    expect(DB::table('organizer_followers')->where(['organizer_id' => $orgB->id, 'user_id' => $user->id])->value('notify_new_events'))->toBe(0);
});

test('clearing does not unsave events or unfollow organizers, only silences them', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($user);
    $event->favorite();
    $organizer->follow();

    $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    expect($user->fresh()->favouritedEvents()->count())->toBe(1);
    expect($user->fresh()->followedOrganizers()->count())->toBe(1);
});

test('clearing also turns off the saved-search notify pilot toggle on every one of the users searches', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true]);

    $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    expect($search->fresh()->notify_new_events)->toBeFalse();
});

test('clearing does not touch another users favorites or follows', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $event = Event::factory()->published()->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);

    $this->actingAs($other);
    $event->favorite();
    $organizer->follow();
    $event->favorites()->where('user_id', $other->id)->update(['notify_new_dates' => true]);
    DB::table('organizer_followers')->where(['organizer_id' => $organizer->id, 'user_id' => $other->id])->update(['notify_new_events' => true]);

    $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    expect($event->favorites()->where('user_id', $other->id)->value('notify_new_dates'))->toBe(1);
    expect(DB::table('organizer_followers')->where(['organizer_id' => $organizer->id, 'user_id' => $other->id])->value('notify_new_events'))->toBe(1);
});

test('clearing returns the callers post-clear notifying counts, both zero', function () {
    $user = User::factory()->create();
    $events = Event::factory()->published()->count(3)->create();
    $this->actingAs($user);
    foreach ($events as $event) {
        $event->favorite();
    }
    Organizer::factory()->create(['status' => 'p'])->follow();

    $response = $this->actingAs($user)->postJson('/api/hub/notification-preferences/clear-all')->assertOk();

    // Raw totals are 3 saved / 1 followed, but nothing notifies anymore —
    // this is what tells the page's "Clear all" button visibly worked.
    expect($response->json())->toBe([
        'saved_events_count' => 0,
        'followed_organizers_count' => 0,
    ]);
});

test('clearing with nothing saved or followed does not error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/hub/notification-preferences/clear-all')
        ->assertOk()
        ->assertJson(['saved_events_count' => 0, 'followed_organizers_count' => 0]);
});
