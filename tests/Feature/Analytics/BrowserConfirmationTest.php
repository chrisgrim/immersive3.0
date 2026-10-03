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

test('a ping from an automated browser flags its page view', function () {
    noteFrom(Analytics::PAGE_VIEW, ['view_id' => 'viewAAAA0001', 'page' => 'home', 'path' => '/', 'js' => 0]);
    noteFrom(Analytics::PAGE_PING, ['view_id' => 'viewAAAA0001', 'webdriver' => 1]);

    $row = flushNow()->sole();

    expect($row->js)->toBe(1)->and($row->bot)->toBe(Analytics::BOT_AUTOMATION);
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
    expect($this->withoutVite()->get('/privacy')->getContent())->not->toContain('finished loading');

    switchOn('js_ping');
    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('finished loading');
});

test('the migration can run again', function () {
    $migration = require database_path('migrations/2026_10_04_120000_add_browser_confirmation_to_analytics.php');

    $migration->up();

    expect(Illuminate\Support\Facades\Schema::hasColumns('analytics_daily', ['js_visitors', 'engaged_visitors']))->toBeTrue();
});
