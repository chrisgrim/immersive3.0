<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Step 1 of storing tiers once per event instead of once per show: every save
 * writes the event's own set alongside the per-show copies, new shows copy
 * from the event's set, and ei:backfill-event-tickets fills the set for
 * events saved before this shipped. Readers still use the per-show copies.
 */
beforeEach(function () {
    $this->event = Event::factory()->create([
        'organizer_id' => Organizer::factory()->create()->id,
        'user_id' => User::factory()->create()->id,
        'showtype' => 's',
    ]);
});

function eventTierShows(Event $event, int $count): void
{
    foreach (range(1, $count) as $i) {
        Show::factory()->create([
            'event_id' => $event->id,
            'date' => now()->addDays($i)->format('Y-m-d 12:00:00'),
        ]);
    }
}

function eventTierRequest(array $tickets): Request
{
    return Request::create('/', 'POST', ['tickets' => $tickets]);
}

function eventTier(string $name, float $price, string $currency = 'USD'): array
{
    return ['name' => $name, 'ticket_price' => $price, 'currency' => $currency, 'description' => $name.' entry'];
}

function eventLevelNames(Event $event): array
{
    return $event->tickets()->orderBy('name')->pluck('name')->all();
}

test('handleTickets writes the tiers onto the event as well as every show', function () {
    eventTierShows($this->event, 3);

    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25), eventTier('VIP', 80)]), $this->event);

    $tiers = $this->event->tickets()->get()->keyBy('name');
    expect($tiers)->toHaveCount(2)
        ->and($tiers['GA']->ticket_price)->toEqual(25)
        ->and($tiers['VIP']->currency)->toBe('USD')
        ->and(Ticket::where('ticket_type', Show::class)->count())->toBe(6);
});

test('handleTickets writes the event tiers even before the event has any dates', function () {
    Ticket::handleTickets(eventTierRequest([eventTier('GA', 15)]), $this->event);

    expect(eventLevelNames($this->event))->toBe(['GA'])
        ->and(Ticket::where('ticket_type', Show::class)->count())->toBe(0);
});

test('handleTickets updates and removes event tiers to match the submission', function () {
    eventTierShows($this->event, 2);
    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25), eventTier('VIP', 80)]), $this->event);

    Ticket::handleTickets(eventTierRequest([eventTier('GA', 30)]), $this->event);

    $tiers = $this->event->tickets()->get();
    expect($tiers)->toHaveCount(1)
        ->and($tiers->first()->ticket_price)->toEqual(30);

    // An explicit empty list removes every tier, on the event and the shows.
    Ticket::handleTickets(eventTierRequest([]), $this->event);

    expect($this->event->tickets()->count())->toBe(0)
        ->and(Ticket::where('ticket_type', Show::class)->count())->toBe(0);
});

test('a malformed request leaves the event tiers alone', function () {
    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25)]), $this->event);

    Ticket::handleTickets(Request::create('/', 'POST'), $this->event);

    expect(eventLevelNames($this->event))->toBe(['GA']);
});

test('saving one event never touches another event tiers', function () {
    $other = Event::factory()->create(['organizer_id' => $this->event->organizer_id]);
    eventTierShows($other, 1);
    Ticket::handleTickets(eventTierRequest([eventTier('Other', 5)]), $other);

    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25)]), $this->event);
    Ticket::handleTickets(eventTierRequest([]), $this->event);

    expect(eventLevelNames($other))->toBe(['Other'])
        ->and(Ticket::where('ticket_type', Show::class)->count())->toBe(1);
});

test('new shows copy their tiers from the event set', function () {
    // Tiers saved before any dates exist live only on the event.
    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25)]), $this->event);

    $request = Request::create('/', 'POST', [
        'showtype' => 's',
        'dateArray' => [now()->addDays(3)->format('Y-m-d 00:00:00'), now()->addDays(4)->format('Y-m-d 00:00:00')],
    ]);
    Show::saveShows($request, $this->event->fresh(), 's');

    $shows = $this->event->fresh()->shows;
    expect($shows)->toHaveCount(2);
    $shows->each(fn ($show) => expect($show->tickets()->pluck('name')->all())->toBe(['GA']));
});

test('new shows copy the event set even when an older show copy differs', function () {
    eventTierShows($this->event, 1);
    Ticket::handleTickets(eventTierRequest([eventTier('GA', 25)]), $this->event);
    // A show copy that drifted (only possible for data written before the
    // event set existed) must not be what new dates inherit.
    Ticket::where('ticket_type', Show::class)->update(['name' => 'Stale']);

    $request = Request::create('/', 'POST', [
        'showtype' => 's',
        'dateArray' => [now()->addDays(1)->format('Y-m-d 00:00:00'), now()->addDays(7)->format('Y-m-d 00:00:00')],
    ]);
    Show::saveShows($request, $this->event->fresh(), 's');

    $newShow = $this->event->fresh()->shows->first();
    expect($newShow->tickets()->pluck('name')->all())->toBe(['GA']);
});

test('a tier new to the event set takes the legacy type of its show copy', function () {
    eventTierShows($this->event, 2);
    Ticket::handleTickets(eventTierRequest([eventTier('Donation', 0)]), $this->event);
    // Old rows carry 'p' (pay what you can); the editor never writes it.
    Ticket::where('ticket_type', Show::class)->update(['type' => 'p']);
    Ticket::where('ticket_type', Event::class)->delete();

    Ticket::handleTickets(eventTierRequest([eventTier('Donation', 0)]), $this->event);

    expect($this->event->tickets()->value('type'))->toBe('p');
});

test('renaming a tier only by case keeps the event row, like the show rows', function () {
    eventTierShows($this->event, 1);
    Ticket::handleTickets(eventTierRequest([eventTier('ga', 25)]), $this->event);
    $id = $this->event->tickets()->value('id');

    Ticket::handleTickets(eventTierRequest([eventTier('GA', 30)]), $this->event);

    expect($this->event->tickets()->pluck('id')->all())->toBe([$id])
        ->and($this->event->tickets()->value('ticket_price'))->toEqual(30);
});

// ----- ei:backfill-event-tickets -----

function perShowTiers(Event $event, array $tiersByDayOffset): void
{
    foreach ($tiersByDayOffset as $offset => $tiers) {
        $show = Show::factory()->create([
            'event_id' => $event->id,
            'date' => now()->addDays($offset)->format('Y-m-d 12:00:00'),
        ]);
        foreach ($tiers as [$name, $price]) {
            $show->tickets()->create(['name' => $name, 'ticket_price' => $price, 'currency' => 'USD', 'description' => '']);
        }
    }
}

test('the dry run reports but writes nothing', function () {
    perShowTiers($this->event, [1 => [['GA', 25]], 2 => [['GA', 25]]]);

    $this->artisan('ei:backfill-event-tickets')->assertSuccessful();

    expect($this->event->tickets()->count())->toBe(0);
});

test('--apply copies the tiers of the latest show onto the event, once', function () {
    perShowTiers($this->event, [1 => [['GA', 25], ['VIP', 80]], 2 => [['GA', 25], ['VIP', 80]]]);

    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();
    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();

    expect(eventLevelNames($this->event))->toBe(['GA', 'VIP'])
        // The per-show copies are left in place.
        ->and(Ticket::where('ticket_type', Show::class)->count())->toBe(4);
});

test('--apply copies the latest show and reports shows that differ', function () {
    perShowTiers($this->event, [1 => [['Early', 10]], 5 => [['GA', 25]]]);

    $this->artisan('ei:backfill-event-tickets --apply')
        ->expectsOutputToContain('Events whose shows hold different tier sets (first 50): '.$this->event->id)
        ->assertSuccessful();

    expect(eventLevelNames($this->event))->toBe(['GA']);
});

test('--apply leaves an event alone when its latest show has no tiers', function () {
    // saveShows used to copy the latest show's (empty) set onto new dates, so
    // the event must keep falling back to that rather than an older show's.
    perShowTiers($this->event, [1 => [['GA', 25]], 9 => []]);

    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();

    expect($this->event->tickets()->count())->toBe(0);
});

test('--apply keeps the legacy type and the original created_at', function () {
    perShowTiers($this->event, [1 => [['Pay what you like', 0]]]);
    Ticket::where('ticket_type', Show::class)->update(['type' => 'p', 'created_at' => '2024-01-02 03:04:05']);

    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();

    $tier = $this->event->tickets()->first();
    expect($tier->type)->toBe('p')
        ->and($tier->created_at->format('Y-m-d H:i:s'))->toBe('2024-01-02 03:04:05');
});

test('--apply skips an event whose tiers were already saved at event level', function () {
    perShowTiers($this->event, [1 => [['Old', 10]]]);
    Ticket::create(['ticket_type' => Event::class, 'ticket_id' => $this->event->id, 'name' => 'New', 'ticket_price' => 20, 'currency' => 'USD']);

    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();

    expect(eventLevelNames($this->event))->toBe(['New']);
});

test('--apply includes soft-deleted and draft events', function () {
    $this->event->update(['status' => 'd']);
    perShowTiers($this->event, [1 => [['GA', 25]]]);
    $this->event->delete();

    $this->artisan('ei:backfill-event-tickets --apply')->assertSuccessful();

    expect(Ticket::where('ticket_type', Event::class)->where('ticket_id', $this->event->id)->pluck('name')->all())->toBe(['GA']);
});
