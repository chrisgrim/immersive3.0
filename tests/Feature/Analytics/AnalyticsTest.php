<?php

use App\Models\Event;
use App\Support\Analytics\Analytics;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeSearchEngine;

afterEach(fn () => Carbon::setTestNow());

function analyticsRows()
{
    test()->artisan('ei:analytics-flush')->assertSuccessful();

    return DB::table('analytics_events')->orderBy('id')->get();
}

const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

// ----- recording and flushing -----

test('a recorded note reaches analytics_events without the IP or user agent', function () {
    Analytics::record(Analytics::SEARCH, ['query' => 'Boise, ID', 'results' => 0, 'source' => 'list', 'props' => ['tags' => [3]]],
        Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => BROWSER_UA]));

    $rows = analyticsRows();

    expect($rows)->toHaveCount(1);
    $row = (array) $rows[0];
    expect($row['type'])->toBe('search')
        ->and($row['query'])->toBe('Boise, ID')
        ->and($row['results'])->toBe(0)
        ->and($row['bot'])->toBe(0)
        ->and(json_decode($row['props'], true))->toBe(['tags' => [3]])
        ->and(strlen($row['visitor']))->toBe(16);
    expect(json_encode($row))->not->toContain('203.0.113.9')->not->toContain('Chrome/129');
});

test('the same visitor hashes the same within a day and differently the next day', function () {
    $request = fn () => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => BROWSER_UA]);

    Carbon::setTestNow('2026-10-02 10:00:00');
    Analytics::record(Analytics::SEARCH, [], $request());
    Analytics::record(Analytics::SEARCH, [], $request());
    Carbon::setTestNow('2026-10-03 10:00:00');
    Analytics::record(Analytics::SEARCH, [], $request());

    $visitors = analyticsRows()->pluck('visitor');

    expect($visitors[0])->toBe($visitors[1])->and($visitors[2])->not->toBe($visitors[0]);
});

test('crawlers, empty user agents and over-the-cap visitors are flagged, not dropped', function () {
    config(['analytics.daily_cap' => 2]);
    $as = fn (string $ua, string $ip = '198.51.100.1') => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua]);

    Analytics::record(Analytics::SEARCH, [], $as('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
    Analytics::record(Analytics::SEARCH, [], $as(''));
    foreach (range(1, 3) as $i) {
        Analytics::record(Analytics::SEARCH, [], $as(BROWSER_UA, '198.51.100.2'));
    }

    expect(analyticsRows()->pluck('bot')->all())->toBe([
        Analytics::BOT_CRAWLER,
        Analytics::BOT_NO_USER_AGENT,
        0,
        0,
        Analytics::BOT_OVER_DAILY_CAP,
    ]);
});

test('the kill switch records nothing', function () {
    config(['analytics.enabled' => false]);
    Analytics::record(Analytics::SEARCH);
    config(['analytics.enabled' => true]);

    expect(analyticsRows())->toHaveCount(0);
});

test('a broken buffer never breaks the page', function () {
    config(['analytics.buffer' => 'redis', 'database.redis.default.port' => 1]);
    app()->forgetInstance('redis');
    Illuminate\Support\Facades\Redis::clearResolvedInstances();

    Analytics::record(Analytics::SEARCH);
})->throwsNoExceptions();

test('pruning deletes only rows older than the retention window', function () {
    DB::table('analytics_events')->insert([
        ['type' => 'search', 'occurred_at' => now()->subDays(181), 'visitor' => str_repeat('a', 16)],
        ['type' => 'search', 'occurred_at' => now()->subDays(10), 'visitor' => str_repeat('b', 16)],
    ]);

    $this->artisan('ei:analytics-prune')->assertSuccessful();

    expect(DB::table('analytics_events')->pluck('visitor')->all())->toBe([str_repeat('b', 16)]);
});

// ----- the search page -----

function analyticsLaQuery(): string
{
    return 'city=Los+Angeles%2C+CA&lat=34.0549076&lng=-118.242643&searchType=inPerson&live=false';
}

test('a search records the place, the result count and the filters', function () {
    FakeSearchEngine::install(Event::factory()->count(3)->published()->create()->pluck('id')->all());

    $this->getJson('/api/index/search?'.analyticsLaQuery().'&start=2026-10-10&end=2026-10-12')->assertOk();

    $row = (array) analyticsRows()->sole();
    expect($row['query'])->toBe('Los Angeles, CA')
        ->and($row['results'])->toBe(3)
        ->and($row['source'])->toBe('list')
        ->and(json_decode($row['props'], true))->toEqual([
            'searchType' => 'inPerson', 'lat' => 34.05, 'lng' => -118.24, 'start' => '2026-10-10', 'end' => '2026-10-12',
        ]);
});

test('a search that finds nothing is recorded with zero results', function () {
    FakeSearchEngine::install([]);

    $this->withoutVite()->get('/index/search?'.analyticsLaQuery())->assertOk();

    expect(analyticsRows()->sole()->results)->toBe(0);
});

test('Show more is the same search going deeper, so it is not recorded again', function () {
    FakeSearchEngine::install(Event::factory()->count(25)->published()->create()->pluck('id')->all());

    $this->getJson('/api/index/search?'.analyticsLaQuery().'&pages=2')->assertOk();

    expect(analyticsRows())->toHaveCount(0);
});

test('a failed insert keeps the notes for the next run', function () {
    Analytics::record(Analytics::SEARCH, ['query' => 'Kept']);
    Illuminate\Support\Facades\Schema::rename('analytics_events', 'analytics_events_away');

    try {
        test()->artisan('ei:analytics-flush');
    } catch (Throwable) {
        // The database error is expected; the notes are what matter.
    } finally {
        Illuminate\Support\Facades\Schema::rename('analytics_events_away', 'analytics_events');
    }

    expect(analyticsRows()->pluck('query')->all())->toBe(['Kept']);
});

test('analytics is off by default on staging', function () {
    $before = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null];
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'staging';
    try {
        $config = require config_path('analytics.php');
    } finally {
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = $before;
    }

    expect($config['enabled'])->toBeFalse();
});
