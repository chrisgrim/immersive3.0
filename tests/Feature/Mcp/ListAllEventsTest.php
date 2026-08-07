<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\ListAllEvents;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

function catalogUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

/** An event under an organizer nobody in the test belongs to. */
function strangerEvent(array $overrides = [], ?Organizer $organizer = null): Event
{
    $owner = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $organizer ??= Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    return Event::factory()->create(array_merge([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'status' => 'p',
        'published_at' => now(),
    ], $overrides));
}

// ── the gap this tool exists to close ──────────────────────────────────

test('a moderator sees events under organizers they do not belong to', function () {
    strangerEvent(['name' => 'Imaginarium Utopia']);
    strangerEvent(['name' => 'Universal Horror Unleashed']);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class)
        ->assertOk()
        ->assertSee(['Imaginarium Utopia', 'Universal Horror Unleashed'])
        ->assertSee('not limited to your own organizers');
});

test('an admin sees every status, including other people\'s drafts', function () {
    strangerEvent(['name' => 'Someone Elses Draft', 'status' => '3', 'published_at' => null]);
    strangerEvent(['name' => 'Someone Elses Review', 'status' => 'r', 'published_at' => null]);

    EiServer::actingAs(catalogUser('a'))->tool(ListAllEvents::class)
        ->assertOk()
        ->assertSee(['Someone Elses Draft', 'Someone Elses Review']);
});

test('a regular user only sees the public catalog, never unpublished events', function () {
    strangerEvent(['name' => 'Public Listing']);
    strangerEvent(['name' => 'Private Draft', 'status' => '3', 'published_at' => null]);
    strangerEvent(['name' => 'Queued For Review', 'status' => 'r', 'published_at' => null]);

    EiServer::actingAs(catalogUser())->tool(ListAllEvents::class)
        ->assertOk()
        ->assertSee('Public Listing')
        ->assertDontSee(['Private Draft', 'Queued For Review']);
});

test('a regular user cannot widen the scope with the status filter', function () {
    strangerEvent(['name' => 'Private Draft', 'status' => '3', 'published_at' => null]);

    EiServer::actingAs(catalogUser())->tool(ListAllEvents::class, ['status' => 'draft'])
        ->assertOk()
        ->assertDontSee('Private Draft');
});

// ── filters ────────────────────────────────────────────────────────────

test('search matches on name', function () {
    strangerEvent(['name' => 'The Matrix Experience']);
    strangerEvent(['name' => 'Every Brilliant Thing']);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['search' => 'matrix'])
        ->assertOk()
        ->assertSee('The Matrix Experience')
        ->assertDontSee('Every Brilliant Thing');
});

test('LIKE wildcards in the search term are matched literally', function () {
    strangerEvent(['name' => '100% Immersive']);
    strangerEvent(['name' => '1000 Doors']);

    // Unescaped, "100%" would also drag in "1000 Doors".
    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['search' => '100%'])
        ->assertOk()
        ->assertSee('100% Immersive')
        ->assertDontSee('1000 Doors');
});

test('the organizer filter accepts an id or a name fragment', function () {
    $owner = catalogUser();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p', 'name' => 'Meow Wolf']);
    strangerEvent(['name' => 'Omega Mart'], $organizer);
    strangerEvent(['name' => 'Unrelated Show']);

    $moderator = catalogUser('m');

    EiServer::actingAs($moderator)->tool(ListAllEvents::class, ['organizer' => (string) $organizer->id])
        ->assertOk()->assertSee('Omega Mart')->assertDontSee('Unrelated Show');

    EiServer::actingAs($moderator)->tool(ListAllEvents::class, ['organizer' => 'meow'])
        ->assertOk()->assertSee('Omega Mart')->assertDontSee('Unrelated Show');
});

test('closing_before finds the runs that are about to expire', function () {
    strangerEvent(['name' => 'Expiring Soon', 'closingDate' => now()->addDays(10)]);
    strangerEvent(['name' => 'Runs For Ages', 'closingDate' => now()->addYear()]);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, [
        'closing_before' => now()->addDays(30)->format('Y-m-d H:i:s'),
    ])->assertOk()->assertSee('Expiring Soon')->assertDontSee('Runs For Ages');
});

test('closing_before and closing_after bound a window', function () {
    strangerEvent(['name' => 'Already Closed', 'closingDate' => now()->subMonth()]);
    strangerEvent(['name' => 'In The Window', 'closingDate' => now()->addDays(10)]);
    strangerEvent(['name' => 'Far Future', 'closingDate' => now()->addYear()]);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, [
        'closing_after' => now()->format('Y-m-d H:i:s'),
        'closing_before' => now()->addDays(30)->format('Y-m-d H:i:s'),
    ])->assertOk()->assertSee('In The Window')->assertDontSee(['Already Closed', 'Far Future']);
});

test('the draft status group covers the wizard step markers, not just d', function () {
    strangerEvent(['name' => 'Marker Draft', 'status' => 'B', 'published_at' => null]);
    strangerEvent(['name' => 'Plain Draft', 'status' => 'd', 'published_at' => null]);
    strangerEvent(['name' => 'Live Listing']);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['status' => 'draft'])
        ->assertOk()
        ->assertSee(['Marker Draft', 'Plain Draft'])
        ->assertDontSee('Live Listing');
});

test('the live status group covers published and embargoed', function () {
    strangerEvent(['name' => 'Published One']);
    strangerEvent(['name' => 'Embargoed One', 'status' => 'e', 'embargo_date' => now()->addMonth()]);
    strangerEvent(['name' => 'Rejected One', 'status' => 'n', 'published_at' => null]);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['status' => 'live'])
        ->assertOk()
        ->assertSee(['Published One', 'Embargoed One'])
        ->assertDontSee('Rejected One');
});

test('archived events are excluded unless asked for', function () {
    strangerEvent(['name' => 'Shelved Show', 'archived' => true]);

    $moderator = catalogUser('m');

    EiServer::actingAs($moderator)->tool(ListAllEvents::class)
        ->assertOk()->assertDontSee('Shelved Show');

    EiServer::actingAs($moderator)->tool(ListAllEvents::class, ['include_archived' => true])
        ->assertOk()->assertSee('Shelved Show');
});

test('soft-deleted events never appear', function () {
    $event = strangerEvent(['name' => 'Deleted Show']);
    $event->delete();

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class)
        ->assertOk()->assertDontSee('Deleted Show');
});

// ── paging and ordering ────────────────────────────────────────────────

test('results page with limit/offset and report where to continue', function () {
    foreach (range(1, 5) as $i) {
        strangerEvent(['name' => "Paged Show {$i}", 'closingDate' => now()->addDays($i)]);
    }

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['limit' => 2])
        ->assertOk()
        ->assertSee(['"total_matching":5', '"returned":2', '"next_offset":2'])
        ->assertSee(['Paged Show 1', 'Paged Show 2'])
        ->assertDontSee('Paged Show 3');

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['limit' => 2, 'offset' => 4])
        ->assertOk()
        ->assertSee(['"returned":1', '"next_offset":null'])
        ->assertSee('Paged Show 5');
});

test('closing_date sort puts the soonest first and undated events last', function () {
    strangerEvent(['name' => 'No Closing Date', 'closingDate' => null]);
    strangerEvent(['name' => 'Closes Later', 'closingDate' => now()->addMonths(6)]);
    strangerEvent(['name' => 'Closes First', 'closingDate' => now()->addDay()]);

    $response = EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class);
    $response->assertOk();

    $ref = new ReflectionObject($response);
    $prop = $ref->getProperty('response');
    $prop->setAccessible(true);
    $text = json_encode($prop->getValue($response)->toArray());

    expect(strpos($text, 'Closes First'))
        ->toBeLessThan(strpos($text, 'Closes Later'))
        ->and(strpos($text, 'Closes Later'))
        ->toBeLessThan(strpos($text, 'No Closing Date'));
});

// ── payload ────────────────────────────────────────────────────────────

test('each row carries what an expiry sweep needs to triage without a second call', function () {
    $event = strangerEvent(['name' => 'Triage Me', 'showtype' => 'o', 'closingDate' => now()->addDays(3)]);
    $event->shows()->create(['date' => now()->addDay()->format('Y-m-d H:i:s')]);
    $event->shows()->create(['date' => now()->addDays(2)->format('Y-m-d H:i:s')]);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['search' => 'Triage Me'])
        ->assertOk()
        ->assertSee(['Triage Me', $event->slug, '"shows":2', 'ongoing/recurring', '"status_label":"published"']);
});

test('a retired limited-type event is labelled so the client knows to convert it', function () {
    strangerEvent(['name' => 'Legacy Limited', 'showtype' => 'l']);

    EiServer::actingAs(catalogUser('m'))->tool(ListAllEvents::class, ['search' => 'Legacy Limited'])
        ->assertOk()
        ->assertSee('retired type');
});
