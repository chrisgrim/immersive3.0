<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * Two rules the wizard enforced only in the browser, now enforced on the
 * server for every write path (the hosting API and the MCP update-event
 * tool): an event under review is frozen for its organizer, and a live
 * event is renamed through a reviewed name-change request. Moderators are
 * exempt from both.
 */
function guardedEvent(string $status, string $userType = 'u'): array
{
    $user = User::factory()->create(['type' => $userType, 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'status' => $status,
        'name' => 'Original  Name',
        'closingDate' => now()->addMonth(),
    ]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return [$user, $event];
}

test('an organizer cannot edit an event under review on the website', function () {
    [$user, $event] = guardedEvent('r');

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", ['tag_line' => 'Swapped after submitting'])
        ->assertForbidden();
    expect($event->fresh()->tag_line)->not->toBe('Swapped after submitting');
});

test('an organizer cannot pull an event out of review by sending a wizard status', function () {
    [$user, $event] = guardedEvent('r');

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", ['status' => '1'])->assertForbidden();
    expect($event->fresh()->status)->toBe('r');
});

test('a moderator can still edit an event under review', function () {
    [, $event] = guardedEvent('r');
    $moderator = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);

    $this->actingAs($moderator)->postJson("/api/hosting/event/{$event->slug}", ['tag_line' => 'Fixed by staff'])->assertOk();
    expect($event->fresh()->tag_line)->toBe('Fixed by staff');
});

test('an organizer cannot rename a live event directly, on the website or through MCP', function (string $status) {
    [$user, $event] = guardedEvent($status);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Brand New Name'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name']);

    EiServer::actingAs($user)->tool(UpdateEvent::class, ['event_slug' => $event->slug, 'name' => 'Brand New Name'])
        ->assertHasErrors();

    expect($event->fresh()->name)->toBe('Original  Name');
})->with(['published' => 'p', 'embargoed' => 'e']);

test('resending a live event name that differs only in spacing is not a rename', function () {
    [$user, $event] = guardedEvent('p');

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Original Name', 'tag_line' => 'Still saves'])
        ->assertOk();
    expect($event->fresh()->tag_line)->toBe('Still saves');
});

test('a draft can still be renamed by its organizer, and a moderator can rename a live event', function () {
    [$user, $draft] = guardedEvent('0');
    $this->actingAs($user)->postJson("/api/hosting/event/{$draft->slug}", ['name' => 'Draft Rename'])->assertOk();
    expect($draft->fresh()->name)->toBe('Draft Rename');

    [, $live] = guardedEvent('p');
    $moderator = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    $this->actingAs($moderator)->postJson("/api/hosting/event/{$live->slug}", ['name' => 'Staff Rename'])->assertOk();
    expect($live->fresh()->name)->toBe('Staff Rename');
});

test('an organizer under review is refused by MCP update-event too', function () {
    [$user, $event] = guardedEvent('r');

    EiServer::actingAs($user)->tool(UpdateEvent::class, ['event_slug' => $event->slug, 'tag_line' => 'Swapped'])
        ->assertHasErrors();
    expect($event->fresh()->tag_line)->not->toBe('Swapped');
});
