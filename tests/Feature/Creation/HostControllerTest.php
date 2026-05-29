<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TrackClick;
use App\Models\User;

// HostController is reached via the authed + verified web routes:
//   GET /hosting/events           -> hosting.dashboard (show)
//   GET /hosting/getting-started  -> hosting.intro (intro)
// Both live inside the auth + verified middleware groups in routes/web.php.

// ----- show() : guards -----

test('show redirects an unauthenticated guest to login', function () {
    // note: the auth middleware redirects guests to the login route.
    $this->get('/hosting/events')->assertRedirect('/login');
});

test('show redirects a verified user with no teams to the intro page', function () {
    // A plain user owns no organizer and belongs to no team pivot.
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->get('/hosting/events')
        ->assertRedirect(route('hosting.intro'))
        ->assertSessionHas('info');
});

test('show renders the dashboard for a user who owns an organizer', function () {
    $user = User::factory()->create(['type' => 'u']);
    // OrganizerFactory::configure() auto-attaches the owner to the
    // organizer_user pivot with role 'owner', so teams()->exists() is true.
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $user->update(['current_team_id' => $organizer->id]);

    $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk()
        ->assertViewIs('creation.index')
        ->assertViewHas('organizer');
});

test('show resolves the current organizer even when current_team_id is null', function () {
    // getCurrentOrganizer() falls back to the first owned organizer and
    // backfills current_team_id when it is unset.
    $user = User::factory()->create(['type' => 'u', 'current_team_id' => null]);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);

    $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk()
        ->assertViewIs('creation.index');

    // current_team_id was backfilled to the owned organizer.
    expect($user->fresh()->current_team_id)->toBe($organizer->id);
});

test('show resolves the current organizer via team membership for a non-owner member', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    // A member attached only through the pivot (does not own the organizer).
    $member = User::factory()->create(['type' => 'u', 'current_team_id' => null]);
    $organizer->users()->attach($member->id, ['role' => 'editor']);

    $this->actingAs($member)
        ->get('/hosting/events')
        ->assertOk()
        ->assertViewIs('creation.index');

    expect($member->fresh()->current_team_id)->toBe($organizer->id);
});

test('show renders the dashboard view for a moderator who owns an organizer', function () {
    $moderator = User::factory()->create(['type' => 'm']);
    $organizer = Organizer::factory()->create(['user_id' => $moderator->id, 'status' => 'p']);
    $moderator->update(['current_team_id' => $organizer->id]);

    $this->actingAs($moderator)
        ->get('/hosting/events')
        ->assertOk()
        ->assertViewIs('creation.index');
});

// ----- show() : event/click/favorite loading -----

test('show eager-loads the organizers events including soft-deleted ones', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $user->update(['current_team_id' => $organizer->id]);

    $live = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
    ]);
    $trashed = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
    ]);
    $trashed->delete();

    $response = $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk();

    $organizerView = $response->viewData('organizer');
    $ids = $organizerView->events->pluck('id')->all();

    // Both the live and the trashed event are present (withTrashed()).
    expect($ids)->toContain($live->id);
    expect($ids)->toContain($trashed->id);

    $trashedView = $organizerView->events->firstWhere('id', $trashed->id);
    expect($trashedView->is_deleted)->toBeTrue();

    $liveView = $organizerView->events->firstWhere('id', $live->id);
    expect($liveView->is_deleted)->toBeFalse();
});

test('show computes total_clicks per event from the clicks relation', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $user->update(['current_team_id' => $organizer->id]);

    $event = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
    ]);

    // Three clicks for this event; a click on an unrelated event must not count.
    TrackClick::factory()->count(3)->create([
        'event_id' => $event->id,
        'organizer_id' => $organizer->id,
    ]);
    $other = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
    ]);
    TrackClick::factory()->create([
        'event_id' => $other->id,
        'organizer_id' => $organizer->id,
    ]);

    $response = $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk();

    $organizerView = $response->viewData('organizer');
    $eventView = $organizerView->events->firstWhere('id', $event->id);
    $otherView = $organizerView->events->firstWhere('id', $other->id);

    expect($eventView->total_clicks)->toBe(3);
    expect($otherView->total_clicks)->toBe(1);
});

test('show loads favorites onto each event', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $user->update(['current_team_id' => $organizer->id]);

    $event = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
    ]);

    $favoriter = User::factory()->create(['type' => 'u']);
    $event->favorites()->create(['user_id' => $favoriter->id]);

    $response = $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk();

    $organizerView = $response->viewData('organizer');
    $eventView = $organizerView->events->firstWhere('id', $event->id);

    // favorites is eager-loaded (relationLoaded) and contains the one favorite.
    expect($eventView->relationLoaded('favorites'))->toBeTrue();
    expect($eventView->favorites)->toHaveCount(1);
});

test('show renders for an organizer with zero events', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $user->update(['current_team_id' => $organizer->id]);

    $response = $this->actingAs($user)
        ->get('/hosting/events')
        ->assertOk()
        ->assertViewIs('creation.index');

    expect($response->viewData('organizer')->events)->toHaveCount(0);
});

// ----- intro() -----

test('intro renders the getting-started view for a verified user', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->get('/hosting/getting-started')
        ->assertOk()
        ->assertViewIs('creation.started');
});

test('intro renders even for a user who already owns an organizer', function () {
    // intro is not gated by team membership, unlike show().
    $user = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);

    $this->actingAs($user)
        ->get('/hosting/getting-started')
        ->assertOk()
        ->assertViewIs('creation.started');
});

test('intro redirects an unauthenticated guest to login', function () {
    $this->get('/hosting/getting-started')->assertRedirect('/login');
});
