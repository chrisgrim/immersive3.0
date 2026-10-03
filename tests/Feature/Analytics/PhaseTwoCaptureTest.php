<?php

use App\Models\Event;
use App\Models\User;
use App\Support\Analytics\Analytics;
use App\Support\Analytics\GeoLookup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

beforeEach(fn () => config(['analytics.capture_file' => storage_path('framework/testing/analytics-capture-'.uniqid().'.json')]));

afterEach(function () {
    Carbon::setTestNow();
    @unlink(config('analytics.capture_file'));
});

/** A UTC day $n days ago, Y-m-d: rollups refuse days older than the bot rows are kept. */
function daysAgo(int $n): string
{
    return now('UTC')->subDays($n)->toDateString();
}

function captureOn(string ...$names): void
{
    foreach ($names as $name) {
        config(["analytics.capture.{$name}" => true]);
    }
    app(Analytics::class)->forgetOverrides();
}

function flushedRows()
{
    test()->artisan('ei:analytics-flush')->assertSuccessful();

    return DB::table('analytics_events')->orderBy('id')->get();
}

function showableEvent(): Event
{
    $organizer = App\Models\Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id, 'closingDate' => now()->addDays(30), 'hasLocation' => true]);
    App\Models\Events\Location::factory()->create(['event_id' => $event->id]);
    App\Models\Events\Show::factory()->create(['event_id' => $event->id]);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

// ----- switches -----

test('every phase 2 capture is off by default, and nothing new is recorded', function () {
    $this->withoutVite()->get('/privacy')->assertOk();

    expect(collect(config('analytics.capture'))->filter()->all())->toBe([])
        ->and(flushedRows())->toHaveCount(0);
});

test('the capture command switches one on and off without a deploy', function () {
    $this->artisan('ei:analytics-capture page_views on')->assertSuccessful();
    expect(Analytics::captures('page_views'))->toBeTrue();

    $this->artisan('ei:analytics-capture page_views default')->assertSuccessful();
    expect(Analytics::captures('page_views'))->toBeFalse();

    $this->artisan('ei:analytics-capture nonsense on')->assertFailed();
});

// ----- page views -----

test('a public page view is recorded after the response, with page, path, source and campaign', function () {
    captureOn('page_views', 'utm');

    $this->withoutVite()->get('/privacy?utm_source=Newsletter&utm_campaign=Fall%20Shows!', ['Referer' => 'https://www.google.com/'])->assertOk();

    $row = flushedRows()->sole();
    expect($row)->type->toBe('page_view')
        ->page->toBe('privacy')
        ->path->toBe('/privacy')
        ->source->toBe('search_engine')
        ->utm_source->toBe('newsletter')
        ->utm_campaign->toBe('fall shows')
        ->and($row->view_id)->toMatch(Analytics::SEARCH_ID_PATTERN);
});

test('an event page becomes one page view with its event and organizer, not an extra event view', function () {
    captureOn('page_views');
    $event = showableEvent();

    $this->withoutVite()->get("/events/{$event->slug}")->assertOk();

    $row = flushedRows()->sole();
    expect($row)->type->toBe('page_view')->page->toBe('events.show')
        ->event_id->toBe($event->id)->organizer_id->toBe($event->organizer_id);
});

test('404s, JSON requests, prefetches, opted-out browsers and pages off the list are not page views', function () {
    captureOn('page_views');

    $this->withoutVite()->get('/events/no-such-event')->assertNotFound();
    $this->withoutVite()->get('/privacy', ['Sec-Purpose' => 'prefetch'])->assertOk();
    $this->withoutVite()->get('/privacy', ['Sec-GPC' => '1'])->assertOk();
    $this->actingAs(User::factory()->create(['type' => 'u']))->withoutVite()->get('/hosting/getting-started');

    expect(flushedRows()->where('type', 'page_view'))->toHaveCount(0);
});

// ----- device, city, live -----

test('device, browser and OS families are worked out at flush, for people only', function () {
    captureOn('page_views', 'device');

    $this->withoutVite()->get('/privacy', ['User-Agent' => PHONE_UA])->assertOk();
    $this->withoutVite()->get('/privacy', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();

    [$person, $bot] = flushedRows()->all();
    expect($person)->device->toBe('mobile')->os->toBe('iOS')
        ->and($person->browser)->not->toBeNull()
        ->and($bot->device)->toBeNull();
});

test('city and region come from the IP at flush, never coordinates', function () {
    captureOn('page_views', 'city');
    app()->instance(GeoLookup::class, new class extends GeoLookup
    {
        public function place(string $ip): array
        {
            return ['city' => 'Portland', 'region' => 'Oregon'];
        }
    });

    $this->withoutVite()->get('/privacy', ['User-Agent' => PHONE_UA])->assertOk();

    expect(flushedRows()->sole())->city->toBe('Portland')->region->toBe('Oregon');
});

test('the live count is people who viewed a page in the last five minutes', function () {
    captureOn('page_views', 'live');

    $this->withoutVite()->get('/privacy', ['User-Agent' => PHONE_UA])->assertOk();
    flushedRows();

    expect(app(Analytics::class)->liveCount())->toBe(1);
    Carbon::setTestNow(now()->addMinutes(6));
    expect(app(Analytics::class)->liveCount())->toBe(0);
});

// ----- time on page -----

test('the time-on-page beacon is recorded only for a real view id, capped, without a session', function () {
    captureOn('duration');

    $this->post('/api/analytics/page-leave', ['view_id' => 'abcDEF123456', 'seconds' => 99999, 'depth' => 40])->assertNoContent();
    $this->post('/api/analytics/page-leave', ['view_id' => 'bad', 'seconds' => 10])->assertNoContent();
    $this->post('/api/analytics/page-leave', ['view_id' => 'abcDEF123456', 'seconds' => 'x'])->assertNoContent();

    expect(flushedRows()->sole())->type->toBe('page_leave')->view_id->toBe('abcDEF123456')->seconds->toBe(1800)->depth->toBe(40);
});

test('the page hands its view id to the browser only while time on page is measured', function () {
    captureOn('page_views');
    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('analyticsView: null');

    captureOn('duration');
    expect($this->withoutVite()->get('/privacy')->getContent())->toMatch('/analyticsView: "[A-Za-z0-9]{12}"/');

    // A page that turns out to be a 404 records no view, so it sends no beacon.
    $gone = $this->withoutVite()->get('/events/no-such-event');
    expect($gone->status())->toBe(404)->and($gone->getContent())->not->toMatch('/analyticsView: "/');
});

// ----- nav search -----

test('nav search text is recorded from the public nav only, cleaned', function () {
    captureOn('nav_search');
    Tests\Support\FakeSearchEngine::install([]);

    $this->getJson('/api/search/nav/names?nav=1&keywords='.urlencode('Sleep No More'))->assertOk();
    $this->getJson('/api/search/nav/organizers?nav=1&keywords='.urlencode('me@example.com'))->assertOk();
    $this->getJson('/api/search/nav/events?keywords=admin+lookup')->assertOk();
    $this->getJson('/api/search/nav/names?nav=1&keywords=a')->assertOk();

    expect(flushedRows()->map(fn ($row) => [$row->type, $row->source, $row->query])->all())->toBe([
        ['nav_search', 'names', 'Sleep No More'],
        ['nav_search', 'organizers', '[removed]'],
    ]);
});

// ----- daily totals and pruning -----

test('the daily rollup adds a day up by dimension, with time on page and paths, and re-runs cleanly', function () {
    $day = daysAgo(2);
    $row = fn (array $values) => DB::table('analytics_events')->insert(array_merge([
        'type' => 'page_view', 'occurred_at' => "{$day} 12:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0,
    ], $values));

    foreach (range(1, 5) as $i) {
        $visitor = str_repeat((string) $i, 16);
        $row(['visitor' => $visitor, 'page' => 'home', 'path' => '/', 'occurred_at' => "{$day} 10:00:0{$i}", 'view_id' => "viewhome000{$i}", 'device' => 'mobile']);
        $row(['visitor' => $visitor, 'page' => 'events.show', 'path' => '/events/x', 'event_id' => 7, 'occurred_at' => "{$day} 10:05:0{$i}", 'utm_source' => 'newsletter']);
    }
    $row(['type' => 'page_leave', 'view_id' => 'viewhome0001', 'seconds' => 30, 'occurred_at' => "{$day} 10:01:00"]);
    $row(['page' => 'home', 'path' => '/', 'bot' => 1, 'visitor' => str_repeat('z', 16)]);

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();
    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    $total = fn (string $dim, string $key, int $bot = 0) => DB::table('analytics_daily')
        ->where(['day' => $day, 'type' => 'view', 'dim' => $dim, 'key' => $key, 'bot' => $bot])->first();

    expect($total('all', ''))->hits->toBe(10)->visitors->toBe(5)
        ->and($total('all', '', 1))->hits->toBe(1)
        ->and($total('page', 'home'))->hits->toBe(5)->seconds_sum->toBe(30)->seconds_count->toBe(1)
        ->and($total('event', '7'))->hits->toBe(5)
        ->and($total('device', 'mobile'))->hits->toBe(5)
        ->and($total('utm_source', 'newsletter'))->hits->toBe(5)
        ->and($total('edge', '/ > /events/x'))->visitors->toBe(5)
        ->and(DB::table('analytics_daily')->where('type', 'page_leave')->count())->toBe(0);
});

test('a path taken by fewer than five people is not kept', function () {
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => daysAgo(2).' 10:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0, 'path' => '/'],
        ['type' => 'page_view', 'occurred_at' => daysAgo(2).' 10:01:00', 'visitor' => str_repeat('a', 16), 'bot' => 0, 'path' => '/help'],
    ]);

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(2)])->assertSuccessful();

    expect(DB::table('analytics_daily')->where('dim', 'edge')->count())->toBe(0);
});

test('bot rows are pruned after 30 days, people after 13 months', function () {
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => now()->subDays(31), 'visitor' => str_repeat('a', 16), 'bot' => 1],
        ['type' => 'page_view', 'occurred_at' => now()->subDays(31), 'visitor' => str_repeat('b', 16), 'bot' => 0],
        ['type' => 'page_view', 'occurred_at' => now()->subDays(400), 'visitor' => str_repeat('c', 16), 'bot' => 0],
    ]);

    $this->artisan('ei:analytics-prune')->assertSuccessful();

    expect(DB::table('analytics_events')->pluck('visitor')->all())->toBe([str_repeat('b', 16)]);
});

// ----- privacy page -----

test('the privacy page names each thing recorded, only while it is switched on', function () {
    $sentences = [
        'page_views' => 'which pages of the site you view',
        'device' => 'your kind of device',
        'city' => 'your city and region',
        'utm' => 'campaign tags',
        'duration' => 'how long a page was on your screen',
        'nav_search' => 'what you type into the search bar',
        'live' => 'how many people are on the site',
    ];

    $off = $this->withoutVite()->get('/privacy')->getContent();
    foreach ($sentences as $text) {
        expect($off)->not->toContain($text);
    }

    captureOn(...array_keys($sentences));
    $on = $this->withoutVite()->get('/privacy')->getContent();
    foreach ($sentences as $text) {
        expect($on)->toContain($text);
    }
});

// ----- review fixes (phase 2, round 1) -----

test('referrers that differ only by accent or case roll up as one key instead of breaking the day', function () {
    foreach (['café.com', 'cafe.com', 'CAFE.com'] as $i => $ref) {
        DB::table('analytics_events')->insert(['type' => 'event_view', 'occurred_at' => daysAgo(2).' 10:00:00', 'visitor' => str_repeat((string) $i, 16), 'bot' => 0, 'event_id' => 1, 'props' => json_encode(['ref' => $ref])]);
    }

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(2)])->assertSuccessful();

    expect((int) DB::table('analytics_daily')->where(['dim' => 'ref'])->sum('hits'))->toBe(3)
        ->and(DB::table('analytics_daily')->where(['dim' => 'ref'])->count())->toBe(1);
});

test('nav search counts the finished text, not every pause while typing', function () {
    foreach (['sl' => '10:00:00', 'slee' => '10:00:01', 'sleep no' => '10:00:02', 'zoo' => '10:05:00'] as $query => $time) {
        DB::table('analytics_events')->insert(['type' => 'nav_search', 'occurred_at' => daysAgo(2)." {$time}", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'query' => $query, 'source' => 'names']);
    }
    DB::table('analytics_events')->insert(['type' => 'nav_search', 'occurred_at' => daysAgo(2).' 10:00:00', 'visitor' => str_repeat('b', 16), 'bot' => 0, 'query' => '50%_off', 'source' => 'names']);

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(2)])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['type' => 'nav_search', 'dim' => 'query'])->orderBy('key')->pluck('hits', 'key')->all())
        ->toBe(['50%_off' => 1, 'sleep no' => 1, 'zoo' => 1])
        ->and(DB::table('analytics_daily')->where(['type' => 'nav_search', 'dim' => 'all'])->value('hits'))->toBe(3);
});

test('one day failing does not stop the other days from rolling up', function () {
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => daysAgo(1).' 10:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0, 'path' => '/']);

    $command = Mockery::mock(App\Console\Commands\AnalyticsRollup::class.'[rollupDay]', []);
    $command->shouldReceive('rollupDay')->andReturnUsing(function ($day) {
        if ($day->toDateString() === daysAgo(2)) {
            throw new RuntimeException('boom');
        }
        (new App\Console\Commands\AnalyticsRollup)->rollupDay($day);
    });
    $command->setLaravel(app());
    app(Illuminate\Contracts\Console\Kernel::class)->registerCommand($command);

    $this->artisan('ei:analytics-rollup', ['--from' => daysAgo(2), '--to' => daysAgo(1)])->assertFailed();

    expect(DB::table('analytics_daily')->where('day', daysAgo(1))->exists())->toBeTrue();
});

test('the prune never deletes recent bot rows, even with an old config cache', function () {
    config(['analytics.bot_raw_days' => null, 'analytics.raw_days' => null]);
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => now()->subHour(), 'visitor' => str_repeat('a', 16), 'bot' => 1]);

    $this->artisan('ei:analytics-prune')->assertSuccessful();

    expect(DB::table('analytics_events')->count())->toBe(1);
});

test('the migration can run again after stopping halfway', function () {
    $migration = require database_path('migrations/2026_10_03_120000_add_page_view_fields_to_analytics_events.php');

    $migration->up();

    expect(Illuminate\Support\Facades\Schema::hasColumn('analytics_events', 'utm_campaign'))->toBeTrue();
});

test('analytics-for finds an event whose slug is all digits', function () {
    $event = Event::factory()->published()->create(['slug' => '1003788030']);
    DB::table('analytics_daily')->insert(['day' => now()->subDay()->toDateString(), 'type' => 'view', 'dim' => 'event', 'key' => (string) $event->id, 'bot' => 0, 'hits' => 4, 'visitors' => 3, 'seconds_sum' => 0, 'seconds_count' => 0]);
    $moderator = User::factory()->create(['type' => 'm']);
    Laravel\Passport\Passport::actingAs($moderator, ['mcp:use', User::MODERATE_SCOPE]);

    App\Mcp\Servers\EiServer::actingAs($moderator, 'api')->tool(App\Mcp\Tools\AnalyticsFor::class, ['event' => '1003788030'])
        ->assertOk()->assertSee('"page_views":4', false);
});

// ----- review fixes (phase 2, round 2) -----

test('a person whose event view and page view fall on the same day is one visitor, and map pans are not searches', function () {
    $row = fn (array $values) => DB::table('analytics_events')->insert(array_merge(['occurred_at' => daysAgo(2).' 10:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0], $values));
    $row(['type' => 'event_view', 'event_id' => 7]);
    $row(['type' => 'page_view', 'page' => 'events.show', 'event_id' => 7, 'path' => '/events/x']);
    $row(['type' => 'search', 'source' => 'list', 'query' => 'Austin']);
    $row(['type' => 'search', 'source' => 'map', 'query' => 'Austin']);

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(2)])->assertSuccessful();

    $all = fn (string $type) => DB::table('analytics_daily')->where(['day' => daysAgo(2), 'type' => $type, 'dim' => 'all', 'bot' => 0])->first();
    expect($all('view'))->hits->toBe(2)->visitors->toBe(1)
        ->and($all('search')->hits)->toBe(1)
        ->and($all('map_search')->hits)->toBe(1);
});

test('typed text in the daily totals that fewer than three people shared goes after 13 months', function () {
    $old = now()->subDays(400)->toDateString();
    foreach ([['query', 'my home address', 1], ['query', 'Austin', 5], ['page', 'home', 1]] as [$dim, $key, $visitors]) {
        DB::table('analytics_daily')->insert(['day' => $old, 'type' => 'search', 'dim' => $dim, 'key' => $key, 'bot' => 0, 'hits' => $visitors, 'visitors' => $visitors, 'seconds_sum' => 0, 'seconds_count' => 0]);
    }

    $this->artisan('ei:analytics-prune')->assertSuccessful();

    expect(DB::table('analytics_daily')->orderBy('key')->pluck('key')->all())->toBe(['Austin', 'home']);
});

test('the capture switches live in a file, not the evictable cache', function () {
    $this->artisan('ei:analytics-capture page_views on')->assertSuccessful();

    Cache::flush();
    app(Analytics::class)->forgetOverrides();

    expect(Analytics::captures('page_views'))->toBeTrue()
        ->and(json_decode(file_get_contents(config('analytics.capture_file')), true))->toBe(['page_views' => true]);
});

test('the live tool will not answer zero while page views are off', function () {
    config(['analytics.capture.live' => true]);
    app(Analytics::class)->forgetOverrides();
    $moderator = User::factory()->create(['type' => 'm']);
    Laravel\Passport\Passport::actingAs($moderator, ['mcp:use', User::MODERATE_SCOPE]);

    App\Mcp\Servers\EiServer::actingAs($moderator, 'api')->tool(App\Mcp\Tools\AnalyticsLive::class)->assertHasErrors()->assertSee('page_views');
});

test('rebuilding a day older than the bot rows are kept is refused, so its totals survive', function () {
    $old = now()->subDays(40)->toDateString();
    DB::table('analytics_daily')->insert(['day' => $old, 'type' => 'view', 'dim' => 'all', 'key' => '', 'bot' => 1, 'hits' => 50, 'visitors' => 40, 'seconds_sum' => 0, 'seconds_count' => 0]);

    $this->artisan('ei:analytics-rollup', ['--day' => $old])->assertSuccessful();

    expect(DB::table('analytics_daily')->where('day', $old)->value('hits'))->toBe(50);
});

// ----- review fixes (phase 2, round 5) -----

test('nav keystrokes and time-on-page notes do not push a person over the daily cap', function () {
    config(['analytics.daily_cap' => 3]);
    $as = fn () => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => PHONE_UA]);
    foreach (range(1, 10) as $i) {
        Analytics::record(Analytics::NAV_SEARCH, ['query' => str_repeat('s', $i + 1)], $as());
        Analytics::record(Analytics::PAGE_LEAVE, ['view_id' => 'abcDEF12345'.($i % 10), 'seconds' => 10], $as());
    }
    Analytics::record(Analytics::PAGE_VIEW, ['page' => 'home', 'path' => '/'], $as());

    expect(flushedRows()->where('bot', '>', 0))->toHaveCount(0);
});

test('a weekly trend covers exactly the period, its first week starting with it', function () {
    $moderator = User::factory()->create(['type' => 'm']);
    Laravel\Passport\Passport::actingAs($moderator, ['mcp:use', User::MODERATE_SCOPE]);
    $since = now('UTC')->subDays(119)->startOfDay();
    $before = $since->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->subDay();
    foreach ([[$before, 5], [$since, 9]] as [$day, $hits]) {
        DB::table('analytics_daily')->insert(['day' => $day->toDateString(), 'type' => 'view', 'dim' => 'all', 'key' => '', 'bot' => 0, 'hits' => $hits, 'visitors' => $hits, 'seconds_sum' => 0, 'seconds_count' => 0]);
    }

    App\Mcp\Servers\EiServer::actingAs($moderator, 'api')->tool(App\Mcp\Tools\AnalyticsTrend::class, ['metric' => 'page_views', 'days' => 120])
        ->assertOk()->assertSee('"series":[["'.$since->toDateString().'",9]]', false);
});

// ----- review fixes (phase 2, round 5, Fable) -----

test('one page spelled differently in its address is one path', function () {
    captureOn('page_views');

    $this->withoutVite()->get('/privacy')->assertOk();
    $this->withoutVite()->get('/%70rivacy')->assertOk();

    expect(flushedRows()->pluck('path')->unique()->values()->all())->toBe(['/privacy']);
});

test('an event page records its real slug as the path', function () {
    captureOn('page_views');
    $event = showableEvent();

    $this->withoutVite()->get('/events/'.rawurlencode($event->slug).'%00')->assertSuccessful();

    expect(flushedRows()->pluck('path')->filter()->unique()->values()->all())->toBe(['/events/'.$event->slug]);
})->skip(fn () => false);

test('open-ended text totals hold people only, empty places are left out, and old event views count for their organizer', function () {
    $event = Event::factory()->published()->create();
    $row = fn (array $values) => DB::table('analytics_events')->insert(array_merge(['occurred_at' => daysAgo(1).' 10:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0], $values));
    $row(['type' => 'page_view', 'page' => 'home', 'path' => '/junk-'.uniqid(), 'bot' => 1]);
    $row(['type' => 'search', 'source' => 'list', 'query' => '']);
    $row(['type' => 'event_view', 'event_id' => $event->id]);

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(1)])->assertSuccessful();

    expect(DB::table('analytics_daily')->where('dim', 'path')->count())->toBe(0)
        ->and(DB::table('analytics_daily')->where('dim', 'query')->count())->toBe(0)
        ->and(DB::table('analytics_daily')->where(['dim' => 'organizer', 'key' => (string) $event->organizer_id])->value('hits'))->toBe(1);
});

test('time on page counts when the leave lands just after midnight UTC', function () {
    $day = daysAgo(3);
    $next = daysAgo(2);
    DB::table('analytics_events')->insert([
        ['type' => 'page_view', 'occurred_at' => "{$day} 23:59:30", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home', 'view_id' => 'lateview0001', 'seconds' => null],
        ['type' => 'page_leave', 'occurred_at' => "{$next} 00:00:20", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => null, 'view_id' => 'lateview0001', 'seconds' => 50],
    ]);

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => $day, 'type' => 'view', 'dim' => 'page', 'key' => 'home'])->first())
        ->seconds_sum->toBe(50)->seconds_count->toBe(1);
});

test('a loop of nav typing is caught by its own cap', function () {
    config(['analytics.nav_daily_cap' => 3]);
    $as = fn () => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => PHONE_UA]);
    foreach (range(1, 4) as $i) {
        Analytics::record(Analytics::NAV_SEARCH, ['query' => "word {$i}"], $as());
    }

    expect(flushedRows()->pluck('bot')->all())->toBe([0, 0, 0, Analytics::BOT_OVER_DAILY_CAP]);
});

test('the nightly rollup builds every day with raw rows but no totals, after deploy or a missed run', function () {
    foreach ([daysAgo(20), daysAgo(0)] as $day) {
        DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => "{$day} 12:00:00", 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home']);
    }
    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(0)])->assertSuccessful();

    $this->artisan('ei:analytics-rollup')->assertSuccessful();
    expect(DB::table('analytics_daily')->where(['day' => daysAgo(20), 'dim' => 'all'])->exists())->toBeTrue();

    // A day missed later (scheduler down) is filled by the next nightly run.
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => daysAgo(10).' 12:00:00', 'visitor' => str_repeat('b', 16), 'bot' => 0, 'page' => 'home']);
    $this->artisan('ei:analytics-rollup')->assertSuccessful();
    expect(DB::table('analytics_daily')->where(['day' => daysAgo(10), 'dim' => 'all'])->exists())->toBeTrue()
        // Days with no raw rows are not touched.
        ->and(DB::table('analytics_daily')->where('day', daysAgo(15))->exists())->toBeFalse();
});

test('days older than the bot rows are kept are still totalled when they have no totals yet', function () {
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => daysAgo(45).' 12:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home']);

    $this->artisan('ei:analytics-rollup')->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => daysAgo(45), 'dim' => 'all'])->value('hits'))->toBe(1);
});

test('the hourly rollup of today also totals the days before deploy', function () {
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => daysAgo(6).' 12:00:00', 'visitor' => str_repeat('a', 16), 'bot' => 0, 'page' => 'home']);

    $this->artisan('ei:analytics-rollup', ['--day' => daysAgo(0)])->assertSuccessful();

    expect(DB::table('analytics_daily')->where(['day' => daysAgo(6), 'dim' => 'all'])->exists())->toBeTrue();
});

test('an owner previewing an unpublished organizer page is not a page view', function () {
    captureOn('page_views');
    $owner = User::factory()->create();
    $organizer = App\Models\Organizer::factory()->create(['status' => 'd']);
    $organizer->users()->attach($owner->id, ['role' => 'owner']);

    $this->actingAs($owner)->withoutVite()->get('/organizers/'.$organizer->slug)->assertOk();

    expect(flushedRows()->where('type', 'page_view'))->toHaveCount(0);
});

test('page addresses fewer than three people opened go from the daily totals after 13 months', function () {
    $old = now()->subDays(400)->toDateString();
    foreach (['/rare' => 1, '/common' => 5] as $key => $visitors) {
        DB::table('analytics_daily')->insert(['day' => $old, 'type' => 'view', 'dim' => 'path', 'key' => $key, 'bot' => 0, 'hits' => $visitors, 'visitors' => $visitors, 'seconds_sum' => 0, 'seconds_count' => 0]);
    }

    $this->artisan('ei:analytics-prune')->assertSuccessful();

    expect(DB::table('analytics_daily')->where('dim', 'path')->pluck('key')->all())->toBe(['/common']);
});

test('a step from a very long address keeps both ends, and is found from either side', function () {
    $day = daysAgo(2);
    $long = '/events/'.str_repeat('a', 180);
    foreach (range(1, 5) as $i) {
        $visitor = str_repeat((string) $i, 16);
        DB::table('analytics_events')->insert([
            ['type' => 'page_view', 'occurred_at' => "{$day} 10:00:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'events.show', 'path' => $long],
            ['type' => 'page_view', 'occurred_at' => "{$day} 10:01:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'search', 'path' => '/index/search'],
        ]);
    }
    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    $query = app(App\Actions\Analytics\AnalyticsQuery::class);
    expect($query->paths($long, 'next', 7)[0]['to'] ?? null)->toBe('/index/search')
        ->and(count($query->paths('/index/search', 'previous', 7)))->toBe(1);
});

test('two pages hours apart are not a step from one to the other', function () {
    $day = daysAgo(2);
    foreach (range(1, 5) as $i) {
        $visitor = str_repeat((string) $i, 16);
        DB::table('analytics_events')->insert([
            ['type' => 'page_view', 'occurred_at' => "{$day} 09:00:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'events.show', 'path' => '/events/x'],
            ['type' => 'page_view', 'occurred_at' => "{$day} 18:00:0{$i}", 'visitor' => $visitor, 'bot' => 0, 'page' => 'home', 'path' => '/'],
        ]);
    }

    $this->artisan('ei:analytics-rollup', ['--day' => $day])->assertSuccessful();

    expect(DB::table('analytics_daily')->where('dim', 'edge')->count())->toBe(0);
});

test('the private communities list is not a page view', function () {
    expect(App\Http\Middleware\RecordPageView::PAGES)->not->toContain('communities.index');
});

test('a capture switched off stays on the privacy page while its records are kept, however it was switched off', function () {
    config(['analytics.capture.city' => false]);
    DB::table('analytics_daily')->insert(['day' => now()->subDays(10)->toDateString(), 'type' => 'view', 'dim' => 'city', 'key' => 'Boston', 'bot' => 0, 'hits' => 4, 'visitors' => 3, 'seconds_sum' => 0, 'seconds_count' => 0]);

    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('Within the last 13 months we also recorded, in the same way, your city and region');

    Illuminate\Support\Facades\Cache::flush();
    Carbon::setTestNow(now()->addDays(400));
    expect($this->withoutVite()->get('/privacy')->getContent())->not->toContain('your city and region');
});

test('a capture switched off before any rollup still shows on the privacy page', function () {
    config(['analytics.capture.city' => false]);
    DB::table('analytics_events')->insert(['type' => 'page_view', 'occurred_at' => now()->subHour(), 'visitor' => str_repeat('a', 16), 'bot' => 0, 'city' => 'Boston']);

    expect($this->withoutVite()->get('/privacy')->getContent())->toContain('your city and region');
});
