<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Events\ShowChangeLog;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\Support\FakeSearchEngine;

/**
 * A show's calendar day is the day in the EVENT's timezone — see
 * Show::localDay() / Show::usesCurtainTimes() for the rule and the data
 * behind it. Covers the readers, the noon-local write path, in-place
 * matching by day, the "new dates" notification, and the repair command.
 */

// A fixed far-future evening. US DST ends 2030-11-03, so Oct 31 is CDT:
// 8 PM in Chicago = 01:00 UTC the next day.
const HOUSTON_EVENING_UTC = '2030-11-01 01:00:00';
const HOUSTON_NOON_UTC = '2030-10-31 17:00:00';

/** A published Houston event with the rows the public page needs to render. */
function localDayEvent(array $overrides = []): Event
{
    $event = Event::factory()->published()->create(array_merge([
        'organizer_id' => Organizer::factory()->create(['status' => 'p'])->id,
        'timezone' => 'America/Chicago',
        'showtype' => 's',
        'hasLocation' => true,
    ], $overrides));
    Location::factory()->create(['event_id' => $event->id]);
    $event->priceranges()->create(['price' => '25']);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

function localDayRequest(array $dates, string $showtype = 's', string $tz = 'America/Chicago'): Request
{
    return new Request(['showtype' => $showtype, 'dateArray' => $dates, 'timezone' => $tz]);
}

function localDayModerator(): User
{
    return User::factory()->create(['type' => 'm']);
}

// ============================================================
// Reading
// ============================================================

test('Event::localDate reads a stored UTC instant on the day it plays where the event is', function () {
    $event = localDayEvent();

    expect($event->localDate(HOUSTON_EVENING_UTC))->toBe('2030-10-31');
    expect($event->localDate(HOUSTON_EVENING_UTC, 'F jS, Y'))->toBe('October 31st, 2030');
    expect($event->localDate(null))->toBeNull();
});

test('Event::localDate falls back to UTC for a junk timezone instead of throwing', function () {
    $event = localDayEvent(['timezone' => 'Nowhere/Nothing']);

    expect($event->localDate(HOUSTON_EVENING_UTC))->toBe('2030-11-01');
});

test('the event page names the local day even for a row still stored at curtain time', function () {
    $event = localDayEvent();
    // Straight into the table, bypassing the write-side normalisation: this
    // is what every assistant-created row looked like before the fix.
    Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    FakeSearchEngine::install([]);

    $this->withoutVite()
        ->get('/events/'.$event->slug)
        ->assertOk()
        ->assertSee('October 31st, 2030')
        ->assertDontSee('November 1st, 2030');
});

// ============================================================
// Writing
// ============================================================

test('saveShows stores a dated show at noon of its local day, whatever instant it was given', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();

    // saveShows() writes the rows; updateEvent() derives the schedule
    // metadata (closingDate) from them — the order UpdateEventAction uses.
    $request = localDayRequest([HOUSTON_EVENING_UTC]);
    Show::saveShows($request, $event);
    Show::updateEvent($request, $event);

    $event->refresh();
    expect($event->shows->pluck('date')->map(fn ($d) => (string) $d)->all())->toBe([HOUSTON_NOON_UTC]);
    // The closing date ends the LOCAL last day, not the UTC one.
    expect((string) $event->closingDate)->toBe('2030-10-31 23:59:59');
    // And the change log names the day the organizer picked.
    expect(ShowChangeLog::where('event_id', $event->id)->where('action', 'added')->value('dates'))->toBe(['2030-10-31']);
});

test('the web wizard path is untouched: a noon-local instant is stored as sent', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();

    Show::saveShows(localDayRequest([HOUSTON_NOON_UTC]), $event);

    expect($event->refresh()->shows->first()->date)->toBe(HOUSTON_NOON_UTC);
});

test('re-saving the same day moves an old curtain-time row in place, keeping its id', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();
    $row = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);

    Show::saveShows(localDayRequest([HOUSTON_NOON_UTC]), $event);

    $shows = $event->refresh()->shows;
    expect($shows)->toHaveCount(1);
    expect($shows->first()->id)->toBe($row->id);
    expect($shows->first()->date)->toBe(HOUSTON_NOON_UTC);
    // Same day before and after: nothing to log.
    expect(ShowChangeLog::where('event_id', $event->id)->count())->toBe(0);
});

test('an unchanged schedule sent at different times writes no rows at all', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();
    Show::saveShows(localDayRequest([HOUSTON_NOON_UTC, '2030-11-02 17:00:00']), $event);
    $ids = $event->refresh()->shows->pluck('id')->sort()->values()->all();

    // The same two days, sent as their curtain times.
    Show::saveShows(localDayRequest([HOUSTON_EVENING_UTC, '2030-11-03 01:00:00']), $event);

    expect($event->refresh()->shows->pluck('id')->sort()->values()->all())->toBe($ids);
});

test('dropping a day still deletes its row, by day', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();
    Show::saveShows(localDayRequest([HOUSTON_NOON_UTC, '2030-11-02 17:00:00']), $event);

    Show::saveShows(localDayRequest(['2030-11-02 17:00:00']), $event);

    expect($event->refresh()->shows->pluck('date')->all())->toBe(['2030-11-02 17:00:00']);
    expect(ShowChangeLog::where('event_id', $event->id)->where('action', 'removed')->value('dates'))->toBe(['2030-10-31']);
});

// ============================================================
// Repairing existing rows — ei:normalize-show-times
// ============================================================

test('the normaliser reports without writing, then moves rows in place and merges same-day duplicates', function () {
    $event = localDayEvent(['closingDate' => '2030-11-01 23:59:59']);
    $evening = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    // A legacy duplicate on the same local day, written later.
    $duplicate = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_NOON_UTC]);
    $fine = Show::factory()->create(['event_id' => $event->id, 'date' => '2030-10-30 17:00:00']);
    FakeSearchEngine::install([]);

    $dryRun = Show::normalizeToLocalNoon($event, apply: false);

    expect($dryRun)->toMatchArray(['updated' => 1, 'merged' => 1, 'closing_before' => '2030-11-01 23:59:59', 'closing_after' => '2030-10-31 23:59:59']);
    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(3);
    expect($evening->refresh()->date)->toBe(HOUSTON_EVENING_UTC);

    $applied = Show::normalizeToLocalNoon($event, apply: true);

    expect($applied['updated'])->toBe(1);
    expect($applied['merged'])->toBe(1);
    $rows = Show::withoutGlobalScopes()->where('event_id', $event->id)->orderBy('id')->get();
    expect($rows->pluck('id')->all())->toBe([$evening->id, $fine->id]);
    expect($rows->pluck('date')->all())->toBe([HOUSTON_NOON_UTC, '2030-10-30 17:00:00']);
    expect(Show::withoutGlobalScopes()->find($duplicate->id))->toBeNull();
    expect((string) $event->refresh()->closingDate)->toBe('2030-10-31 23:59:59');
});

test('the normaliser leaves a compliant event alone', function () {
    $event = localDayEvent(['closingDate' => '2030-10-31 23:59:59']);
    Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_NOON_UTC]);

    $report = Show::normalizeToLocalNoon($event, apply: true);

    expect($report)->toMatchArray(['updated' => 0, 'merged' => 0]);
});

test('the command is a dry run unless told to apply', function () {
    $event = localDayEvent();
    $row = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    FakeSearchEngine::install([]);

    $this->artisan('ei:normalize-show-times', ['--event' => $event->slug])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();
    expect($row->refresh()->date)->toBe(HOUSTON_EVENING_UTC);

    $this->artisan('ei:normalize-show-times', ['--event' => $event->slug, '--apply' => true])
        ->expectsOutputToContain('Applied: 1 events, 1 shows moved')
        ->assertSuccessful();
    expect($row->refresh()->date)->toBe(HOUSTON_NOON_UTC);
});

// ============================================================
// Legacy rows: midnight UTC from before the noon convention is date-only
// ============================================================

test('a midnight row is a date when the schedule has no real times, an instant when it does', function () {
    // All-midnight schedule (the wizard until late 2025, assistants sending
    // a date list): the UTC date IS the day.
    $dateOnly = localDayEvent();
    Show::factory()->create(['event_id' => $dateOnly->id, 'date' => '2030-06-01 00:00:00']);
    Show::factory()->create(['event_id' => $dateOnly->id, 'date' => '2030-06-08 00:00:00']);
    expect($dateOnly->localDate('2030-06-01 00:00:00'))->toBe('2030-06-01');

    // The same value in a schedule that records curtain times is 7 PM
    // Central the evening before.
    $curtain = localDayEvent();
    Show::factory()->create(['event_id' => $curtain->id, 'date' => '2030-06-01 00:00:00']);
    Show::factory()->create(['event_id' => $curtain->id, 'date' => '2030-06-08 01:00:00']);
    expect($curtain->localDate('2030-06-01 00:00:00'))->toBe('2030-05-31');

    // An incoming value (no schedule behind it) is a date.
    expect(Show::localDay('2030-06-01 00:00:00', 'America/Chicago'))->toBe('2030-06-01');
    expect(Show::atLocalNoon('2030-06-01 00:00:00', 'America/Chicago'))->toBe('2030-06-01 17:00:00');
});

test('an assistant-written list of dates reads on its dates, and the repair leaves it alone', function () {
    // Reign of Terror: every row at midnight UTC, show_times naming the same
    // dates — Sept 25, Oct 2 … — for a Los Angeles event.
    $event = localDayEvent(['timezone' => 'America/Los_Angeles', 'closingDate' => '2030-10-02 23:59:59']);
    Show::factory()->create(['event_id' => $event->id, 'date' => '2030-09-25 00:00:00']);
    Show::factory()->create(['event_id' => $event->id, 'date' => '2030-10-02 00:00:00']);
    FakeSearchEngine::install([]);

    $this->withoutVite()->get('/events/'.$event->slug)->assertOk()
        ->assertSee('September 25th, 2030')->assertDontSee('September 24th, 2030');

    expect(Show::normalizeToLocalNoon($event, apply: true))->toMatchArray(['updated' => 0, 'merged' => 0]);
});

test('the normaliser leaves a date-only midnight row alone by default and moves it under --all, to the right day', function () {
    $event = localDayEvent(['closingDate' => '2025-06-01 23:59:59']);
    $legacy = Show::factory()->create(['event_id' => $event->id, 'date' => '2025-06-01 00:00:00']);
    FakeSearchEngine::install([]);

    expect(Show::normalizeToLocalNoon($event, apply: true)['updated'])->toBe(0);
    expect($legacy->refresh()->date)->toBe('2025-06-01 00:00:00');

    expect(Show::normalizeToLocalNoon($event, apply: true, onlyShifted: false)['updated'])->toBe(1);
    // Noon Chicago on June 1 — the day it was always meant to be.
    expect($legacy->refresh()->date)->toBe('2025-06-01 17:00:00');
});

test('a midnight row in a schedule with real times is a curtain time and moves to the evening before', function () {
    // Foresta Lumina: entries at 7:30 PM (23:30 UTC) and 8 PM (00:00 UTC
    // next day) in Toronto — the whole schedule records curtain times.
    $event = localDayEvent(['timezone' => 'America/Toronto']);
    $row = Show::factory()->create(['event_id' => $event->id, 'date' => '2030-09-05 00:00:00']);
    Show::factory()->create(['event_id' => $event->id, 'date' => '2030-09-12 23:30:00']);
    FakeSearchEngine::install([]);

    Show::normalizeToLocalNoon($event, apply: true);

    expect($row->refresh()->date)->toBe('2030-09-04 16:00:00');
});

test('the write path stores a midnight input as that date at noon local', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();

    Show::saveShows(localDayRequest(['2030-10-31 00:00:00']), $event);

    expect($event->refresh()->shows->first()->date)->toBe(HOUSTON_NOON_UTC);
});

test('a blank or junk timezone is read as UTC instead of failing the save', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent(['timezone' => 'Nowhere/Nothing']);

    Show::saveShows(localDayRequest(['2030-10-31 12:00:00'], 's', ''), $event);

    expect($event->refresh()->shows->first()->date)->toBe('2030-10-31 12:00:00');
});

test('the normaliser never touches a closing date that was set by hand', function () {
    // An ongoing run whose closing date an admin pushed past its generated
    // rows (Mystère, Museum of Ice Cream): rows move, the closing date stays.
    $event = localDayEvent(['closingDate' => '2031-02-07 23:59:59']);
    Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    FakeSearchEngine::install([]);

    $report = Show::normalizeToLocalNoon($event, apply: true);

    expect($report['updated'])->toBe(1);
    expect($report['closing_after'])->toBe('2031-02-07 23:59:59');
    expect((string) $event->refresh()->closingDate)->toBe('2031-02-07 23:59:59');
});

// ============================================================
// Normaliser edge cases Codex asked for
// ============================================================

test('the normaliser repairs a closing date the UTC-day rule got wrong even when no row moves', function () {
    // Noon in Auckland during NZDT (UTC+13) is 23:00 UTC the previous day.
    // The row is already on the convention; only the closing date is stale.
    $event = localDayEvent(['timezone' => 'Pacific/Auckland', 'closingDate' => '2030-11-01 23:59:59']);
    Show::factory()->create(['event_id' => $event->id, 'date' => '2030-11-01 23:00:00']);
    $engine = FakeSearchEngine::install([]);

    $report = Show::normalizeToLocalNoon($event, apply: true);

    expect($report['updated'])->toBe(0);
    expect($report['closing_after'])->toBe('2030-11-02 23:59:59');
    expect((string) $event->refresh()->closingDate)->toBe('2030-11-02 23:59:59');
    // And the index was refreshed from the committed rows.
    expect($engine->indexed)->toContain($event->id);
});

test('merging a duplicate day hands its tickets to a survivor that had none', function () {
    $event = localDayEvent();
    $survivor = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    $duplicate = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_NOON_UTC]);
    $ticket = $duplicate->tickets()->create(['name' => 'General', 'ticket_price' => 35, 'currency' => 'USD', 'type' => 'p', 'description' => '']);
    FakeSearchEngine::install([]);

    Show::normalizeToLocalNoon($event, apply: true);

    expect(Show::withoutGlobalScopes()->find($duplicate->id))->toBeNull();
    expect($ticket->refresh()->ticket_id)->toBe($survivor->id);
    expect($survivor->refresh()->tickets)->toHaveCount(1);
});

test('merging carries over tiers by name: the survivor keeps its own version of a shared name and gains the rest', function () {
    $event = localDayEvent();
    $survivor = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    $survivor->tickets()->create(['name' => 'General', 'ticket_price' => 20, 'currency' => 'USD', 'type' => 'p', 'description' => '']);
    $duplicate = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_NOON_UTC]);
    $duplicate->tickets()->create(['name' => 'General', 'ticket_price' => 99, 'currency' => 'USD', 'type' => 'p', 'description' => '']);
    $duplicate->tickets()->create(['name' => 'VIP', 'ticket_price' => 150, 'currency' => 'USD', 'type' => 'p', 'description' => '']);
    FakeSearchEngine::install([]);

    Show::normalizeToLocalNoon($event, apply: true);

    $tickets = $survivor->refresh()->tickets->sortBy('name')->values();
    expect($tickets->pluck('name')->all())->toBe(['General', 'VIP']);
    // The survivor's own General, not the duplicate's.
    expect((float) $tickets->firstWhere('name', 'General')->ticket_price)->toBe(20.0);
});

// ============================================================
// "New dates" notifications compare DAYS, not stored instants
// ============================================================

test('moving a curtain-time row onto the convention does not tell favoriters a date was added', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();
    $row = Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    FakeSearchEngine::install([]);
    $this->mock(\App\Services\EventNotificationDispatcher::class)
        ->shouldNotReceive('newDatesForSavedEvent');

    // The wizard re-saves the same day at noon.
    $this->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'timezone' => 'America/Chicago',
        'dateArray' => [HOUSTON_NOON_UTC],
    ])->assertOk();

    expect($event->refresh()->shows->pluck('id')->all())->toBe([$row->id]);
});

test('a genuinely new day still tells favoriters', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent();
    Show::factory()->create(['event_id' => $event->id, 'date' => HOUSTON_EVENING_UTC]);
    FakeSearchEngine::install([]);
    $this->mock(\App\Services\EventNotificationDispatcher::class)
        ->shouldReceive('newDatesForSavedEvent')->once();

    $this->postJson("/api/hosting/event/{$event->slug}", [
        'showtype' => 's',
        'timezone' => 'America/Chicago',
        'dateArray' => [HOUSTON_NOON_UTC, '2030-11-02 17:00:00'],
    ])->assertOk();
});

// ============================================================
// Timezones the running PHP may not know
// ============================================================

test('a legacy timezone alias is mapped to its zone, junk falls back to UTC', function () {
    // The server's PHP uses the system tz database, which has none of the
    // "backward" aliases; nine published events still say US/Eastern.
    expect(Show::validTimezone('US/Eastern'))->toBe('America/New_York');
    expect(Show::validTimezone('US/Pacific'))->toBe('America/Los_Angeles');
    expect(Show::validTimezone('America/Chicago'))->toBe('America/Chicago');
    expect(Show::validTimezone('Nowhere/Nothing'))->toBe('UTC');
    expect(Show::validTimezone(''))->toBe('UTC');
    expect(Show::validTimezone(null))->toBe('UTC');
});

test('an event on a legacy alias saves, reads and repairs as its real zone', function () {
    $this->actingAs(localDayModerator());
    $event = localDayEvent(['timezone' => 'US/Eastern']);
    FakeSearchEngine::install([]);

    // 8 PM Eastern on Oct 31 = 01:00 UTC Nov 1 (EDT); stored at noon New York.
    $request = localDayRequest(['2030-11-01 00:00:00'], 's', 'US/Eastern');
    Show::saveShows($request, $event);
    Show::updateEvent($request, $event);

    $event->refresh();
    // Midnight input is the date itself (Nov 1), stored at noon EDT = 16:00 UTC.
    expect($event->shows->first()->date)->toBe('2030-11-01 16:00:00');
    expect((string) $event->closingDate)->toBe('2030-11-01 23:59:59');
    expect($event->localDate('2030-11-01 16:00:00'))->toBe('2030-11-01');
    expect(Show::normalizeToLocalNoon($event, apply: false)['updated'])->toBe(0);
});
