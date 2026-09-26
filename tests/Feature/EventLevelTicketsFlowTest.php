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
 * End-to-end guard for step 1 of storing tiers once per event: whatever a
 * host does through the web wizard or the MCP tools, the event's own tier
 * set and every show's copy must say exactly the same thing. Step 2 switches
 * the readers to the event set, so any drift here would become a visible
 * change then.
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

/** One comparable line per tier. */
function flowTierSet($tickets): array
{
    return collect($tickets)
        ->map(fn ($t) => json_encode([$t->name, (float) $t->ticket_price, $t->currency, (string) $t->description, $t->type]))
        ->sort()->values()->all();
}

/** The event set equals every show's copy, and holds exactly $names. */
function expectTierParity(Event $event, array $names): void
{
    $event = Event::withoutGlobalScopes()->find($event->id);
    $eventSet = flowTierSet($event->tickets()->get());

    expect($event->tickets()->orderBy('name')->pluck('name')->all())->toBe($names);

    $shows = Show::withoutGlobalScopes()->where('event_id', $event->id)->get();
    foreach ($shows as $show) {
        expect(flowTierSet($show->tickets()->get()))->toBe($eventSet);
    }
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

    // More dates later: the new ones get the same tiers.
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

test('web: an event saved before this shipped (show copies only) catches up on its next save', function () {
    $user = flowUser();
    $event = flowEvent($user);
    $save = fn (array $data) => $this->actingAs($user)->postJson("/api/hosting/event/{$event->slug}", $data)->assertOk();

    $save(['timezone' => 'America/New_York', 'showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6)], 'tickets' => [flowTier('GA', 20)]]);
    // Simulate the old code: no event-level rows, only show copies.
    Ticket::where('ticket_type', Event::class)->delete();

    // Adding a date still copies from a show, exactly as before.
    $save(['showtype' => 's', 'dateArray' => [flowDay(5), flowDay(6), flowDay(9)]]);
    Show::withoutGlobalScopes()->where('event_id', $event->id)->get()
        ->each(fn ($s) => expect($s->tickets()->pluck('name')->all())->toBe(['GA']));

    // The next tickets save writes the event set too.
    $save(['tickets' => [flowTier('GA', 22)]]);
    expectTierParity($event, ['GA']);
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
