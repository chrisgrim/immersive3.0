<?php

use App\Actions\Events\UpdateEventAction;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Events\ShowChangeLog;
use App\Models\Organizer;
use App\Models\User;
use App\Support\RecurringDates;
use App\Support\ShowHistory;
use App\Support\Validation\EventUpdateRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeSearchEngine;

/**
 * Days more than a year old live in events.show_history (App\Support\ShowHistory)
 * instead of one row each: the save path puts them there, ei:fold-show-history
 * moves rows that age past the year, and every reader counts both. Covers the
 * rules (staff edit history, nobody else can erase it, the newest day stays a
 * row, folding never changes a day) and the readers.
 */
const FOLD_TZ = 'America/New_York';

beforeEach(function () {
    Mail::fake();
    // A fixed Monday afternoon in New York, so "a year ago" is one known day.
    $this->travelTo(CarbonImmutable::parse('2026-09-28 18:00:00', 'UTC'));
});

function foldEvent(array $overrides = []): Event
{
    $event = Event::factory()->published()->create(array_merge([
        'organizer_id' => Organizer::factory()->create(['status' => 'p'])->id,
        'timezone' => FOLD_TZ,
        'showtype' => 'o',
        'hasLocation' => true,
    ], $overrides));
    Location::factory()->create(['event_id' => $event->id]);
    $event->priceranges()->create(['price' => '25']);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

/** Every day from $from to $to on one of $weekdays (0 = Sunday), as local days. */
function foldDays(string $from, string $to, array $weekdays = [0, 1, 2, 3, 4, 5, 6]): array
{
    $out = [];
    for ($d = CarbonImmutable::parse($from, 'UTC'); $d->toDateString() <= $to; $d = $d->addDay()) {
        if (in_array($d->dayOfWeek, $weekdays, true)) {
            $out[] = $d->toDateString();
        }
    }

    return $out;
}

/** Local days as the wizard sends them: noon in the event's timezone, UTC. */
function foldPayload(array $days): array
{
    return array_map(fn ($day) => Show::storedFromLocalDay($day, FOLD_TZ), $days);
}

/** Save a schedule the way the website does, as $user. */
function foldSave(Event $event, array $days, User $user, string $showtype = 'o'): UpdateEventAction
{
    test()->actingAs($user);
    $data = ['showtype' => $showtype, 'dateArray' => foldPayload($days), 'timezone' => FOLD_TZ];
    $action = app(UpdateEventAction::class);
    $action->handle(Event::findOrFail($event->id), $data, new Request($data));

    return $action;
}

/** Every show day an event holds, rows and history, sorted. */
function foldAllDays(Event $event): array
{
    return Show::scheduleDaysOf(Event::findOrFail($event->id), FOLD_TZ);
}

/** The local days of an event's rows, sorted. */
function foldRowDays(Event $event): array
{
    $rows = Show::withoutGlobalScopes()->where('event_id', $event->id)->pluck('date');
    $curtain = Show::usesCurtainTimes($rows);

    return $rows->map(fn ($d) => Show::localDay($d, FOLD_TZ, $curtain))->unique()->sort()->values()->all();
}

function foldStaff(): User
{
    return User::factory()->create(['type' => 'a']);
}

function foldOrganizer(Event $event): User
{
    $user = User::factory()->create(['type' => 'u']);
    $event->organizer->users()->attach($user->id, ['role' => 'owner']);
    $event->update(['user_id' => $user->id]);

    return $user;
}

// ============================================================
// The save path
// ============================================================

test('a staff save of a run open daily since 1977 keeps only the last year and the future as rows', function () {
    $event = foldEvent();
    $days = foldDays('1977-10-01', '2027-03-31');

    foldSave($event, $days, foldStaff());

    $event = Event::findOrFail($event->id);
    expect(foldAllDays($event))->toBe($days)
        ->and(foldRowDays($event)[0])->toBe('2025-09-28')
        ->and(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(count(foldDays('2025-09-28', '2027-03-31')))
        ->and(ShowHistory::firstDay($event->show_history))->toBe('1977-10-01')
        ->and(ShowHistory::lastDay($event->show_history))->toBe('2025-09-27')
        ->and($event->show_history['runs'])->toHaveCount(1)
        // closingDate still comes from the newest row.
        ->and((string) $event->closingDate)->toBe('2027-03-31 23:59:59');
});

test('re-saving the same long schedule changes nothing and logs nothing', function () {
    $event = foldEvent();
    $days = foldDays('1990-01-01', '2026-12-31', [0, 2, 3, 4, 5, 6]);
    $staff = foldStaff();
    foldSave($event, $days, $staff);

    $idsBefore = Show::withoutGlobalScopes()->where('event_id', $event->id)->orderBy('id')->pluck('id')->all();
    $historyBefore = Event::findOrFail($event->id)->show_history;
    $logsBefore = ShowChangeLog::where('event_id', $event->id)->count();

    foldSave($event, $days, $staff);

    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->orderBy('id')->pluck('id')->all())->toBe($idsBefore)
        ->and(Event::findOrFail($event->id)->show_history)->toBe($historyBefore)
        ->and(ShowChangeLog::where('event_id', $event->id)->count())->toBe($logsBefore);
});

test('the change log names what a person changed, never the days that only moved into the history', function () {
    $event = foldEvent();
    $staff = foldStaff();
    // Rows only, as every event was before the history existed.
    $old = foldDays('2024-06-01', '2024-06-10');
    foreach (foldPayload($old) as $date) {
        Show::create(['event_id' => $event->id, 'date' => $date]);
    }
    $new = ['2026-10-10'];

    foldSave($event, array_merge($old, $new), $staff);

    expect(ShowChangeLog::where('event_id', $event->id)->where('action', 'added')->value('dates'))->toBe($new)
        ->and(ShowChangeLog::where('event_id', $event->id)->where('action', 'removed')->count())->toBe(0)
        ->and(foldRowDays($event))->toBe($new)
        ->and(ShowHistory::days(Event::findOrFail($event->id)->show_history))->toBe($old);
});

test('staff can remove old days, which leave the history', function () {
    $event = foldEvent();
    $staff = foldStaff();
    $days = foldDays('2010-01-01', '2026-12-31');
    foldSave($event, $days, $staff);

    $without2015 = array_values(array_filter($days, fn ($d) => ! str_starts_with($d, '2015-')));
    foldSave($event, $without2015, $staff);

    expect(foldAllDays($event))->toBe($without2015)
        ->and(ShowChangeLog::where('event_id', $event->id)->where('action', 'removed')->latest('id')->value('dates'))
        ->toBe(foldDays('2015-01-01', '2015-12-31'));
});

test('an organizer can never erase history days, and is told they were kept', function () {
    $event = foldEvent();
    $days = foldDays('2012-05-01', '2026-12-31');
    foldSave($event, $days, foldStaff());
    $organizer = foldOrganizer($event);

    // An organizer's calendar only sends the future.
    $action = foldSave($event, foldDays('2026-09-28', '2026-12-31'), $organizer);

    $past = foldDays('2012-05-01', '2026-09-27');
    expect(foldAllDays($event))->toBe($days)
        ->and($action->preservedPastDates)->toBe($past);
});

test('an organizer re-sending the history they were shown gets no warning', function () {
    $event = foldEvent();
    $days = foldDays('2012-05-01', '2026-12-31');
    foldSave($event, $days, foldStaff());
    $organizer = foldOrganizer($event);

    $action = foldSave($event, $days, $organizer);

    expect($action->preservedPastDates)->toBe([])
        ->and($action->rejectedPastDates)->toBe([])
        ->and(foldAllDays($event))->toBe($days);
});

test('an organizer cannot invent an old day, in the history or anywhere else', function () {
    $event = foldEvent();
    $days = foldDays('2012-05-01', '2026-12-31', [5, 6]);
    foldSave($event, $days, foldStaff());
    $organizer = foldOrganizer($event);

    // 2013-01-01 was a Tuesday: never a show day of this run.
    $action = foldSave($event, array_merge(['2013-01-01'], $days), $organizer);

    expect($action->rejectedPastDates)->toBe(['2013-01-01'])
        ->and(foldAllDays($event))->toBe($days);
});

test('a staff switch of show type clears the history with the rows', function () {
    $event = foldEvent();
    $staff = foldStaff();
    foldSave($event, foldDays('2015-01-01', '2026-12-31'), $staff);

    foldSave($event, ['2026-11-01', '2026-11-02'], $staff, 's');

    expect(Event::findOrFail($event->id)->show_history)->toBeNull()
        ->and(foldAllDays($event))->toBe(['2026-11-01', '2026-11-02']);
});

test('a schedule of only old days keeps its newest day as a row', function () {
    $event = foldEvent(['showtype' => 's']);
    $days = ['2001-03-01', '2001-03-02', '2003-07-04'];

    foldSave($event, $days, foldStaff(), 's');

    $event = Event::findOrFail($event->id);
    expect(foldRowDays($event))->toBe(['2003-07-04'])
        ->and(ShowHistory::days($event->show_history))->toBe(['2001-03-01', '2001-03-02'])
        ->and(substr((string) $event->closingDate, 0, 10))->toBe('2003-07-04');
});

test('dropping the recent days of a long run puts its newest old day back as a row', function () {
    $event = foldEvent();
    $staff = foldStaff();
    foldSave($event, foldDays('2015-01-01', '2026-12-31'), $staff);

    $onlyOld = foldDays('2015-01-01', '2020-12-31');
    foldSave($event, $onlyOld, $staff);

    expect(foldAllDays($event))->toBe($onlyOld)
        ->and(foldRowDays($event))->toBe(['2020-12-31']);
});

test('a copy of an event starts with no history', function () {
    Storage::fake('digitalocean');
    $event = foldEvent();
    foldSave($event, foldDays('2015-01-01', '2026-12-31'), foldStaff());

    $copy = Event::findOrFail($event->id)->duplicate();

    expect(Event::findOrFail($copy->id)->show_history)->toBeNull();
});

// ============================================================
// Validation caps
// ============================================================

test('a dateArray may reach 100 years back but only 9,500 of its days can be from the last year on', function () {
    $validate = fn (array $days) => validator(
        ['showtype' => 'o', 'dateArray' => foldPayload($days)],
        ['dateArray' => EventUpdateRules::rules(FOLD_TZ)['dateArray']],
    );

    // 1930 to 2031: ~37,000 days, but only about six years of them are rows.
    expect($validate(foldDays('1930-01-01', '2031-12-31'))->passes())->toBeTrue();

    $recent = foldDays('2025-09-28', '2051-12-31');
    expect(count($recent))->toBeGreaterThan(Show::MAX_ROWS)
        ->and($validate($recent)->passes())->toBeFalse()
        ->and($validate(array_slice($recent, 0, Show::MAX_ROWS))->passes())->toBeTrue();

    expect(RecurringDates::MAX_OCCURRENCES)->toBeGreaterThan(105 * 366);
});

// ============================================================
// ei:fold-show-history
// ============================================================

/** Two years of daily rows written straight into the table, as an old run has them. */
function foldSeedRows(Event $event, string $from, string $to, string $time = '12:00:00'): array
{
    $days = foldDays($from, $to);
    $now = now();
    collect($days)->map(fn ($day) => [
        'event_id' => $event->id,
        'date' => $time === 'noon' ? Show::storedFromLocalDay($day, FOLD_TZ) : $day.' '.$time,
        'created_at' => $now,
        'updated_at' => $now,
    ])->chunk(500)->each(fn ($chunk) => Show::insert($chunk->values()->all()));
    $event->update(['closingDate' => substr(end($days), 0, 10).' 23:59:59']);

    return $days;
}

test('the weekly fold moves a running event\'s old rows into its history without changing a day', function () {
    $event = foldEvent();
    $days = foldSeedRows($event, '2023-01-01', '2027-01-31', 'noon');

    $this->artisan('ei:fold-show-history', ['--apply' => true])->assertSuccessful();

    $event = Event::findOrFail($event->id);
    expect(foldAllDays($event))->toBe($days)
        ->and(foldRowDays($event)[0])->toBe('2025-09-28')
        ->and(ShowHistory::lastDay($event->show_history))->toBe('2025-09-27');

    // A second run has nothing left to do.
    $this->artisan('ei:fold-show-history', ['--apply' => true])
        ->expectsOutputToContain('0 events, 0 rows')
        ->assertSuccessful();
});

test('a dry run of the fold reports but writes nothing', function () {
    $event = foldEvent();
    foldSeedRows($event, '2023-01-01', '2027-01-31', 'noon');
    $rowsBefore = Show::withoutGlobalScopes()->where('event_id', $event->id)->count();

    $this->artisan('ei:fold-show-history')
        ->expectsOutputToContain('Dry run (nothing written; add --apply): 1 events')
        ->assertSuccessful();

    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe($rowsBefore)
        ->and(Event::findOrFail($event->id)->show_history)->toBeNull();
});

test('the fold leaves a finished run alone unless asked for --all', function () {
    $event = foldEvent();
    $days = foldSeedRows($event, '2021-01-01', '2023-06-30', 'noon');

    $this->artisan('ei:fold-show-history', ['--apply' => true])->assertSuccessful();
    expect(Event::findOrFail($event->id)->show_history)->toBeNull();

    $this->artisan('ei:fold-show-history', ['--apply' => true, '--all' => true])->assertSuccessful();
    expect(foldAllDays($event))->toBe($days)
        ->and(foldRowDays($event))->toBe(['2023-06-30']);
});

test('the fold never touches an always-available event', function () {
    $event = foldEvent(['showtype' => 'a']);
    Show::create(['event_id' => $event->id, 'date' => '2020-01-01 12:00:00']);
    $event->update(['closingDate' => '2030-01-01 00:00:00']);

    $this->artisan('ei:fold-show-history', ['--apply' => true, '--all' => true])->assertSuccessful();

    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(1)
        ->and(Event::findOrFail($event->id)->show_history)->toBeNull();
});

test('folding away the only timed rows does not shift the midnight rows left behind', function () {
    // Old rows at curtain time (8 PM New York = 00:00 UTC next day is the
    // trap, so use 23:30 UTC), recent legacy rows at midnight UTC. With the
    // timed rows present, a midnight row is a real instant: 2026-10-05
    // 00:00 UTC is the evening of Oct 4 in New York. Folding the timed rows
    // away must keep reading it as Oct 4.
    $event = foldEvent(['showtype' => 's']);
    Show::create(['event_id' => $event->id, 'date' => '2022-03-01 23:30:00']);
    Show::create(['event_id' => $event->id, 'date' => '2026-10-05 00:00:00']);
    $event->update(['closingDate' => '2026-10-04 23:59:59']);
    $before = foldAllDays($event);
    expect($before)->toBe(['2022-03-01', '2026-10-04']);

    $this->artisan('ei:fold-show-history', ['--apply' => true])->assertSuccessful();

    expect(foldAllDays($event))->toBe($before)
        ->and(foldRowDays($event))->toBe(['2026-10-04']);
});

// ============================================================
// Readers
// ============================================================

test('the event page gives the real first day, the whole count, and the runs for its calendars', function () {
    $event = foldEvent();
    $days = foldDays('1977-10-01', '2026-12-31', [0, 2, 3, 4, 5, 6]);
    foldSave($event, $days, foldStaff());
    FakeSearchEngine::install([]);

    $response = $this->withoutVite()->get('/events/'.$event->slug)->assertOk();

    $response->assertSee('October 1st, 1977');
    $html = $response->getContent();
    expect($html)->toContain('show_history')
        ->and($html)->not->toContain('July 4th, 2020');
});

test('saved events show the run from its real first day', function () {
    $event = foldEvent();
    foldSave($event, foldDays('1999-02-01', '2026-12-31'), foldStaff());

    $loaded = Event::query()
        ->withMin('shows as first_show_date', 'date')
        ->withMax('shows as last_show_date', 'date')
        ->withCount(['shows as timed_shows_count' => fn ($q) => $q->whereRaw("TIME(date) <> '00:00:00'")])
        ->findOrFail($event->id);

    expect($loaded->dateRangeLabel())->toBe('Feb 1, 1999 - Dec 31, 2026')
        ->and($loaded->runDateParts()['first']['label'])->toBe('Feb 1, 1999');
});

test('the search document still carries only rows from two days ago on', function () {
    $event = foldEvent();
    foldSave($event, foldDays('1980-01-01', '2026-12-31'), foldStaff());

    $shows = Event::findOrFail($event->id)->toSearchableArray()['shows'];

    expect(count($shows))->toBe(count(foldDays('2026-09-26', '2026-12-31')));
});

// ============================================================
// The assistant (MCP) tools
// ============================================================

test('an admin can start an ongoing run in 1977 through update-event', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => null, 'user_id' => $admin->id]);

    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('1977-10-01', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2027-06-30', FOLD_TZ),
            'daysOfWeek' => [0, 2, 3, 4, 5, 6],
        ],
    ])->assertOk()->assertSee('Event updated');

    expect(foldAllDays($event))->toBe(foldDays('1977-10-01', '2027-06-30', [0, 2, 3, 4, 5, 6]))
        ->and(ShowHistory::firstDay(Event::findOrFail($event->id)->show_history))->toBe('1977-10-01');
});

test('get-event reports the older days, and extending with the same start removes nothing', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'user_id' => $admin->id]);
    foldSave($event, foldDays('1977-10-01', '2026-12-31'), $admin);

    $get = \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\GetEvent::class, ['event_slug' => $event->slug]);
    $get->assertOk()->assertSee('older_show_days')->assertSee('1977-10-01');

    // Extend the run to next summer, keeping its real start.
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('1977-10-01', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2027-08-31', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
    ])->assertOk()->assertDontSee('confirm_schedule_replace')->assertDontSee('past_dates');

    expect(foldAllDays($event))->toBe(foldDays('1977-10-01', '2027-08-31'));
});

test('a recipe starting today keeps the older days and asks before dropping the last year', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'user_id' => $admin->id]);
    foldSave($event, foldDays('2000-01-01', '2026-12-31'), $admin);

    // Starting the recipe today drops the past rows (the last year); the
    // older days are kept whatever the recipe says.
    $response = \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('2026-09-28', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2026-12-31', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
    ]);

    $removed = count(foldDays('2025-09-28', '2026-09-27'));
    $response->assertOk()->assertSee('confirm_schedule_replace')->assertSee('"shows_to_remove":'.$removed, false);
    expect(foldAllDays($event))->toBe(foldDays('2000-01-01', '2026-12-31'));
});

test('update-event refuses a recurrence with more than the row cap from the last year on', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => null, 'user_id' => $admin->id]);

    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('2026-10-01', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2053-01-01', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
    ])->assertOk()->assertSee('schedule_too_long');

    expect(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(0);
});

// ============================================================
// Review follow-ups
// ============================================================

test('the schedule assistant snapshot carries the history, so the editor keeps it', function () {
    $event = foldEvent();
    foldSave($event, foldDays('1990-01-01', '2026-12-31'), foldStaff());

    $snapshot = app(\App\Services\EventScheduleAssistant::class)->scheduleSnapshot(Event::findOrFail($event->id));

    expect($snapshot['older_show_days']['first_day'])->toBe('1990-01-01')
        ->and($snapshot['older_show_days']['runs'])->not->toBeEmpty()
        ->and($snapshot['show_count'])->toBe(count(foldDays('1990-01-01', '2026-12-31')));
});

test('an organizer keeps a run\'s history through "always available" and back', function () {
    $event = foldEvent();
    $days = foldDays('2012-05-01', '2026-12-31');
    foldSave($event, $days, foldStaff());
    $organizer = foldOrganizer($event);
    $history = foldDays('2012-05-01', '2025-09-27');

    test()->actingAs($organizer);
    $always = ['showtype' => 'a', 'always_config' => ['endDate' => '2027-06-01 12:00:00'], 'timezone' => FOLD_TZ];
    $action = app(UpdateEventAction::class);
    $action->handle(Event::findOrFail($event->id), $always, new Request($always));
    expect(ShowHistory::days(Event::findOrFail($event->id)->show_history))->toBe($history);

    // A second save of the same sentinel type must not drop it either.
    $action = app(UpdateEventAction::class);
    $action->handle(Event::findOrFail($event->id), $always, new Request($always));
    expect(ShowHistory::days(Event::findOrFail($event->id)->show_history))->toBe($history)
        ->and($action->preservedPastDates)->toBe([]);
});

test('a save whose whole schedule would pass the total cap is refused and changes nothing', function () {
    $event = foldEvent(['showtype' => 's']);
    // History straight into the column: close to the total cap already.
    $old = array_slice(foldDays('1900-01-01', '2025-01-01'), 0, RecurringDates::MAX_OCCURRENCES - 10);
    expect($old)->toHaveCount(RecurringDates::MAX_OCCURRENCES - 10);
    $event->forceFill(['show_history' => ShowHistory::pack($old, '2025-01-01')])->save();
    Show::create(['event_id' => $event->id, 'date' => Show::storedFromLocalDay('2026-10-01', FOLD_TZ)]);
    $organizer = foldOrganizer($event);

    // Twenty new future days: a small request, but the kept history makes it
    // too big. Sent with a new name and a switch to ongoing, neither of which
    // may be saved when the schedule is refused.
    test()->actingAs($organizer);
    $data = ['showtype' => 'o', 'dateArray' => foldPayload(foldDays('2026-10-01', '2026-10-20')), 'timezone' => FOLD_TZ, 'name' => 'Renamed'];
    expect(fn () => app(UpdateEventAction::class)->handle(Event::findOrFail($event->id), $data, new Request($data)))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $after = Event::findOrFail($event->id);
    expect(foldRowDays($event))->toBe(['2026-10-01'])
        ->and(ShowHistory::count($after->show_history))->toBe(count($old))
        ->and($after->name)->not->toBe('Renamed')
        ->and($after->showtype)->toBe('s');
});

test('validating 40,000 dates takes one quick pass and still names a bad entry', function () {
    $dates = foldPayload(array_slice(foldDays('1920-01-01', '2031-12-31'), 0, RecurringDates::MAX_OCCURRENCES));
    $rules = collect(EventUpdateRules::rules(FOLD_TZ))->only(['showtype', 'dateArray', 'dateArray.*'])->all();

    $start = microtime(true);
    $ok = validator(['showtype' => 'o', 'dateArray' => $dates], $rules);
    expect($ok->passes())->toBeTrue()
        ->and(microtime(true) - $start)->toBeLessThan(3.0);

    $dates[3] = '2026-05-28';
    $bad = validator(['showtype' => 'o', 'dateArray' => $dates], $rules);
    expect($bad->fails())->toBeTrue()
        ->and($bad->errors()->has('dateArray.3'))->toBeTrue();
});

test('update-event keeps older days when a dateArray leaves them out, and drops only the ones named', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => 's', 'user_id' => $admin->id]);
    $old = foldDays('2001-01-01', '2001-12-31', [6]);
    foldSave($event, array_merge($old, ['2026-10-10']), $admin, 's');

    // Just the upcoming days, as an assistant would send them, dropping one
    // older day: that is a removal, so it is confirmed first.
    $args = [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => FOLD_TZ,
        'dateArray' => ['2026-10-10', '2026-10-17'],
        'remove_older_show_days' => ['2001-01-06'],
    ];
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args)
        ->assertOk()->assertSee('"shows_to_remove":1', false);
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args + ['confirm_schedule_replace' => true])
        ->assertOk()->assertSee('Event updated');

    $kept = array_values(array_diff($old, ['2001-01-06']));
    expect(foldAllDays($event))->toBe(array_merge($kept, ['2026-10-10', '2026-10-17']));
});

test('update-event with replace_older_show_days asks before dropping the older days', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => 's', 'user_id' => $admin->id]);
    foldSave($event, array_merge(foldDays('2001-01-01', '2001-01-31'), ['2026-10-10']), $admin, 's');

    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => FOLD_TZ,
        'dateArray' => ['2026-10-10'],
        'replace_older_show_days' => true,
    ])->assertOk()->assertSee('confirm_schedule_replace')->assertSee('"shows_to_remove":31', false);
});

test('changing the timezone in the same save keeps every day, for an organizer too', function () {
    $event = foldEvent();
    $days = foldDays('2024-01-01', '2026-12-31');
    foldSave($event, $days, foldStaff());
    $organizer = foldOrganizer($event);

    // The same days, sent for Tokyo: an organizer's editor re-sends the whole schedule.
    test()->actingAs($organizer);
    $data = ['showtype' => 'o', 'timezone' => 'Asia/Tokyo', 'dateArray' => array_map(fn ($d) => Show::storedFromLocalDay($d, 'Asia/Tokyo'), $days)];
    $action = app(UpdateEventAction::class);
    $action->handle(Event::findOrFail($event->id), $data, new Request($data));

    expect(Show::scheduleDaysOf(Event::findOrFail($event->id), 'Asia/Tokyo'))->toBe($days)
        ->and($action->rejectedPastDates)->toBe([])
        ->and($action->preservedPastDates)->toBe([])
        // One row per day, no duplicates from the move.
        // (A year ago in Tokyo is a day later than in New York.)
        ->and(Show::withoutGlobalScopes()->where('event_id', $event->id)->count())->toBe(count(foldDays('2025-09-29', '2026-12-31')));
});

test('an organizer\'s kept past rows stay on their day when the timezone changes', function () {
    $event = foldEvent(['showtype' => 's']);
    foldSave($event, ['2026-09-01', '2026-09-02', '2026-12-01'], foldStaff(), 's');
    $organizer = foldOrganizer($event);

    // Only the future day, for Tokyo: the two past days are kept anyway.
    test()->actingAs($organizer);
    $data = ['showtype' => 's', 'timezone' => 'Asia/Tokyo', 'dateArray' => [Show::storedFromLocalDay('2026-12-01', 'Asia/Tokyo')]];
    $action = app(UpdateEventAction::class);
    $action->handle(Event::findOrFail($event->id), $data, new Request($data));

    expect(Show::scheduleDaysOf(Event::findOrFail($event->id), 'Asia/Tokyo'))->toBe(['2026-09-01', '2026-09-02', '2026-12-01'])
        ->and($action->preservedPastDates)->toBe(['2026-09-01', '2026-09-02']);
});

test('a live-edit preview shows the dates sent and a count of the older days kept, not every one', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['showtype' => 's', 'user_id' => $admin->id]);
    foldSave($event, array_merge(foldDays('1980-01-01', '2000-12-31'), ['2026-10-10']), $admin, 's');

    $response = \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 's',
        'timezone' => FOLD_TZ,
        'dateArray' => ['2026-10-10', '2026-10-17'],
    ]);

    $response->assertOk()->assertSee('confirm_live_edit')
        ->assertSee('"older_days_kept":'.count(foldDays('1980-01-01', '2000-12-31')), false)
        ->assertDontSee('1990-06-15');
});

test('fixing a timezone does not tell favoriters about new dates', function () {
    $event = foldEvent(['timezone' => 'Australia/Sydney']);
    $days = foldDays('2026-10-02', '2026-12-25', [5]);
    test()->actingAs(foldStaff());
    $data = ['showtype' => 'o', 'timezone' => 'Australia/Sydney', 'dateArray' => array_map(fn ($d) => Show::storedFromLocalDay($d, 'Australia/Sydney'), $days)];
    app(UpdateEventAction::class)->handle(Event::findOrFail($event->id), $data, new Request($data));

    $this->mock(\App\Services\EventNotificationDispatcher::class)->shouldNotReceive('newDatesForSavedEvent');

    // The same Fridays, now for New York.
    $data = ['showtype' => 'o', 'timezone' => FOLD_TZ, 'dateArray' => foldPayload($days)];
    app(UpdateEventAction::class)->handle(Event::findOrFail($event->id), $data, new Request($data));

    expect(foldAllDays($event))->toBe($days);
});

// ============================================================
// The undo: ei:unfold-show-history
// ============================================================

test('unfolding puts every history day back as a row and clears the history', function () {
    $event = foldEvent();
    $days = foldDays('1990-01-01', '2026-12-31', [0, 3, 5]);
    foldSave($event, $days, foldStaff());
    expect(Event::findOrFail($event->id)->show_history)->not->toBeNull();

    $this->artisan('ei:unfold-show-history')->expectsOutputToContain('Dry run (nothing written; add --apply): 1 events')->assertSuccessful();
    expect(Event::findOrFail($event->id)->show_history)->not->toBeNull();

    $this->artisan('ei:unfold-show-history', ['--apply' => true])->assertSuccessful();

    expect(Event::findOrFail($event->id)->show_history)->toBeNull()
        ->and(foldRowDays($event))->toBe($days)
        ->and(foldAllDays($event))->toBe($days);
});

test('unfolding next to legacy midnight rows does not shift them', function () {
    // Midnight rows with no timed rows are calendar dates. Adding noon rows
    // would make them read as instants (a day early in New York) unless
    // they are moved first.
    $event = foldEvent(['showtype' => 's']);
    Show::create(['event_id' => $event->id, 'date' => '2026-10-05 00:00:00']);
    $event->forceFill(['show_history' => ShowHistory::pack(['2001-02-03'], '2001-12-31')])->save();
    expect(foldAllDays($event))->toBe(['2001-02-03', '2026-10-05']);

    $this->artisan('ei:unfold-show-history', ['--apply' => true])->assertSuccessful();

    expect(foldAllDays($event))->toBe(['2001-02-03', '2026-10-05'])
        ->and(Event::findOrFail($event->id)->show_history)->toBeNull();
});

test('rolling the migration back refuses while any history exists', function () {
    $event = foldEvent();
    $event->forceFill(['show_history' => ShowHistory::pack(['2001-02-03'], '2001-12-31')])->save();
    $migration = require database_path('migrations/2026_09_28_120000_add_show_history_to_events_table.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'ei:unfold-show-history');
    expect(\Illuminate\Support\Facades\Schema::hasColumn('events', 'show_history'))->toBeTrue();
});

test('a recipe reaching back past the history does not refill its closures, and removals always apply', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'user_id' => $admin->id]);
    // Daily since 1980, closed every Christmas.
    $days = array_values(array_filter(foldDays('1980-01-01', '2026-12-31'), fn ($d) => ! str_ends_with($d, '-12-25')));
    foldSave($event, $days, $admin);

    $args = [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('1980-01-01', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2026-12-31', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
        'remove_older_show_days' => ['1990-07-04'],
    ];
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args)
        ->assertOk()->assertSee('"shows_to_remove":1', false);
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args + ['confirm_schedule_replace' => true])
        ->assertOk()->assertSee('Event updated')->assertSee('older_days_not_added');

    $after = foldAllDays($event);
    expect($after)->not->toContain('1990-07-04')
        ->and($after)->not->toContain('2001-12-25')
        // The last year and the future are rows: the recipe covers them
        // exactly as before, so their two Christmases are added.
        ->and($after)->toContain('2025-12-25')
        ->and($after)->toContain('2026-12-25')
        ->and(count($after))->toBe(count($days) - 1 + 2);
});

test('removing older days on their own goes through the usual confirmation', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => 's', 'user_id' => $admin->id]);
    $days = array_merge(foldDays('2001-01-01', '2001-01-10'), ['2026-10-10', '2026-10-11']);
    foldSave($event, $days, $admin, 's');

    $args = ['event_slug' => $event->slug, 'remove_older_show_days' => ['2001-01-03', '2001-01-04']];
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args)
        ->assertOk()->assertSee('"shows_to_remove":2', false);
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args + ['confirm_schedule_replace' => true])
        ->assertOk()->assertSee('Event updated');

    expect(foldAllDays($event))->toBe(array_values(array_diff($days, ['2001-01-03', '2001-01-04'])));
});

test('a live-edit preview of a long recipe shows a summary, not the list', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['user_id' => $admin->id]);
    foldSave($event, foldDays('1980-01-01', '2026-12-31'), $admin);

    $response = \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('2025-09-28', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2027-06-30', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
    ]);

    $response->assertOk()->assertSee('confirm_live_edit')->assertSee('"count":'.count(foldDays('2025-09-28', '2027-06-30')), false);
    expect(strlen((string) json_encode($response)))->toBeLessThan(100000);
});

test('removing an older day on its own leaves upcoming curtain-time shows on their day', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'showtype' => 's', 'user_id' => $admin->id]);
    // Curtain times: 8 PM New York is midnight UTC the next day, next to a
    // timed row, so it reads as the evening of Oct 1.
    Show::create(['event_id' => $event->id, 'date' => '2026-10-02 00:00:00']);
    Show::create(['event_id' => $event->id, 'date' => '2026-10-09 23:30:00']);
    $event->forceFill(['show_history' => ShowHistory::pack(['2001-02-03', '2001-02-04'], '2025-09-27')])->save();
    $event->update(['closingDate' => '2026-10-09 23:59:59']);
    expect(foldAllDays($event))->toBe(['2001-02-03', '2001-02-04', '2026-10-01', '2026-10-09']);

    $args = ['event_slug' => $event->slug, 'remove_older_show_days' => ['2001-02-03'], 'confirm_schedule_replace' => true];
    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, $args)->assertOk()->assertSee('Event updated');

    expect(foldAllDays($event))->toBe(['2001-02-04', '2026-10-01', '2026-10-09']);
});

test('a recipe keeps old days that are still rows, waiting for the fold', function () {
    $admin = User::factory()->create(['type' => 'a', 'email_verified_at' => now()]);
    $event = foldEvent(['status' => '0', 'user_id' => $admin->id]);
    foldSave($event, foldDays('2020-01-01', '2026-12-31'), $admin);
    // Time passes: days that were in the last year are now older than a
    // year but still rows until the weekly fold.
    $this->travelTo(CarbonImmutable::parse('2026-11-15 18:00:00', 'UTC'));
    $before = foldAllDays($event);

    \App\Mcp\Servers\EiServer::actingAs($admin)->tool(\App\Mcp\Tools\UpdateEvent::class, [
        'event_slug' => $event->slug,
        'showtype' => 'o',
        'timezone' => FOLD_TZ,
        'ongoing_config' => [
            'startDate' => Show::storedFromLocalDay('2020-01-01', FOLD_TZ),
            'endDate' => Show::storedFromLocalDay('2026-12-31', FOLD_TZ),
            'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6],
        ],
    ])->assertOk()->assertDontSee('confirm_schedule_replace')->assertDontSee('older_days_not_added');

    expect(foldAllDays($event))->toBe($before);
});
