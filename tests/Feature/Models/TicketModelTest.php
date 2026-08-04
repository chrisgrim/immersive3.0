<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ticket::handleTickets writes the event's tier list onto every one of its
 * shows. It used to do that with 3 queries per show, which Sentry flagged as an
 * N+1 (EI-LARAVEL-Q) once recurrence expansion started producing large
 * schedules. These cover both the resulting rows and the query count.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->event = Event::factory()->create([
        'organizer_id' => Organizer::factory()->create()->id,
        'user_id' => $this->user->id,
    ]);
});

function showsFor(Event $event, int $count): void
{
    foreach (range(1, $count) as $i) {
        Show::factory()->create([
            'event_id' => $event->id,
            'date' => now()->addDays($i)->format('Y-m-d H:i:s'),
        ]);
    }
}

function ticketRequest(array $tickets): Request
{
    return Request::create('/', 'POST', ['tickets' => $tickets]);
}

function gaTier(float $price = 25, string $description = 'Standard entry'): array
{
    return ['name' => 'GA', 'ticket_price' => $price, 'currency' => '$', 'description' => $description];
}

test('handleTickets writes every tier onto every show', function () {
    showsFor($this->event, 3);

    Ticket::handleTickets(ticketRequest([
        gaTier(),
        ['name' => 'VIP', 'ticket_price' => 80, 'currency' => '$', 'description' => 'Front row'],
    ]), $this->event);

    $shows = $this->event->fresh()->shows;
    expect($shows)->toHaveCount(3);

    $shows->each(function ($show) {
        $tickets = $show->tickets()->get()->keyBy('name');
        expect($tickets)->toHaveCount(2);
        expect($tickets['GA']->ticket_price)->toEqual(25);
        expect($tickets['GA']->description)->toBe('Standard entry');
        expect($tickets['VIP']->ticket_price)->toEqual(80);
    });
});

test('handleTickets updates existing tiers in place rather than duplicating them', function () {
    showsFor($this->event, 3);

    Ticket::handleTickets(ticketRequest([gaTier()]), $this->event);
    $firstIds = Ticket::where('ticket_type', Show::class)->pluck('id')->sort()->values();

    Ticket::handleTickets(ticketRequest([gaTier(30, 'Now pricier')]), $this->event);

    $tickets = Ticket::where('ticket_type', Show::class)->get();
    expect($tickets)->toHaveCount(3);
    expect($tickets->pluck('id')->sort()->values()->all())->toBe($firstIds->all());
    $tickets->each(function ($ticket) {
        expect($ticket->ticket_price)->toEqual(30);
        expect($ticket->description)->toBe('Now pricier');
    });
});

test('handleTickets deletes tiers the user removed, on every show', function () {
    showsFor($this->event, 3);

    Ticket::handleTickets(ticketRequest([
        gaTier(),
        ['name' => 'VIP', 'ticket_price' => 80, 'currency' => '$', 'description' => 'Front row'],
    ]), $this->event);
    expect(Ticket::where('ticket_type', Show::class)->count())->toBe(6);

    Ticket::handleTickets(ticketRequest([gaTier()]), $this->event);

    $remaining = Ticket::where('ticket_type', Show::class)->get();
    expect($remaining)->toHaveCount(3);
    expect($remaining->pluck('name')->unique()->all())->toBe(['GA']);
});

test('handleTickets adds a tier to shows created after the first save', function () {
    showsFor($this->event, 2);
    Ticket::handleTickets(ticketRequest([gaTier()]), $this->event);

    // A show added later (as recurrence expansion does) has no tickets yet.
    $late = Show::factory()->create([
        'event_id' => $this->event->id,
        'date' => now()->addDays(9)->format('Y-m-d H:i:s'),
    ]);

    Ticket::handleTickets(ticketRequest([gaTier()]), $this->event);

    expect($late->tickets()->where('name', 'GA')->exists())->toBeTrue();
    expect(Ticket::where('ticket_type', Show::class)->count())->toBe(3);
});

test('handleTickets keeps the last of a duplicated tier name without inserting twice', function () {
    showsFor($this->event, 2);

    Ticket::handleTickets(ticketRequest([
        gaTier(25, 'first'),
        gaTier(40, 'second'),
    ]), $this->event);

    $tickets = Ticket::where('ticket_type', Show::class)->get();
    expect($tickets)->toHaveCount(2);
    $tickets->each(function ($ticket) {
        expect($ticket->ticket_price)->toEqual(40);
        expect($ticket->description)->toBe('second');
    });
});

test('handleTickets rebuilds the price ranges and the event price range', function () {
    showsFor($this->event, 2);

    Ticket::handleTickets(ticketRequest([
        gaTier(),
        ['name' => 'VIP', 'ticket_price' => 80, 'currency' => '$', 'description' => 'Front row'],
    ]), $this->event);

    expect($this->event->priceranges()->pluck('price')->all())->toEqualCanonicalizing(['25', '80']);
    expect($this->event->fresh()->price_range)->toBe('$25 - $80');

    // Re-saving replaces the ranges instead of appending to them.
    Ticket::handleTickets(ticketRequest([gaTier(10)]), $this->event);

    expect($this->event->priceranges()->pluck('price')->all())->toEqualCanonicalizing(['10']);
    expect($this->event->fresh()->price_range)->toBe('$10');
});

test('handleTickets query count stays flat as the number of shows grows', function () {
    // The N+1 this replaced ran 3 queries per show, so 40 shows meant ~120
    // ticket queries. The batched version must not scale with the show count.
    showsFor($this->event, 40);
    $tickets = [gaTier(), ['name' => 'VIP', 'ticket_price' => 80, 'currency' => '$', 'description' => 'Front row']];

    DB::enableQueryLog();
    Ticket::handleTickets(ticketRequest($tickets), $this->event);
    $onInsert = count(DB::getQueryLog());

    DB::flushQueryLog();
    Ticket::handleTickets(ticketRequest($tickets), $this->event);
    $onUpdate = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($onInsert)->toBeLessThan(20);
    expect($onUpdate)->toBeLessThan(20);
    expect(Ticket::where('ticket_type', Show::class)->count())->toBe(80);
});
