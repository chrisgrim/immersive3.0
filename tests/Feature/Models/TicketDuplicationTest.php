<?php

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A tier name identifies a tier. Nothing enforced that: the only index on
 * tickets was a NON-unique (ticket_type, ticket_id) that didn't include name,
 * and handleTickets is read-then-write — it reads which shows are missing a
 * tier, then writes them. Two saves landing together both read "missing" and
 * both wrote, which put 148 duplicate groups into production, 147 of them
 * created within the same second.
 */
function showWithEvent(): Show
{
    $event = Event::factory()->published()->create();

    return Show::factory()->create(['event_id' => $event->id]);
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
    // handleTickets reads which shows are missing a tier, then writes them.
    // Two saves landing together both read "missing" and both write — the race
    // that put 148 duplicate rows into production.
    //
    // A single-process test can't interleave two real requests, so this stages
    // the losing side directly: the write half of handleTickets, carrying a row
    // the read said was missing but which now exists because the other request
    // committed first. With a plain insert() that throws on the unique index;
    // the upsert() collapses it onto the existing row.
    $show = showWithEvent();

    saveTiers($show->event, [['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => 'winner']]);

    $losingWrite = [[
        'ticket_type' => Show::class,
        'ticket_id' => $show->id,
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

    $tickets = Ticket::where('ticket_type', Show::class)->where('ticket_id', $show->id)->get();

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
        'ticket_type' => Show::class, 'ticket_id' => $show->id, 'name' => 'General',
        'description' => '', 'currency' => 'USD', 'ticket_price' => 40,
        'created_at' => now(), 'updated_at' => now(),
    ]]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

test('two sequential saves of the same tier keep one row', function () {
    $show = showWithEvent();
    $event = $show->event;

    saveTiers($event, [['name' => 'General', 'ticket_price' => 42, 'currency' => 'USD', 'description' => '']]);
    saveTiers($event, [['name' => 'General', 'ticket_price' => 40, 'currency' => 'USD', 'description' => '']]);

    $tickets = Ticket::where('ticket_type', Show::class)->where('ticket_id', $show->id)->get();

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

    $tickets = Ticket::where('ticket_type', Show::class)->where('ticket_id', $show->id)->get();

    expect($tickets)->toHaveCount(1);
    expect((float) $tickets->first()->ticket_price)->toBe(40.0);
});

test('distinct tier names on one show are unaffected', function () {
    $show = showWithEvent();

    saveTiers($show->event, [
        ['name' => 'Adult', 'ticket_price' => 47, 'currency' => 'USD', 'description' => ''],
        ['name' => 'Child', 'ticket_price' => 18, 'currency' => 'USD', 'description' => ''],
    ]);

    expect(Ticket::where('ticket_id', $show->id)->count())->toBe(2);
});

test('the same tier name on two different shows is still allowed', function () {
    // The constraint is per show, not per name — every show of an event
    // carries its own copy of the tier set.
    $event = Event::factory()->published()->create();
    Show::factory()->count(2)->create(['event_id' => $event->id]);

    saveTiers($event, [['name' => 'General', 'ticket_price' => 25, 'currency' => 'USD', 'description' => '']]);

    expect(Ticket::where('name', 'General')->count())->toBe(2);
});

test('re-saving an existing tier updates it rather than adding another', function () {
    $show = showWithEvent();
    $event = $show->event;

    saveTiers($event, [['name' => 'General', 'ticket_price' => 25, 'currency' => 'USD', 'description' => 'first']]);
    saveTiers($event, [['name' => 'General', 'ticket_price' => 30, 'currency' => 'USD', 'description' => 'second']]);

    $tickets = Ticket::where('ticket_id', $show->id)->get();

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

    preg_match('/self::upsert\(\s*\$chunk->values\(\)->all\(\),\s*\[(.*?)\],/s', $ticketSource, $upsertMatch);
    expect($upsertMatch)->not->toBeEmpty('upsert match columns not found — was handleTickets rewritten?');

    $columns = function (string $raw) {
        preg_match_all("/'([^']+)'/", $raw, $m);

        return $m[1];
    };

    expect($columns($upsertMatch[1]))->toBe($columns($indexMatch[1]));
});

test('adding dates to an event whose tiers were duplicated does not spread the duplicates', function () {
    // Show::copyTicketsToShows() reads tiers off an existing show and copies
    // them to each new date. When that source show carried duplicate names —
    // as 148 shows in production did — every date added afterwards inherited
    // them, which is how a single bad save propagated across a schedule.
    $event = Event::factory()->published()->create();
    $source = Show::factory()->create(['event_id' => $event->id]);

    $duplicated = collect([
        (object) ['name' => 'General', 'description' => '', 'currency' => 'USD', 'ticket_price' => 42, 'type' => 's'],
        (object) ['name' => 'General', 'description' => '', 'currency' => 'USD', 'ticket_price' => 40, 'type' => 's'],
    ]);

    $newShow = Show::factory()->create(['event_id' => $event->id]);

    $copy = new ReflectionMethod(Show::class, 'copyTicketsToShows');
    $copy->setAccessible(true);
    $copy->invoke(null, $duplicated, collect([$newShow->id]));

    expect(Ticket::where('ticket_id', $newShow->id)->count())->toBe(1);
    expect($source->tickets()->count())->toBe(0);
});

test('copying tiers onto a show that already has them updates rather than erroring', function () {
    // The concurrent case: two saves adding dates each build rows for shows the
    // other just created. With a plain insert this hits the unique constraint
    // and 500s; the upsert collapses it.
    $event = Event::factory()->published()->create();
    $show = Show::factory()->create(['event_id' => $event->id]);

    $tiers = collect([(object) ['name' => 'General', 'description' => 'first', 'currency' => 'USD', 'ticket_price' => 42, 'type' => 's']]);

    $copy = new ReflectionMethod(Show::class, 'copyTicketsToShows');
    $copy->setAccessible(true);
    $copy->invoke(null, $tiers, collect([$show->id]));

    $again = collect([(object) ['name' => 'General', 'description' => 'second', 'currency' => 'USD', 'ticket_price' => 40, 'type' => 's']]);
    $copy->invoke(null, $again, collect([$show->id]));

    $tickets = Ticket::where('ticket_id', $show->id)->get();
    expect($tickets)->toHaveCount(1);
    expect($tickets->first()->description)->toBe('second');
});
