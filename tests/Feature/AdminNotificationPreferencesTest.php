<?php

use App\Mail\EventSubmittedNotification;
use App\Mail\NameChangeNotification;
use App\Mail\OrganizerSubmittedNotification;
use App\Mail\OwnershipClaimNotification;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/** A staff-owned, externally-unowned (claimable) organizer. */
function staffOrg(): Organizer
{
    return Organizer::factory()->create([
        'user_id' => User::factory()->create(['type' => 'm'])->id,
        'status' => 'p',
    ]);
}

// ---------------------------------------------------------------------------
// wantsNotification() helper — opt-out (default ON) semantics
// ---------------------------------------------------------------------------

test('wantsNotification defaults a missing key to on', function () {
    $user = User::factory()->create(['type' => 'a', 'notification_preferences' => null]);
    expect($user->wantsNotification('organizers'))->toBeTrue();
    expect($user->wantsNotification('events'))->toBeTrue();

    $user->update(['notification_preferences' => ['organizers' => false]]);
    $user->refresh();
    expect($user->wantsNotification('organizers'))->toBeFalse();
    // A key that isn't present still defaults ON.
    expect($user->wantsNotification('events'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Saving preferences via the profile endpoint
// ---------------------------------------------------------------------------

test('an admin can save notification preferences via their profile', function () {
    $admin = User::factory()->create(['type' => 'a']);

    $this->actingAs($admin)
        ->postJson("/users/{$admin->id}", [
            'notification_preferences' => ['organizers' => false, 'events' => true],
        ])
        ->assertOk();

    expect($admin->fresh()->notification_preferences)->toMatchArray(['organizers' => false, 'events' => true]);
});

test('a moderator cannot change another admin notification preferences', function () {
    $admin = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => true]]);
    $mod = User::factory()->create(['type' => 'm']);

    $this->actingAs($mod)
        ->postJson("/users/{$admin->id}", ['notification_preferences' => ['organizers' => false]])
        ->assertOk();

    // The field is personal to the admin — a moderator's write is ignored.
    expect($admin->fresh()->wantsNotification('organizers'))->toBeTrue();
});

test('a non-admin editing self does not persist admin notification preferences', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson("/users/{$user->id}", ['notification_preferences' => ['organizers' => false]])
        ->assertOk();

    expect($user->fresh()->notification_preferences)->toBeNull();
});

// ---------------------------------------------------------------------------
// Existing admin emails respect the 'organizers' opt-out
// ---------------------------------------------------------------------------

test('ownership-claim admin email skips admins who opted out of organizer notifications', function () {
    $optedOut = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => false]]);
    $subscribed = User::factory()->create(['type' => 'a']);
    $org = staffOrg();
    $claimant = User::factory()->create(['type' => 'u']);

    $this->actingAs($claimant)->postJson("/api/organizers/{$org->slug}/claim")->assertStatus(201);

    Mail::assertSent(OwnershipClaimNotification::class, fn ($m) => $m->hasTo($subscribed->email));
    Mail::assertNotSent(OwnershipClaimNotification::class, fn ($m) => $m->hasTo($optedOut->email));
});

test('name-change admin email respects the organizer opt-out', function () {
    $optedOut = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => false]]);
    $subscribed = User::factory()->create(['type' => 'a']);
    $org = Organizer::factory()->create();
    $requester = User::factory()->create(['type' => 'u']);

    $this->actingAs($requester);
    (new NameChangeRequestService)->handleNameChange($org, 'A Brand New Name', 'rebrand');

    Mail::assertSent(NameChangeNotification::class, fn ($m) => $m->hasTo($subscribed->email));
    Mail::assertNotSent(NameChangeNotification::class, fn ($m) => $m->hasTo($optedOut->email));
});

// ---------------------------------------------------------------------------
// New submission emails, gated by their domain pref
// ---------------------------------------------------------------------------

test('creating an organizer emails admins who want organizer notifications', function () {
    $optedOut = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => false]]);
    $subscribed = User::factory()->create(['type' => 'a']);
    $creator = User::factory()->create(['type' => 'u']);

    $this->actingAs($creator)
        ->postJson('/organizers', ['name' => 'Fresh Org', 'description' => 'A new org'])
        ->assertOk();

    Mail::assertSent(OrganizerSubmittedNotification::class, fn ($m) => $m->hasTo($subscribed->email));
    Mail::assertNotSent(OrganizerSubmittedNotification::class, fn ($m) => $m->hasTo($optedOut->email));
});

test('submitting an event emails admins who want event notifications', function () {
    $optedOut = User::factory()->create(['type' => 'a', 'notification_preferences' => ['events' => false]]);
    $subscribed = User::factory()->create(['type' => 'a']);

    $organizer = Organizer::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($member->id, ['role' => 'owner']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);

    $this->actingAs($member)
        ->postJson(route('hosting.event.submit', $event))
        ->assertOk();

    Mail::assertSent(EventSubmittedNotification::class, fn ($m) => $m->hasTo($subscribed->email));
    Mail::assertNotSent(EventSubmittedNotification::class, fn ($m) => $m->hasTo($optedOut->email));
});

test('the organizers opt-out does not silence event notifications (independent toggles)', function () {
    // Opted out of organizers, still wants events.
    $admin = User::factory()->create(['type' => 'a', 'notification_preferences' => ['organizers' => false, 'events' => true]]);

    $organizer = Organizer::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($member->id, ['role' => 'owner']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);

    $this->actingAs($member)->postJson(route('hosting.event.submit', $event))->assertOk();

    Mail::assertSent(EventSubmittedNotification::class, fn ($m) => $m->hasTo($admin->email));
});

// ---------------------------------------------------------------------------
// The new mailables actually render (Mail::fake never renders the view)
// ---------------------------------------------------------------------------

test('the submission emails render', function () {
    // Explicit names (no special chars) so the substring check isn't tripped by HTML-escaping.
    $org = Organizer::factory()->create(['name' => 'Render Test Org']);
    expect((new OrganizerSubmittedNotification($org))->render())->toContain('Render Test Org');

    $event = Event::factory()->create(['organizer_id' => $org->id, 'name' => 'Render Test Event']);
    expect((new EventSubmittedNotification($event))->render())->toContain('Render Test Event');
});
