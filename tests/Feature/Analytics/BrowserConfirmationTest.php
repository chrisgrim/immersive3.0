<?php

use App\Support\Analytics\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

beforeEach(fn () => config(['analytics.capture_file' => storage_path('framework/testing/analytics-capture-'.uniqid().'.json')]));

afterEach(fn () => @unlink(config('analytics.capture_file')));

function switchOn(string ...$names): void
{
    foreach ($names as $name) {
        config(["analytics.capture.{$name}" => true]);
    }
    app(Analytics::class)->forgetOverrides();
}

function flushNow()
{
    test()->artisan('ei:analytics-flush')->assertSuccessful();

    return DB::table('analytics_events')->orderBy('id')->get();
}

/** A note as a browser on one address would leave it. */
function noteFrom(string $type, array $data): void
{
    Analytics::record($type, $data, Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '198.51.100.20', 'HTTP_USER_AGENT' => CHROME_UA,
        'HTTP_ACCEPT_LANGUAGE' => 'en-US', 'HTTP_SEC_FETCH_SITE' => 'none',
    ]));
}

// ----- the ping route -----

test('the load ping is recorded for a real view id, without a session', function () {
    switchOn('js_ping');

    $this->post('/api/analytics/page-ping', ['view_id' => 'abcDEF123456', 'wd' => '1'])->assertNoContent();
    $this->post('/api/analytics/page-ping', ['view_id' => 'bad'])->assertNoContent();
    $this->post('/api/analytics/page-ping', [])->assertNoContent();

    $notes = app(Analytics::class)->pop(10);
    expect($notes)->toHaveCount(1)
        ->and(json_decode($notes[0], true))->t->toBe('page_ping')->d->toBe(['view_id' => 'abcDEF123456', 'webdriver' => 1]);
});

test('with the switch off the load ping records nothing', function () {
    $this->post('/api/analytics/page-ping', ['view_id' => 'abcDEF123456', 'wd' => '0'])->assertNoContent();

    expect(app(Analytics::class)->pop(10))->toBe([]);
});

test('the ping route needs no CSRF token or session cookie', function () {
    switchOn('js_ping');

    $response = $this->withHeader('Origin', config('app.url'))->post('/api/analytics/page-ping', ['view_id' => 'abcDEF123456']);

    $response->assertNoContent();
    expect($response->headers->getCookies())->toBe([]);
});

test('the page hands its view id to the browser for the ping even with time on page off', function () {
    switchOn('page_views');
    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('analyticsView: null')->toContain('analyticsPing: false');

    switchOn('js_ping');
    expect($this->withoutVite()->get('/privacy')->getContent())
        ->toMatch('/analyticsView: "[A-Za-z0-9]{12}"/')
        ->toContain('analyticsDuration: false')
        ->toContain('analyticsPing: true');
});

// ----- flushing -----

test('a page view asked for a ping waits at js 0, one without stays null', function () {
    switchOn('page_views');
    $this->withoutVite()->get('/privacy')->assertOk();
    switchOn('js_ping');
    $this->withoutVite()->get('/privacy')->assertOk();

    expect(flushNow()->pluck('js')->all())->toBe([null, 0]);
});

test('a ping marks its page view as browser confirmed, and is not a row of its own', function () {
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0002', 'page' => 'home', 'path' => '/', 'js' => 0]);
    flushNow();

    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 0]);
    $rows = flushNow();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('js', 'view_id')->all())->toBe(['viewAAAA0001' => 1, 'viewAAAA0002' => 0])
        ->and($rows->pluck('bot')->all())->toBe([0, 0]);
});

test('a ping from an automated browser flags its visitor\'s whole day so far', function () {
    noteFrom(Analytics::SEARCH, ['query' => 'Boise, ID', 'source' => 'list', 'results' => 0]);
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    flushNow();
    // Someone else, the same day: untouched.
    Analytics::record(Analytics::PAGE_VIEW, ['view_id' => 'viewBBBB0001', 'page' => 'home', 'path' => '/', 'js' => 0], Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '198.51.100.99', 'HTTP_USER_AGENT' => CHROME_UA, 'HTTP_ACCEPT_LANGUAGE' => 'en-US', 'HTTP_SEC_FETCH_SITE' => 'none',
    ]));
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 1]);

    $rows = flushNow();

    expect($rows->pluck('bot', 'type')->all())->toBe(['search' => Analytics::BOT_AUTOMATION, 'page_view' => 0])
        ->and($rows->firstWhere('view_id', 'viewAAAA0001'))->js->toBe(1)->bot->toBe(Analytics::BOT_AUTOMATION)
        ->and($rows->firstWhere('view_id', 'viewBBBB0001'))->bot->toBe(0);
});

test('a ping ahead of its view in the same batch still marks it', function () {
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 0]);
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);

    expect(flushNow()->sole()->js)->toBe(1);
});

test('a ping whose view is not written yet is tried again on the next runs, then given up', function () {
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 1]);

    // Run 1: no view yet, the ping goes back.
    expect(flushNow())->toHaveCount(0);

    // Run 2: the view has arrived meanwhile.
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    $row = flushNow()->sole();
    expect($row->js)->toBe(1)->and($row->bot)->toBe(Analytics::BOT_AUTOMATION);

    // A ping whose view never comes is dropped after three runs.
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewNEVER001', 'webdriver' => 0]);
    flushNow();
    flushNow();
    $waiting = app(Analytics::class)->pop(10);
    expect($waiting)->toHaveCount(1)->and(json_decode($waiting[0], true)['r'])->toBe(2);
    app(Analytics::class)->putBack($waiting);
    flushNow();
    expect(app(Analytics::class)->pop(10))->toBe([]);
});

test('pings never count toward the daily cap', function () {
    config(['analytics.daily_cap' => 2]);
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    foreach (range(1, 5) as $i) {
        noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 0]);
    }
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0002', 'page' => 'home', 'path' => '/', 'js' => 0]);

    expect(flushNow()->pluck('bot')->all())->toBe([0, 0]);
});

test('the privacy page names the load ping while it is on', function () {
    expect($this->withoutVite()->get('/privacy')->getContent())->not->toContain('ran our page script');

    switchOn('js_ping');
    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('ran our page script');
});

test('the migration can run again', function () {
    $migration = require database_path('migrations/2026_10_04_120000_add_browser_confirmation_to_analytics.php');

    $migration->up();

    expect(Illuminate\Support\Facades\Schema::hasColumns('analytics_daily', ['js_visitors', 'engaged_visitors']))->toBeTrue();
});

// ----- daily totals -----

test('the rollup counts browser-confirmed and engaged visitor-days, for people only', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    $row = fn (string $visitor, array $values) => DB::table('analytics_events')->insert(array_merge([
        'type' => 'page_view', 'occurred_at' => "{$day} 12:00:00", 'visitor' => str_repeat($visitor, 16), 'bot' => 0,
        'page' => 'home', 'path' => '/', 'js' => 0, 'country' => 'US',
    ], $values));

    // a: one page, pinged: confirmed, not engaged.
    $row('a', ['js' => 1]);
    // b: one page, never pinged (a script).
    $row('b', []);
    // c: two pages, one pinged: confirmed and engaged.
    $row('c', ['js' => 1]);
    $row('c', ['path' => '/events/x', 'occurred_at' => "{$day} 12:01:00"]);
    // d: no ping, but a time-on-page beacon (any script can POST one): neither.
    $row('d', ['view_id' => 'viewDDDD0001']);
    $row('d', ['type' => 'page_leave', 'view_id' => 'viewDDDD0001', 'seconds' => 12, 'page' => null, 'path' => null, 'js' => null]);
    // e: one page, pinged, and a ticket click: confirmed and engaged.
    $row('e', ['js' => 1]);
    $row('e', ['type' => 'ticket_click', 'event_id' => 7, 'page' => null, 'path' => null, 'js' => null]);
    // y: pinged as automated, then a page written after the flag: neither.
    $row('y', ['js' => 1, 'bot' => Analytics::BOT_AUTOMATION]);
    $row('y', ['js' => 1, 'occurred_at' => "{$day} 12:02:00"]);
    // A bot, pinged: not in the people's counts.
    $row('z', ['js' => 1, 'bot' => Analytics::BOT_DATACENTER]);

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    $total = fn (string $dim, string $key, int $bot = 0) => DB::table('analytics_daily')
        ->where(['day' => $day, 'type' => 'view', 'dim' => $dim, 'key' => $key, 'bot' => $bot])->first();

    expect($total('all', ''))->visitors->toBe(6)->js_visitors->toBe(3)->engaged_visitors->toBe(2)
        ->and($total('country', 'US'))->js_visitors->toBe(3)->engaged_visitors->toBe(2)
        ->and($total('path', '/events/x'))->visitors->toBe(1)->js_visitors->toBe(1)->engaged_visitors->toBe(1)
        ->and($total('all', '', 1))->js_visitors->toBeNull()->engaged_visitors->toBeNull()
        ->and(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'ticket_click', 'dim' => 'all'])->first())
        ->engaged_visitors->toBe(1);
});

test('a day without the load ping has no browser-confirmed or engaged count', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    foreach (['a', 'a', 'b'] as $i => $visitor) {
        DB::table('analytics_events')->insert([
            'type' => 'page_view', 'occurred_at' => "{$day} 12:0{$i}:00", 'visitor' => str_repeat($visitor, 16), 'bot' => 0, 'page' => 'home', 'path' => '/',
        ]);
    }

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'all', 'bot' => 0])->first())
        ->visitors->toBe(2)->js_visitors->toBeNull()->engaged_visitors->toBeNull();
});

test('path edges carry their browser-confirmed and engaged visitors', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    foreach (range(1, 5) as $i) {
        $visitor = str_repeat((string) $i, 16);
        DB::table('analytics_events')->insert([
            ['type' => 'page_view', 'occurred_at' => "{$day} 10:00:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => $i <= 3 ? 1 : 0],
            ['type' => 'page_view', 'occurred_at' => "{$day} 10:05:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'events.show', 'path' => '/events/x', 'js' => 0],
        ]);
    }

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => $day, 'dim' => 'edge', 'key' => '/ > /events/x'])->first())
        ->visitors->toBe(5)->js_visitors->toBe(3)->engaged_visitors->toBe(3);
});

// ----- before the migration has run -----

test('flush, rollup and readers leave the new columns alone while they do not exist yet', function () {
    app(Analytics::class)->assumeConfirmationColumns(false);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 1]);
    Analytics::record(Analytics::PAGE_VIEW, ['view_id' => 'viewBBBB0001', 'page' => 'home', 'path' => '/'], Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '198.51.100.99', 'HTTP_USER_AGENT' => CHROME_UA, 'HTTP_ACCEPT_LANGUAGE' => 'en-US', 'HTTP_SEC_FETCH_SITE' => 'none',
    ]));
    $row = flushNow()->firstWhere('view_id', 'viewAAAA0001');
    $this->artisan('ei:analytics-rollup', ['--day' => 'today'])->assertSuccessful();
    $report = app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7);
    $trend = app(App\Actions\Analytics\AnalyticsQuery::class)->trend('confirmed_visits', null, 7);

    expect(collect($queries)->filter(fn ($sql) => preg_match('/`js`|\bjs (=|is)|js_visitors|engaged_visitors/i', $sql))->all())->toBe([])
        // A ping that cannot be recorded yet waits for a later run instead
        // of being used up (and the switch stays off until the columns exist).
        ->and($row->bot)->toBe(0)
        ->and(collect(app(Analytics::class)->pop(10))->filter(fn ($note) => str_contains($note, 'viewAAAA0001') && str_contains($note, Analytics::PAGE_PING)))->toHaveCount(1)
        ->and(Analytics::pingsOn())->toBeFalse()
        ->and($report['totals']['people']['confirmed_visitors'])->toBeNull()
        ->and($trend)->toMatchArray(['measured_since' => null, 'series' => []]);
});

test('rows of an automated visitor-day that come after its ping are flagged as they are written', function () {
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 1]);
    flushNow();

    noteFrom(Analytics::SEARCH, ['query' => 'Boise, ID', 'source' => 'list', 'results' => 0]);

    expect(flushNow()->firstWhere('type', 'search')->bot)->toBe(Analytics::BOT_AUTOMATION);
});

test('the load ping has its own, roomier rate limit', function () {
    switchOn('js_ping');

    foreach (range(1, 70) as $i) {
        $this->post('/api/analytics/page-ping', ['view_id' => 'abcDEF123456'])->assertNoContent();
    }
});

test('a time-on-page note just after midnight counts for its view\'s visitor, not as a visitor of its own', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    $next = now('UTC')->subDay()->toDateString();
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => "{$day} 23:58:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1, 'view_id' => 'viewAAAA0001', 'seconds' => null],
        // The leave comes under the next day's visitor code.
        ['type' => 'page_leave', 'occurred_at' => "{$next} 00:02:00", 'visitor' => str_repeat('n', 16), 'bot' => 0, 'page' => null, 'path' => null, 'js' => null, 'view_id' => 'viewAAAA0001', 'seconds' => 240],
    ]);
    $this->artisan('ei:analytics-rollup', ['--from' => $day, '--to' => $next])->assertSuccessful();
    $report = app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7);

    expect(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'all', 'bot' => 0])->first())
        ->visitors->toBe(1)->js_visitors->toBe(1)->engaged_visitors->toBe(1)
        ->and($report['totals']['people'])->toMatchArray(['visitors' => 1, 'visitors_on_measured_days' => 1, 'confirmed_visitors' => 1, 'engaged_visitors' => 1]);
});

test('confirmed and engaged counts leave out days not yet marked measured, like today before its rollup', function () {
    $yesterday = now('UTC')->subDay();
    foreach ([[$yesterday, 'a'], [now('UTC'), 't']] as [$at, $visitor]) {
        DB::table('analytics_events')->insert([
            ['type' => 'page_view', 'occurred_at' => $at->copy()->startOfDay()->addHour(), 'visitor' => str_repeat($visitor, 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
            ['type' => 'page_view', 'occurred_at' => $at->copy()->startOfDay()->addHours(2), 'visitor' => str_repeat($visitor, 16), 'bot' => 0, 'page' => 'home', 'path' => '/x', 'js' => 0],
        ]);
    }
    // Yesterday rolled up; today's hourly rollup has not run yet.
    $this->artisan('ei:analytics-rollup', ['--day' => $yesterday->toDateString()])->assertSuccessful();

    expect(app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7)['totals']['people'])
        ->toMatchArray(['visitors' => 2, 'visitors_on_measured_days' => 1, 'confirmed_visitors' => 1, 'engaged_visitors' => 1]);
});

test('the day the ping is switched on mid-day is not measured; measuring starts with the next whole day', function () {
    $on = now('UTC')->subDays(3)->toDateString();
    $next = now('UTC')->subDays(2)->toDateString();
    DB::table('analytics_events')->insert([
        // Morning, before the switch: not asked. Afternoon: pinged.
        ['type' => 'page_view', 'occurred_at' => "{$on} 08:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => null],
        ['type' => 'page_view', 'occurred_at' => "{$on} 15:00:00", 'visitor' => str_repeat('b', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
        ['type' => 'page_view', 'occurred_at' => "{$next} 08:00:00", 'visitor' => str_repeat('c', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
    ]);

    $this->artisan('ei:analytics-rollup', ['--from' => $on, '--to' => $next])->assertSuccessful();

    $all = fn (string $day) => DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'all', 'bot' => 0])->first();
    expect($all($on))->visitors->toBe(2)->js_visitors->toBeNull()->engaged_visitors->toBeNull()
        ->and($all($next))->js_visitors->toBe(1)
        ->and(app(App\Actions\Analytics\AnalyticsQuery::class)->trend('confirmed_visits', null, 7)['measured_since'])->toBe($next)
        ->and(app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7)['totals']['people'])
        ->toMatchArray(['visitors' => 3, 'visitors_on_measured_days' => 1, 'confirmed_visitors' => 1, 'measured_since' => $next]);
});

test('the report\'s measured base is view visitor-days, and a beacon alone is no country visit', function () {
    $day = now('UTC')->subDay()->toDateString();
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => "{$day} 10:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'country' => 'US', 'source' => null, 'view_id' => null, 'js' => 1],
        // Searched, never opened a page: a person, not a visit.
        ['type' => 'search', 'occurred_at' => "{$day} 10:00:00", 'visitor' => str_repeat('s', 16), 'bot' => 0, 'country' => 'US', 'source' => 'list', 'view_id' => null, 'js' => null],
        // A leave whose view is not in range, under its own visitor code.
        ['type' => 'page_leave', 'occurred_at' => "{$day} 00:02:00", 'visitor' => str_repeat('l', 16), 'bot' => 0, 'country' => 'GB', 'source' => null, 'view_id' => 'viewGONE0001', 'js' => null],
    ]);
    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    $report = app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7);

    expect($report['totals']['people'])->toMatchArray(['visitors' => 2, 'visitors_on_measured_days' => 1, 'confirmed_visitors' => 1])
        ->and($report['countries'])->toBe(['US' => ['visitors' => 2, 'visitors_on_measured_days' => 1, 'confirmed' => 1]]);
});

test('the day the ping is switched off mid-day is not measured either', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    DB::table('analytics_events')->insert([
        // Morning: pinged. Afternoon, after the switch-off: not asked.
        ['type' => 'page_view', 'occurred_at' => "{$day} 08:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
        ['type' => 'page_view', 'occurred_at' => "{$day} 15:00:00", 'visitor' => str_repeat('b', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => null],
    ]);

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'all', 'bot' => 0])->first())
        ->visitors->toBe(2)->js_visitors->toBeNull()->engaged_visitors->toBeNull();
});

test('once measuring has begun, the top countries are the top by visits on measured days', function () {
    $day = now('UTC')->subDay()->toDateString();
    $old = now('UTC')->subDays(3)->toDateString();
    $view = fn (string $visitor, string $country, string $at, $js) => DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => $at, 'visitor' => str_repeat($visitor, 16), 'bot' => 0, 'country' => $country, 'js' => $js]);
    // GB: three visits before measuring. US: two on the measured day.
    foreach (['a', 'b', 'c'] as $visitor) {
        $view($visitor, 'GB', "{$old} 10:00:00", null);
    }
    $view('d', 'US', "{$day} 10:00:00", 1);
    $view('e', 'US', "{$day} 11:00:00", 0);
    $this->artisan('ei:analytics-rollup', ['--from' => $old, '--to' => $day])->assertSuccessful();

    expect(array_keys(app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7)['countries']))->toBe(['US', 'GB']);
});

test('measured counts compare whole days with the same number of whole days before', function () {
    // 7 days: the current period's whole days are the 6 before today; the
    // period before is the 6 whole days before those.
    foreach ([0 => 100, 1 => 10, 6 => 20, 7 => 30, 12 => 40, 13 => 500] as $ago => $visitors) {
        DB::table('analytics_daily')->insert(['day' => now('UTC')->subDays($ago)->toDateString(), 'type' => 'view', 'dim' => 'all', 'key' => '', 'bot' => 0,
            'visitors' => $visitors, 'js_visitors' => $visitors / 2, 'engaged_visitors' => $visitors / 10]);
    }
    // A person today, so the people line exists in both periods' raw rows.
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => now('UTC'), 'visitor' => str_repeat('a', 16), 'bot' => 0, 'js' => 1],
        ['type' => 'page_view', 'occurred_at' => now('UTC')->subDays(8), 'visitor' => str_repeat('b', 16), 'bot' => 0, 'js' => 1],
    ]);

    $report = app(App\Actions\Analytics\SiteAnalyticsReport::class)->handle(7);

    // Today (partial) and the day 13 back (outside both) are left out.
    expect($report['totals']['people'])->toMatchArray(['visitors_on_measured_days' => 30, 'confirmed_visitors' => 15, 'engaged_visitors' => 3, 'measured_since' => now('UTC')->subDays(6)->toDateString()])
        ->and($report['totals_previous']['people'])->toMatchArray(['visitors_on_measured_days' => 70, 'confirmed_visitors' => 35, 'engaged_visitors' => 7, 'measured_since' => now('UTC')->subDays(12)->toDateString()]);
});

test('an automated browser found by its ping leaves the live count', function () {
    switchOn('live');
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewLIVE0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    flushNow();
    expect(app(Analytics::class)->liveCount())->toBe(1);

    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewLIVE0001', 'webdriver' => 1]);
    flushNow();

    expect(app(Analytics::class)->liveCount())->toBe(0);
});

test('a day the ping was on for only part of keeps the privacy page naming it after the raw rows are gone', function () {
    switchOn('page_views');
    config(['analytics.capture.js_ping' => false]);
    $day = now('UTC')->subDays(20)->toDateString();
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => "{$day} 08:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
        ['type' => 'page_view', 'occurred_at' => "{$day} 15:00:00", 'visitor' => str_repeat('b', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => null],
    ]);
    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();
    // Not a measured day, but the capture was on: a marker row says so.
    expect(DB::table('analytics_daily')->where(['day' => $day, 'dim' => 'capture', 'type' => 'js_ping'])->exists())->toBeTrue();

    Illuminate\Support\Facades\Cache::flush();
    expect(Analytics::recentlyCaptured('js_ping'))->toBeTrue();
});

test('the load ping stays off while its columns do not exist, even when switched on', function () {
    switchOn('page_views', 'js_ping');
    app(Analytics::class)->assumeConfirmationColumns(false);
    Illuminate\Support\Facades\Cache::forget('analytics:confirmation-columns');
    expect(Analytics::pingsOn())->toBeFalse();

    app(Analytics::class)->assumeConfirmationColumns(true);
    Illuminate\Support\Facades\Cache::forget('analytics:confirmation-columns');
    expect(Analytics::pingsOn())->toBeTrue();
});

test('pings wait however many runs it takes for the js column', function () {
    app(Analytics::class)->assumeConfirmationColumns(false);
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewWAIT0001', 'webdriver' => 0]);

    foreach (range(1, 5) as $run) {
        flushNow();
    }

    expect(collect(app(Analytics::class)->pop(10))->filter(fn ($note) => str_contains($note, 'viewWAIT0001')))->toHaveCount(1);
});

test('a day with event views from while page views were off is not a measured day', function () {
    $day = now('UTC')->subDays(2)->toDateString();
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => "{$day} 15:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'path' => '/', 'js' => 1],
        ['type' => 'event_view', 'occurred_at' => "{$day} 08:00:00", 'visitor' => str_repeat('b', 16), 'bot' => 0, 'page' => null, 'path' => null, 'js' => null],
    ]);

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'all', 'bot' => 0])->first())
        ->visitors->toBe(2)->js_visitors->toBeNull();
});
