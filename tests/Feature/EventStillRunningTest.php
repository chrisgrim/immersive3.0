<?php

use App\Models\Event;
use Carbon\Carbon;

/**
 * closingDate is a wall time in the event's own timezone (the end of its last
 * local day). "Is this run over?" has to read it in that zone: compared as
 * UTC, a Los Angeles run left search at 5pm on its last day, and one in
 * Tokyo lingered nine hours after it ended.
 */
afterEach(fn () => Carbon::setTestNow());

function endingEvent(string $timezone, string $closingDate = '2026-11-30 23:59:59'): Event
{
    return Event::factory()->published()->create(['timezone' => $timezone, 'closingDate' => $closingDate]);
}

test('closingAt reads closingDate in the event timezone', function () {
    expect(endingEvent('America/Los_Angeles')->closingAt()->toDateTimeString())->toBe('2026-12-01 07:59:59')
        ->and(endingEvent('Asia/Tokyo')->closingAt()->toDateTimeString())->toBe('2026-11-30 14:59:59')
        ->and(endingEvent('US/Eastern')->closingAt()->toDateTimeString())->toBe('2026-12-01 04:59:59')
        ->and(endingEvent('Not/AZone')->closingAt()->toDateTimeString())->toBe('2026-11-30 23:59:59');
});

test('a Los Angeles run is still running on its last evening, after midnight UTC', function () {
    $la = endingEvent('America/Los_Angeles');
    // 8pm in Los Angeles on Nov 30 is 04:00 UTC on Dec 1.
    Carbon::setTestNow('2026-12-01 04:00:00');

    expect($la->fresh()->isShowing)->toBeTrue()
        ->and(Event::stillRunning()->whereKey($la->id)->exists())->toBeTrue();

    Carbon::setTestNow('2026-12-01 08:00:01');
    expect($la->fresh()->isShowing)->toBeFalse()
        ->and(Event::stillRunning()->whereKey($la->id)->exists())->toBeFalse();
});

test('a Tokyo run ends at midnight Tokyo time, not hours later', function () {
    $tokyo = endingEvent('Asia/Tokyo');
    Carbon::setTestNow('2026-11-30 14:59:00');
    expect(Event::stillRunning()->whereKey($tokyo->id)->exists())->toBeTrue();

    Carbon::setTestNow('2026-11-30 15:00:00');
    expect(Event::stillRunning()->whereKey($tokyo->id)->exists())->toBeFalse()
        ->and($tokyo->fresh()->isShowing)->toBeFalse();
});

test('the search index carries closing_at and search filters on it', function () {
    expect(endingEvent('America/Los_Angeles')->toSearchableArray()['closing_at'])->toBe('2026-12-01T07:59:59Z');

    $filter = json_encode(Event::stillRunningSearchFilter()->buildQuery());
    expect($filter)->toContain('"closing_at"')->and($filter)->toContain('"closingDate"');
});

test('an embargo can still be set on a Los Angeles run on its last evening', function () {
    $this->actingAs(App\Models\User::factory()->create(['type' => 'u']));
    $la = endingEvent('America/Los_Angeles');
    Carbon::setTestNow('2026-12-01 04:00:00'); // 8pm in LA on Nov 30

    $refused = App\Models\Events\Show::applyEmbargo(new Illuminate\Http\Request(['embargo_date' => '2026-12-05 12:00:00']), $la);

    expect($refused)->toBeFalse()->and($la->fresh()->status)->toBe('e');
});

test('the 90-day edit lock counts from the end of the last local day', function () {
    $la = endingEvent('America/Los_Angeles', '2026-08-01 23:59:59'); // ends 2026-08-02 06:59:59 UTC

    Carbon::setTestNow(Carbon::parse('2026-08-02 06:59:59')->addDays(90)->subMinute());
    expect($la->fresh()->isHistorical())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-08-02 06:59:59')->addDays(90)->addMinute());
    expect($la->fresh()->isHistorical())->toBeTrue();
});

test('saving an event in a timezone the cached list has not seen refreshes the list', function () {
    endingEvent('America/Los_Angeles');
    Event::stillRunning()->count(); // warms the list with LA only
    expect(Cache::get(Event::TIMEZONES_IN_USE_CACHE))->toBe(['America/Los_Angeles']);

    $tokyo = endingEvent('Asia/Tokyo');
    expect(Cache::has(Event::TIMEZONES_IN_USE_CACHE))->toBeFalse();

    Carbon::setTestNow('2026-11-30 15:00:00'); // just past midnight in Tokyo
    expect(Event::stillRunning()->whereKey($tokyo->id)->exists())->toBeFalse();
});
