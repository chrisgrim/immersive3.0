<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A tier name identifies a tier. Nothing enforced that: the only index on
 * tickets was a NON-unique (ticket_type, ticket_id) that didn't include name,
 * and the old per-show write was read-then-write — it read which shows were
 * missing a tier, then wrote them. Two saves landing together both read
 * "missing" and both wrote, which put 148 duplicate groups into production,
 * 147 of them created within the same second. The event-level write is
 * read-then-write too, so the same constraint and upsert still guard it.
 */
function showWithEvent(): Show
{
    $event = Event::factory()->published()->create();

    return Show::factory()->create(['event_id' => $event->id]);
}

function eventTiers(Event $event)
{
    return Ticket::where('ticket_type', Event::class)->where('ticket_id', $event->id)->get();
}

function saveTiers(Event $event, array $tiers): void
{
    Ticket::handleTickets(new Request(['tickets' => $tiers]), $event);
}

test('the database refuses a second tier with the same name on one show', function () {
    $show = showWithEvent();
    $row = fn (string $name) => [
        'ticket_type' => Show::class, 'ticket_id' => $show->id, 'name' => $name,
        'description' => '', 'currency' => 'USD', 'ticket_price' => 10,
        'created_at' => now(), 'updated_at' => now(),
    ];

    DB::table('tickets')->insert($row('General'));

    expect(fn () => DB::table('tickets')->insert($row('General')))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('the losing side of a concurrent save updates the winners row instead of erroring', function () {
    // handleTickets reads which tiers the event already has, then writes the
    // rest. Two saves landing together both read "missing" and both write —
    // the race that put 148 duplicate rows into production.
    //
    // A single-process test can't interleave two real requests, so this stages
    // the losing side directly: the write half of handleTickets, carrying a row
    // the read said was missing but which now exists because the other request
    // committed first. With a plain insert() that throws on the unique index;
    // the upsert() collapses it onto the existing row.
    $show = showWithEvent();

    saveTiers($show->event, [['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => 'winner']]);

    $losingWrite = [[
        'ticket_type' => Event::class,
        'ticket_id' => $show->event_id,
        'name' => 'General',
        'description' => 'loser',
        'currency' => 'USD',
        'ticket_price' => 40,
        'created_at' => now(),
        'updated_at' => now(),
    ]];

    // Exactly the call handleTickets makes, with the same match and update
    // columns — if those drift from the unique index, this is what breaks.
    Ticket::upsert($losingWrite, ['ticket_type', 'ticket_id', 'name'], ['description', 'currency', 'ticket_price', 'updated_at']);

    $tickets = eventTiers($show->event);

    expect($tickets)->toHaveCount(1);
    expect((float) $tickets->first()->ticket_price)->toBe(40.0);
});

test('a plain insert on that same write would have duplicated, which is what upsert prevents', function () {
    // Pins the reason the call above must stay an upsert: the identical row
    // through insert() hits the constraint instead of collapsing. Before the
    // constraint existed it silently made a second row.
    $show = showWithEvent();
    saveTiers($show->event, [['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => '']]);

    expect(fn () => Ticket::insert([[
        'ticket_type' => Event::class, 'ticket_id' => $show->event_id, 'name' => 'General',
        'description' => '', 'currency' => 'USD', 'ticket_price' => 40,
        'created_at' => now(), 'updated_at' => now(),
    ]]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('two sequential saves of the same tier keep one row', function () {
    $show = showWithEvent();
    $event = $show->event;

    saveTiers($event, [['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => '']]);
    saveTiers($event, [['name' => 'General', 'ticket_price' => 40, 'currency' => 'USD', 'description' => '']]);

    $tickets = eventTiers($show->event);

    expect($tickets)->toHaveCount(1);
    expect((float) $tickets->first()->ticket_price)->toBe(40.0);
});

test('a payload carrying the same tier name twice keeps only one', function () {
    // The within-one-request half, which keyBy('name') already handled — kept
    // here so the two halves can't regress independently.
    $show = showWithEvent();

    saveTiers($show->event, [
        ['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => ''],
        ['name' => 'General', 'ticket_price' => 40, 'currency' => 'USD', 'description' => ''],
    ]);

    $tickets = eventTiers($show->event);

    expect($tickets)->toHaveCount(1);
    expect((float) $tickets->first()->ticket_price)->toBe(40.0);
});

test('distinct tier names on one event are unaffected', function () {
    $show = showWithEvent();

    saveTiers($show->event, [
        ['name' => 'Adult', 'ticket_price' => 47, 'currency' => 'USD', 'description' => ''],
        ['name' => 'Child', 'ticket_price' => 18, 'currency' => 'USD', 'description' => ''],
    ]);

    expect(eventTiers($show->event))->toHaveCount(2);
});

test('the same tier name on two different events is still allowed', function () {
    // The constraint is per owner, not per name.
    $first = Event::factory()->published()->create();
    $second = Event::factory()->published()->create();

    saveTiers($first, [['name' => 'General', 'ticket_price' => 25, 'currency' => 'USD', 'description' => '']]);
    saveTiers($second, [['name' => 'General', 'ticket_price' => 25, 'currency' => 'USD', 'description' => '']]);

    expect(Ticket::where('ticket_type', Event::class)->where('name', 'General')->count())->toBe(2);
});

test('re-saving an existing tier updates it rather than adding another', function () {
    $show = showWithEvent();
    $event = $show->event;

    saveTiers($event, [['name' => 'General', 'ticket_price' => 25, 'currency' => 'USD', 'description' => 'first']]);
    saveTiers($event, [['name' => 'General', 'ticket_price' => 30, 'currency' => 'USD', 'description' => 'second']]);

    $tickets = eventTiers($show->event);

    expect($tickets)->toHaveCount(1);
    expect($tickets->first()->description)->toBe('second');
    expect((float) $tickets->first()->ticket_price)->toBe(30.0);
});

test('the upsert matches on exactly the columns the unique index covers', function () {
    // The test above calls upsert() with the columns written out, so it stays
    // green even if the production call drifts to a different set — and a
    // mismatch there is silent: the upsert simply stops recognising conflicts
    // and starts throwing on the constraint instead of collapsing. Neither the
    // migration nor Ticket.php can import the other's list, so they are
    // compared here, the same way the currency catalog is.
    $ticketSource = file_get_contents(app_path('Models/Events/Ticket.php'));
    $migration = file_get_contents(
        database_path('migrations/2026_08_26_000000_add_unique_index_to_tickets_table.php')
    );

    preg_match("/->unique\(\[(.*?)\], 'tickets_owner_name_unique'\)/s", $migration, $indexMatch);
    expect($indexMatch)->not->toBeEmpty('unique index definition not found — was the migration renamed?');

    preg_match('/self::upsert\(.*?->values\(\)->all\(\),\s*\[(.*?)\],/s', $ticketSource, $upsertMatch);
    expect($upsertMatch)->not->toBeEmpty('upsert match columns not found — was handleTickets rewritten?');

    $columns = function (string $raw) {
        preg_match_all("/'([^']+)'/", $raw, $m);

        return $m[1];
    };

    expect($columns($upsertMatch[1]))->toBe($columns($indexMatch[1]));
});
