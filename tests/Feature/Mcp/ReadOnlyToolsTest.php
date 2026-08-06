<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListEventAttributes;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\Whoami;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

function mcpUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function organizerFor(User $user): Organizer
{
    return Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
}

// ── whoami ─────────────────────────────────────────────────────────────

test('whoami returns the user and their organizers', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);

    $response = EiServer::actingAs($user)->tool(Whoami::class);

    $response->assertOk()
        ->assertSee($user->email)
        ->assertSee($organizer->name)
        ->assertSee('owner');
});

test('whoami hints when the user has no organizer', function () {
    $response = EiServer::actingAs(mcpUser())->tool(Whoami::class);

    $response->assertOk()->assertSee('create-organizer');
});

// ── list-event-attributes ──────────────────────────────────────────────

test('list-event-attributes returns categories', function () {
    Category::factory()->create(['name' => 'Immersive Theatre']);

    $response = EiServer::actingAs(mcpUser())
        ->tool(ListEventAttributes::class, ['type' => 'categories']);

    $response->assertOk()->assertSee('Immersive Theatre');
});

test('list-event-attributes rejects an unknown type', function () {
    $response = EiServer::actingAs(mcpUser())
        ->tool(ListEventAttributes::class, ['type' => 'nonsense']);

    $response->assertHasErrors();
});

// ── list-my-events ─────────────────────────────────────────────────────

test('list-my-events returns drafts for my organizer only', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);
    $mine = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0', 'name' => 'My Draft Event']);

    $stranger = mcpUser();
    $otherOrg = organizerFor($stranger);
    Event::factory()->create(['organizer_id' => $otherOrg->id, 'status' => '0', 'name' => 'Someone Elses Event']);

    $response = EiServer::actingAs($user)->tool(ListMyEvents::class);

    $response->assertOk()
        ->assertSee('My Draft Event')
        ->assertDontSee('Someone Elses Event');
});

test('list-my-events rejects filtering by an organizer the user does not belong to', function () {
    $user = mcpUser();
    organizerFor($user);
    $otherOrg = organizerFor(mcpUser());

    $response = EiServer::actingAs($user)
        ->tool(ListMyEvents::class, ['organizer_id' => $otherOrg->id]);

    $response->assertHasErrors();
});

// ── get-event ──────────────────────────────────────────────────────────

test('get-event returns state and readiness for an owned draft', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => '0',
        'name' => 'Readiness Probe',
        'description' => null,
    ]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    $response = EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertOk()
        ->assertSee('Readiness Probe')
        ->assertSee('readiness')
        ->assertSee('missing');
});

test('get-event denies events the user cannot manage', function () {
    $stranger = mcpUser();
    $otherOrg = organizerFor(mcpUser());
    $event = Event::factory()->create(['organizer_id' => $otherOrg->id, 'status' => '0']);

    $response = EiServer::actingAs($stranger)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertHasErrors();
});

test('get-event lets a moderator view any event', function () {
    $moderator = mcpUser('m');
    $event = Event::factory()->create(['organizer_id' => organizerFor(mcpUser())->id, 'status' => '0']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    $response = EiServer::actingAs($moderator)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertOk();
});

test('get-event exposes hiddenLocation so a client can see what clears secret_location_explained', function () {
    $user = mcpUser();
    $event = Event::factory()->create(['organizer_id' => organizerFor($user)->id, 'status' => '0']);
    $event->location()->create(['hiddenLocationToggle' => true, 'hiddenLocation' => null]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    // The flag is unmet and the field that satisfies it must be visible in the
    // same payload — without it clients guessed at remote_description instead.
    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()
        ->assertSee('secret_location_explained')
        ->assertSee('hiddenLocation');
});

test('whoami tells a moderator they can use any organizer id', function () {
    $moderator = mcpUser('m');
    organizerFor($moderator);

    // is_moderator alone did not tell clients that the membership check in
    // create-event-draft exempts them, so they stopped at an unclaimable org.
    EiServer::actingAs($moderator)->tool(Whoami::class)
        ->assertOk()
        ->assertSee('NOT limited to the organizers listed here');

    EiServer::actingAs(mcpUser())->tool(Whoami::class)
        ->assertOk()
        ->assertDontSee('NOT limited to the organizers listed here');
});
