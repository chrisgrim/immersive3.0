<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Organizer;
use App\Models\User;

/**
 * End-to-end guard for storing tiers once per event: whatever a host does
 * through the web wizard or the MCP tools, the event's own tier set holds
 * exactly the tiers they saved and no show gets a copy.
 */
function flowUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function flowEvent(User $user): Event
{
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'status' => '0',
    ]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

function flowDay(int $days): string
{
    return now('America/New_York')->addDays($days)->format('Y-m-d 00:00:00');
}

function flowTier(string $name, float $price, string $description = ''): array
{
    return ['name' => $name, 'ticket_price' => $price, 'currency' => 'USD', 'description' => $description];
}

/** The event set holds exactly $names, and none of its shows has a copy. */
function expectTierParity(Event $event, array $names): void
{
    $event = Event::withoutGlobalScopes()->find($event->id);

    expect($event->tickets()->reorder('name')->pluck('name')->all())->toBe($names);

    $showIds = Show::withoutGlobalScopes()->where('event_id', $event->id)->pluck('id');
    expect(Ticket::where('ticket_type', Show::class)->whereIn('ticket_id', $showIds)->count())->toBe(0);
}

// ----- web wizard (POST /api/hosting/event/{slug}, one step at a time) -----

test('web: a new event saved step by step keeps the event set and every date in step', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data)->assertOk();

    // Dates step, then tickets step, as the wizard does.
    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(10), flowDay(11)]]);
    $save(['tickets' => [flowTier('GA', 25), flowTier('VIP', 80, 'Front row')], 'ticketUrl' => 'https://example.com', 'call_to_action' => 'Get Tickets']);
    expectTierParity($event, ['GA', 'VIP']);

    // Edit prices, drop a tier, add one.
    $save(['tickets' => [flowTier('GA', 30), flowTier('Student', 15)]]);
    expectTierParity($event, ['GA', 'Student']);

    // More dates later.
    $save(['showtype' => 's', 'dateArray' => [flowDay(10), flowDay(11), flowDay(12), flowDay(20)]]);
    expectTierParity($event, ['GA', 'Student']);
    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(4);

    // Remove a date.
    $save(['showtype' => 's', 'dateArray' => [flowDay(10), flowDay(20)]]);
    expectTierParity($event, ['GA', 'Student']);
});

test('web: switching to always available and back carries the tiers', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data)->assertOk();

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5)]]);
    $save(['tickets' => [flowTier('GA', 20)]]);

    $save(['showtype' => 'a', 'always_config' => ['endDate' => now()->addMonths(3)->format('Y-m-d 00:00:00')]]);
    expectTierParity($event, ['GA']);

    $save(['showtype' => 's', 'dateArray' => [flowDay(7), flowDay(8)]]);
    expectTierParity($event, ['GA']);
});

test('web: dates and tickets in the same save', function () {
    $user = flowUser();
    $event = flowEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [flowDay(3), flowDay(4), flowDay(5)],
        'tickets' => [flowTier('PWYC', 0), flowTier('VIP', 80)],
    ])->assertOk();

    expectTierParity($event, ['PWYC', 'VIP']);
    expect($event->fresh()->price_range)->toBe('PWYC - $80');
});

test('web: removing every tier empties both', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data);

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5)], 'tickets' => [flowTier('GA', 20)]])->assertOk();
    Ticket::handleTickets(\Illuminate\Http\Request::create('/', 'POST', ['tickets' => []]), $event->fresh());

    expectTierParity($event, []);
    expect(Ticket::where('ticket_type', Show::class)->count())->toBe(0);
});

// ----- MCP tools -----

test('mcp: draft, dates, tickets, weekly run and edits keep everything in step', function () {
    $user = flowUser();
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);

    EiServer::actingAs($user)->tool(CreateEventDraft::class, [
        'organizer_id' => $organizer->id,
        'name' => 'The Tier Parity House',
    ])->assertOk();
    $event = Event::withoutGlobalScopes()->where('organizer_id', $organizer->id)->firstOrFail();
    $update = fn (array $args) => EiServer::actingAs($user)->tool(UpdateEvent::class, ['event_slug' => $event->slug] + $args)->assertOk();

    $tz = 'America/Toronto';
    $noon = fn (int $d) => now($tz)->addDays($d)->setTime(12, 0)->utc()->format('Y-m-d H:i:s');

    $update(['showtype' => 's', 'timezone' => $tz, 'dateArray' => [$noon(10), $noon(11)]]);
    $update(['tickets' => [flowTier('GA', 25), flowTier('VIP', 80)]]);
    expectTierParity($event, ['GA', 'VIP']);

    // Weekly run expanded server side.
    $update([
        'showtype' => 'o',
        'ongoing_config' => ['startDate' => $noon(1), 'endDate' => $noon(60), 'daysOfWeek' => [4, 5, 6]],
        'confirm_schedule_replace' => true,
    ]);
    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBeGreaterThan(20);
    expectTierParity($event, ['GA', 'VIP']);

    $update(['tickets' => [flowTier('GA', 28)]]);
    expectTierParity($event, ['GA']);
});

// ----- step 2: readers use the event set -----

/** An event with dates and tiers, whose show copies have drifted to 'Stale'. */
function flowEventWithDriftedCopies(User $user): Event
{
    $event = flowEvent($user);
    test()->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [flowDay(5), flowDay(6)],
        'tickets' => [flowTier('GA', 25)],
    ])->assertOk();
    // Leftover per-show copies from before tiers moved onto the event, so a
    // reader still on them shows up as 'Stale'.
    Show::withoutGlobalScopes()->where('event_id', $event->id)->get()
        ->each(fn ($show) => $show->tickets()->create(['name' => 'Stale', 'ticket_price' => 1, 'currency' => 'USD', 'description' => '']));

    return $event;
}

test('the public event page reads the event set', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);

    $page = Event::withoutGlobalScopes()->find($event->id);
    expect($page->first_show_tickets->pluck('name')->all())->toBe(['GA']);
});

test('leftover show copies are never read, even when the event set is empty', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);
    Ticket::where('ticket_type', Event::class)->delete();

    $page = Event::withoutGlobalScopes()->find($event->id);
    expect($page->first_show_tickets)->toHaveCount(0)
        ->and($page->currentTickets())->toHaveCount(0);
});

test('the editor gets the event set on load and after every save', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);

    $this->actingAs($user)->get("/hosting/event/{$event->slug}/edit")
        ->assertOk()
        ->assertViewHas('event', fn ($e) => $e->relationLoaded('tickets')
            && $e->toArray()['tickets'][0]['name'] === 'GA'
            // The Dates step and sidebar read the schedule from here; losing
            // it opened the editor with no dates (2026-09-27).
            && count($e->toArray()['shows'] ?? []) === 2);

    // A dates-only save must still send the tiers back, or the wizard's
    // Object.assign would keep stale ones and could re-save them.
    $response = $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6), flowDay(7)],
    ])->assertOk();

    expect(collect($response->json('event.tickets'))->pluck('name')->all())->toBe(['GA']);
    expect($response->json('event.shows'))->toHaveCount(3);
});

test('the admin review screen gets the event set', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);

    $response = $this->actingAs(flowUser('a'))->getJson("/api/admin/events/{$event->slug}")->assertOk();

    expect(collect($response->json('tickets'))->pluck('name')->all())->toBe(['GA']);
    expect($response->json('shows'))->toHaveCount(2);
});

test('mcp get-event returns the event set', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);

    EiServer::actingAs($user)->tool(\App\Mcp\Tools\GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()
        ->assertSee('"name":"GA"', false)
        ->assertDontSee('Stale');
});

test('the real event page shows the event set in its data, JSON-LD and button', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);
    $event->update(['status' => 'p', 'published_at' => now(), 'ticketUrl' => 'https://example.com']);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    // The page data is JSON.parse('...') with quotes written as \u0022.
    expect($html)->toContain('name\u0022:\u0022GA\u0022')
        ->and($html)->not->toContain('Stale')
        ->and($html)->toContain('"lowPrice": "25"');
})->skip(fn () => ! file_exists(public_path('hot')) && ! file_exists(public_path('build/manifest.json')), 'needs built assets');

test('tiers are listed alphabetically, as the show copies always were', function () {
    $user = flowUser();
    $event = flowEvent($user);

    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5)],
        'tickets' => [flowTier('VIP', 80), flowTier('Adult', 30), flowTier('Child', 10)],
    ])->assertOk();
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'tickets' => [flowTier('VIP', 80), flowTier('Adult', 30), flowTier('Child', 10), flowTier('Balcony', 20)],
    ])->assertOk();

    $page = Event::withoutGlobalScopes()->find($event->id);
    expect($page->first_show_tickets->pluck('name')->all())->toBe(['Adult', 'Balcony', 'Child', 'VIP']);
    // MySQL happens to return these in name order via the unique index even
    // without ORDER BY, so also pin the explicit ordering itself.
    expect(strtolower($event->tickets()->toSql()))->toContain('order by `name` asc, `id` asc');
});

test('mcp submit readiness counts the event set', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);
    // With no show copies left, only the event set can say tickets exist.
    Ticket::where('ticket_type', Show::class)->delete();

    // Readiness lists what is still missing; tickets must not be among them.
    EiServer::actingAs($user)->tool(\App\Mcp\Tools\UpdateEvent::class, ['event_slug' => $event->slug, 'tag_line' => 'Still a draft'])
        ->assertOk()
        ->assertSee('"tickets":true', false);
});

test('an accent-only rename keeps the same row', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data)->assertOk();

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6)], 'tickets' => [flowTier('Café', 20)]]);
    $id = $event->tickets()->value('id');

    // The column's collation treats these as the same name, so the row and
    // its name stay as they were.
    $save(['tickets' => [flowTier('cafe', 22)]]);

    expect($event->tickets()->pluck('id')->all())->toBe([$id]);
    expectTierParity($event, ['Café']);
});

test('an event with tiers but no dates offers no tickets on its page', function () {
    $user = flowUser();
    $event = flowEvent($user);
    Ticket::handleTickets(\Illuminate\Http\Request::create('/', 'POST', ['tickets' => [flowTier('GA', 25)]]), $event);

    expect(Event::withoutGlobalScopes()->find($event->id)->first_show_tickets)->toHaveCount(0);
});

test('a tier named only with digits saves and can be kept alone', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data);

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5)], 'tickets' => [flowTier('10', 10), flowTier('GA', 25)]])->assertOk();
    // Keeping only the all-digit tier used to fail the delete with a numeric comparison.
    $save(['tickets' => [flowTier('10', 12)]])->assertOk();

    expectTierParity($event, ['10']);
});

test('the event page reads no show copies when the event has its own set', function () {
    $user = flowUser();
    $event = flowEventWithDriftedCopies($user);
    $event->update(['status' => 'p', 'published_at' => now(), 'ticketUrl' => 'https://example.com']);

    $showTicketQueries = 0;
    \Illuminate\Support\Facades\DB::listen(function ($q) use (&$showTicketQueries) {
        if (str_contains($q->sql, '`tickets`') && in_array(Show::class, $q->bindings, true)) {
            $showTicketQueries++;
        }
    });

    $this->get("/events/{$event->slug}")->assertOk();

    expect($showTicketQueries)->toBe(0);
})->skip(fn () => ! file_exists(public_path('hot')) && ! file_exists(public_path('build/manifest.json')), 'needs built assets');

test('mcp get-event lists no tiers for an event without dates, matching its readiness', function () {
    $user = flowUser();
    $event = flowEvent($user);
    Ticket::handleTickets(\Illuminate\Http\Request::create('/', 'POST', ['tickets' => [flowTier('GA', 25)]]), $event);

    EiServer::actingAs($user)->tool(\App\Mcp\Tools\GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()
        ->assertSee('"tickets":[]', false);
});

test('two look-alike names in one save end with the same tier everywhere', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data)->assertOk();

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6)], 'tickets' => [flowTier('GA', 10)]]);
    // "ga" and "GA" are the same name to the database; the last one wins.
    $save([
        'showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6), flowDay(7)],
        'tickets' => [flowTier('ga', 20, 'lower'), flowTier('GA', 30, 'upper')],
    ]);

    expectTierParity($event, ['GA']);
    expect((float) $event->tickets()->value('ticket_price'))->toBe(30.0)
        ->and($event->fresh()->price_range)->toBe('$30');
});

test('names the database keeps apart are never merged', function () {
    $user = flowUser();
    $event = flowEvent($user);

    // Accent-insensitive, but й and и are distinct letters to the collation.
    $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", [
        'timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5)],
        'tickets' => [flowTier('Билет й', 10), flowTier('Билет и', 20)],
    ])->assertOk();

    expect($event->tickets()->count())->toBe(2);
    expectTierParity($event, ['Билет и', 'Билет й']);
});
