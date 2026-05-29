<?php

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

// ei:publish-embargoed — publishes embargoed (status 'e') events whose embargo_date
// has passed *in the event's own timezone*. Timezone handling is the critical bit.
//
// note: embargo_date is NOT in Event::$casts, so it is stored/read as a raw string.
// The command parses it via Carbon::parse($event->embargo_date, $eventTimezone),
// i.e. it interprets the stored wall-clock string AS-IF it were in the event timezone.

afterEach(function () {
    Carbon::setTestNow();
});

test('publishes an embargoed event whose embargo has passed in its own timezone', function () {
    // now = 2026-01-16 01:00 UTC. In America/New_York (EST, UTC-5) that is
    // 2026-01-15 20:00. The embargo wall-clock 2026-01-15 18:00 is in the past there.
    Carbon::setTestNow(Carbon::parse('2026-01-16 01:00:00', 'UTC'));

    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'America/New_York',
        'embargo_date' => '2026-01-15 18:00:00',
        'published_at' => null,
    ]);

    Artisan::call('ei:publish-embargoed');

    $event->refresh();
    expect($event->status)->toBe('p');
    // note: the command nulls embargo_date when it publishes; it does NOT set
    // published_at (unlike the moderator approve flow), so it stays null here.
    expect($event->embargo_date)->toBeNull();
    expect($event->published_at)->toBeNull();
});

test('leaves an embargoed event whose embargo is still in the future', function () {
    // now = 2026-01-15 20:00 UTC. In America/New_York that is 15:00. The embargo
    // wall-clock 2026-01-15 18:00 (EST) is still in the future, so it stays embargoed.
    Carbon::setTestNow(Carbon::parse('2026-01-15 20:00:00', 'UTC'));

    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'America/New_York',
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    $event->refresh();
    expect($event->status)->toBe('e');
    expect($event->embargo_date)->not->toBeNull();
});

test('falls back to Etc/UTC when timezone is null', function () {
    // With a null timezone the command treats the embargo wall-clock as UTC.
    // now = 2026-01-15 18:01 UTC, embargo = 2026-01-15 18:00 UTC -> just passed.
    Carbon::setTestNow(Carbon::parse('2026-01-15 18:01:00', 'UTC'));

    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => null,
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($event->fresh()->status)->toBe('p');
});

test('null-timezone embargo still in the future (treated as UTC) stays embargoed', function () {
    // now = 2026-01-15 17:59 UTC, embargo = 2026-01-15 18:00 UTC -> not yet.
    Carbon::setTestNow(Carbon::parse('2026-01-15 17:59:00', 'UTC'));

    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => null,
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($event->fresh()->status)->toBe('e');
});

test('publishes exactly when embargo equals now (lte boundary)', function () {
    // The check is $embargoDate->lte($nowInEventTimezone), so an exact match publishes.
    Carbon::setTestNow(Carbon::parse('2026-01-15 18:00:00', 'UTC'));

    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'Etc/UTC',
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($event->fresh()->status)->toBe('p');
});

test('respects timezone differences across two events at the same instant', function () {
    // now = 2026-01-16 01:00 UTC.
    Carbon::setTestNow(Carbon::parse('2026-01-16 01:00:00', 'UTC'));

    // New York (EST): now is 2026-01-15 20:00; embargo 18:00 -> publishes.
    $ny = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'America/New_York',
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    // Tokyo (JST, UTC+9): now is 2026-01-16 10:00; embargo wall-clock 2026-01-16 18:00
    // is still in the future there -> stays embargoed.
    $tokyo = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'Asia/Tokyo',
        'embargo_date' => '2026-01-16 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($ny->fresh()->status)->toBe('p');
    expect($tokyo->fresh()->status)->toBe('e');
});

test('ignores events that are not embargoed even if they have a past embargo_date', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-16 01:00:00', 'UTC'));

    // status 'd' (draft) with a past embargo: the command only queries status 'e'.
    $draft = Event::factory()->create([
        'status' => 'd',
        'timezone' => 'Etc/UTC',
        'embargo_date' => '2026-01-15 18:00:00',
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($draft->fresh()->status)->toBe('d');
});

test('ignores embargoed events with a null embargo_date', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-16 01:00:00', 'UTC'));

    // whereNotNull('embargo_date') excludes this row from the query entirely.
    $event = Event::factory()->create([
        'status' => 'e',
        'timezone' => 'Etc/UTC',
        'embargo_date' => null,
    ]);

    Artisan::call('ei:publish-embargoed');

    expect($event->fresh()->status)->toBe('e');
});

test('returns success and reports zero when there is nothing to publish', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-16 01:00:00', 'UTC'));

    $exit = Artisan::call('ei:publish-embargoed');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('No events to publish at this time');
});
