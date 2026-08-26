<?php

use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\Events\Show;
use App\Models\LoginHistory;
use App\Models\Messaging\Conversation;
use App\Models\Organizer;
use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// The critical safety guard — Chris's explicit instruction: never let this
// touch an Organizer or Event a user created.
// ---------------------------------------------------------------------------

test('guests cannot delete an account', function () {
    $this->deleteJson('/api/account-settings')->assertUnauthorized();
});

test('a user who owns an organizer cannot delete their account, and the organizer survives', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson('/api/account-settings')
        ->assertStatus(422)
        ->assertJsonFragment(['message' => "This account still owns an organizer page. Transfer ownership to a teammate before deleting the account, or contact us at support@everythingimmersive.com and we'll help — we don't want to take that away from the events or team members who depend on it."]);

    expect(User::find($user->id))->not->toBeNull();
    expect(Organizer::find($organizer->id))->not->toBeNull();
});

test('a user who has created an event cannot delete their account, and the event survives', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson('/api/account-settings')
        ->assertStatus(422);

    expect(User::find($user->id))->not->toBeNull();
    expect(Event::find($event->id))->not->toBeNull();
});

test('a user who owns a community cannot delete their account, and the community survives', function () {
    $user = User::factory()->create();
    $community = Community::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson('/api/account-settings')
        ->assertStatus(422);

    expect(User::find($user->id))->not->toBeNull();
    expect(Community::find($community->id))->not->toBeNull();
});

test('an events organizer and its shows survive even if the creating user is blocked from deletion', function () {
    // Belt-and-suspenders: confirms the guard fires BEFORE any deletion
    // logic runs, so a user who owns both an organizer and its events is
    // still fully blocked and nothing downstream is touched.
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $user->id]);
    $event = Event::factory()->create(['user_id' => $user->id, 'organizer_id' => $organizer->id]);
    $show = Show::factory()->create(['event_id' => $event->id]);

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertStatus(422);

    expect(Organizer::find($organizer->id))->not->toBeNull();
    expect(Event::find($event->id))->not->toBeNull();
    expect(Show::find($show->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// The happy path — a plain user (no organizer/event/community) can delete
// ---------------------------------------------------------------------------

test('a plain user can delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson('/api/account-settings')
        ->assertOk();

    expect(User::find($user->id))->toBeNull();
});

test('deleting an account detaches team membership without deleting the organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(); // owned by someone else
    $organizer->users()->attach($user->id, ['role' => 'member']);

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(Organizer::find($organizer->id))->not->toBeNull();
    expect(DB::table('organizer_user')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('deleting an account removes the users own favorites', function () {
    $user = User::factory()->create();
    $event = Event::factory()->published()->create();
    $this->actingAs($user);
    $event->favorite();

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(DB::table('favorites')->where('favorited_id', $event->id)->where('favorited_type', Event::class)->exists())->toBeFalse();
    // The event itself is untouched.
    expect(Event::find($event->id))->not->toBeNull();
});

test('deleting an account cascades the users residential address and organizer follows', function () {
    $user = User::factory()->create();
    UserLocation::factory()->for($user)->create();
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($user);
    $organizer->follow();

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(UserLocation::where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('organizer_followers')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(Organizer::find($organizer->id))->not->toBeNull();
});

test('deleting an account detaches conversations without deleting the conversation for the other party', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->users()->attach([$user->id, $other->id]);

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(Conversation::find($conversation->id))->not->toBeNull();
    expect(DB::table('conversation_user')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('conversation_user')->where('user_id', $other->id)->exists())->toBeTrue();
});

test('deleting an account removes rows in FK-restricted tables so the delete does not throw', function () {
    // flags/ratings have a real FK with no cascade/null action (RESTRICT) —
    // if these aren't cleaned up first, $user->delete() throws.
    $user = User::factory()->create();
    DB::table('flags')->insert(['user_id' => $user->id, 'flaggable_id' => 1, 'flaggable_type' => 'App\\Models\\Event', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(DB::table('flags')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(User::find($user->id))->toBeNull();
});

test('deleting an account logs the user out', function () {
    // Checks the 'web' guard directly rather than $this->assertGuest():
    // /api/* routes sit behind auth:sanctum, and Pest's test client doesn't
    // reproduce the same-origin headers that bridge a real session onto an
    // API request the way a browser's axios call does (same gap documented
    // in LoginSecurityControllerTest) — so actingAs()'s own persisted auth
    // state doesn't fully unwind via assertGuest() here even though the
    // controller's Auth::guard('web')->logout() demonstrably ran (confirmed
    // live in-browser too). Checking the guard the controller actually calls
    // logout() on is what's really under test.
    $user = User::factory()->create();

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('deleting an account also removes the users own login history', function () {
    $user = User::factory()->create();
    LoginHistory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(0);
});

test('deleting one user never touches another users data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherEvent = Event::factory()->published()->create();
    $this->actingAs($other);
    $otherEvent->favorite();

    $this->actingAs($user)->deleteJson('/api/account-settings')->assertOk();

    expect(User::find($other->id))->not->toBeNull();
    expect(DB::table('favorites')->where('user_id', $other->id)->exists())->toBeTrue();
});

test('a blocked deletion leaves the user logged in', function () {
    // The controller's own blockingReason() check is defence-in-depth:
    // UserDeletionService::delete() re-checks inside the transaction and
    // throws, so removing the controller check still blocks the delete and
    // every existing test here stays green. Its one unique contribution is
    // bailing out BEFORE the logout — without it, a user who can't be
    // deleted gets signed out anyway on every attempt, not just on the rare
    // TOCTOU race the controller comment accepts that for.
    $user = User::factory()->create();
    Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);

    $this->actingAs($user)
        ->deleteJson('/api/account-settings')
        ->assertStatus(422);

    expect(Auth::guard('web')->check())->toBeTrue();
    expect(User::find($user->id))->not->toBeNull();
});
