<?php

use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * embargo_date is a wall-clock time in the event's own timezone (the wizard
 * stores noon on the day the organizer picked). Until 2026-09-14 the publish
 * cron read it that way while admin approval and the validator compared it
 * as UTC, so on the embargo day the three could disagree by up to 14 hours.
 * These pin every reader to Event::embargoLiftsAt().
 */
afterEach(function () {
    Carbon::setTestNow();
});

test('embargoLiftsAt reads the stored value as a wall-clock time where the event is', function () {
    $la = Event::factory()->make(['timezone' => 'America/Los_Angeles', 'embargo_date' => '2026-01-15 12:00:00']);
    $seoul = Event::factory()->make(['timezone' => 'Asia/Seoul', 'embargo_date' => '2026-01-15 12:00:00']);
    $none = Event::factory()->make(['timezone' => 'Asia/Seoul', 'embargo_date' => null]);

    expect($la->embargoLiftsAt()->toIso8601String())->toBe('2026-01-15T12:00:00-08:00')
        ->and($la->embargoLiftsAt()->utc()->toDateTimeString())->toBe('2026-01-15 20:00:00')
        ->and($seoul->embargoLiftsAt()->utc()->toDateTimeString())->toBe('2026-01-15 03:00:00')
        ->and($none->embargoLiftsAt())->toBeNull()
        ->and($none->embargoIsPending())->toBeFalse();
});

test('embargoIsPending is decided in the event timezone, not UTC', function () {
    // 14:00 UTC on the embargo day: 06:00 in Los Angeles (noon still ahead),
    // 23:00 in Seoul (noon long gone). A UTC reading of "12:00:00" would call
    // both of them "already passed".
    Carbon::setTestNow(Carbon::parse('2026-01-15 14:00:00', 'UTC'));

    $la = Event::factory()->make(['timezone' => 'America/Los_Angeles', 'embargo_date' => '2026-01-15 12:00:00']);
    $seoul = Event::factory()->make(['timezone' => 'Asia/Seoul', 'embargo_date' => '2026-01-15 12:00:00']);

    expect($la->embargoIsPending())->toBeTrue()
        ->and($seoul->embargoIsPending())->toBeFalse();
});

test('admin approval embargoes an event whose noon has not yet come where it is', function () {
    Mail::fake();
    Carbon::setTestNow(Carbon::parse('2026-01-15 14:00:00', 'UTC')); // 06:00 in Los Angeles

    $moderator = User::factory()->create(['type' => 'm']);
    $event = Event::factory()->inReview()->create([
        'timezone' => 'America/Los_Angeles',
        'embargo_date' => '2026-01-15 12:00:00',
    ]);

    $this->actingAs($moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'e');

    // ...and the cron agrees it is not time yet, then publishes once it is.
    Artisan::call('ei:publish-embargoed');
    expect($event->fresh()->status)->toBe('e');

    Carbon::setTestNow(Carbon::parse('2026-01-15 20:00:00', 'UTC')); // noon in Los Angeles
    Artisan::call('ei:publish-embargoed');
    expect($event->fresh()->status)->toBe('p');
});

test('the validator accepts an embargo that is still ahead where the event is', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 14:00:00', 'UTC'));

    // StoreEventRequest's own rules(), with the {event} route binding the
    // hosting update route provides.
    $rulesFor = function (array $data, ?Event $event = null) {
        $request = StoreEventRequest::create('/', 'POST', $data);
        $request->setContainer(app());
        $route = new Route('POST', '/', []);
        $route->bind($request);
        if ($event) {
            $route->setParameter('event', $event);
        }
        $request->setRouteResolver(fn () => $route);

        return Validator::make($data, $request->rules());
    };

    // The Dates step sends the timezone with the embargo: noon today in LA is
    // still six hours away, so it is a valid embargo (after:now in UTC would
    // have refused it).
    expect($rulesFor(['timezone' => 'America/Los_Angeles', 'embargo_date' => '2026-01-15 12:00:00'])->passes())->toBeTrue();

    // Noon today in Seoul was eleven hours ago.
    expect($rulesFor(['timezone' => 'Asia/Seoul', 'embargo_date' => '2026-01-15 12:00:00'])->errors()->first('embargo_date'))
        ->toBe('The embargo date must be in the future.');

    // An embargo sent on its own (no timezone in the save) is read in the
    // timezone of the event being edited.
    $la = Event::factory()->create(['timezone' => 'America/Los_Angeles']);
    $seoul = Event::factory()->create(['timezone' => 'Asia/Seoul']);
    expect($rulesFor(['embargo_date' => '2026-01-15 12:00:00'], $la)->passes())->toBeTrue()
        ->and($rulesFor(['embargo_date' => '2026-01-15 12:00:00'], $seoul)->passes())->toBeFalse();

    // A junk sibling timezone (array, number) must not crash the rule
    // (Codex review, 2026-09-14): it falls back to the event's timezone and
    // the timezone field's own rules report the junk.
    expect($rulesFor(['timezone' => ['Asia/Seoul'], 'embargo_date' => '2026-01-15 12:00:00'], $la)->errors()->has('embargo_date'))->toBeFalse()
        ->and($rulesFor(['timezone' => 42, 'embargo_date' => '2026-01-15 12:00:00'], $seoul)->errors()->first('embargo_date'))
        ->toBe('The embargo date must be in the future.');

    // Clearing an embargo is always fine; a malformed value is reported by
    // date_format, not by the future check.
    expect($rulesFor(['embargo_date' => null])->passes())->toBeTrue()
        ->and($rulesFor(['embargo_date' => 'next tuesday'])->errors()->first('embargo_date'))->toContain('format');
});
