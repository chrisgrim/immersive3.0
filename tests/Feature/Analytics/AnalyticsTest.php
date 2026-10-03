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
        Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => BROWSER_UA, 'HTTP_SEC_FETCH_SITE' => 'none']));

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
    $request = fn () => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => BROWSER_UA, 'HTTP_SEC_FETCH_SITE' => 'none']);

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
    $as = fn (string $ua, string $ip = '198.51.100.1') => Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua, 'HTTP_SEC_FETCH_SITE' => 'none']);

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
        ['type' => 'search', 'occurred_at' => now()->subDays(396), 'visitor' => str_repeat('a', 16)],
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
    $ids = Event::factory()->count(3)->published()->create()->pluck('id')->all();
    FakeSearchEngine::install($ids);

    $this->getJson('/api/index/search?'.analyticsLaQuery().'&start=2026-10-10&end=2026-10-12')->assertOk();

    $row = (array) analyticsRows()->sole();
    expect($row['query'])->toBe('Los Angeles, CA')
        ->and($row['results'])->toBe(3)
        ->and($row['source'])->toBe('list')
        ->and(json_decode($row['props'], true))->toEqual([
            'searchType' => 'inPerson', 'lat' => 34.05, 'lng' => -118.24, 'start' => '2026-10-10', 'end' => '2026-10-12', 'shown' => $ids,
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

// ----- country and datacenter (step 2) -----

function fakeGeo(array $countries, array $asns): void
{
    app()->instance(App\Support\Analytics\GeoLookup::class, new class($countries, $asns) extends App\Support\Analytics\GeoLookup
    {
        public function __construct(private array $countries, private array $asns) {}

        public function country(string $ip): ?string
        {
            return $this->countries[$ip] ?? null;
        }

        public function asn(string $ip): ?int
        {
            return $this->asns[$ip] ?? null;
        }
    });
}

test('a hit from a cloud network is flagged as a bot, and every row gets its country', function () {
    // 43.134.x is the Singapore bot's Tencent network; 73.162.x a Comcast home line.
    fakeGeo(['43.134.1.1' => 'SG', '73.162.1.1' => 'US'], ['43.134.1.1' => 132203, '73.162.1.1' => 7922]);
    foreach (['43.134.1.1', '73.162.1.1'] as $ip) {
        Analytics::record(Analytics::SEARCH, [], Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => BROWSER_UA, 'HTTP_SEC_FETCH_SITE' => 'none']));
    }

    $rows = analyticsRows();

    expect($rows->pluck('bot')->all())->toBe([Analytics::BOT_DATACENTER, 0])
        ->and($rows->pluck('country')->all())->toBe(['SG', 'US']);
});

test('without the IP databases nothing is looked up and nothing breaks', function () {
    config(['analytics.geo_path' => storage_path('framework/testing/no-geo-here')]);

    $geo = new App\Support\Analytics\GeoLookup;

    expect($geo->country('73.162.1.1'))->toBeNull()->and($geo->asn('73.162.1.1'))->toBeNull();
});

test('a broken download never replaces a working database', function () {
    $dir = storage_path('framework/testing/geo-'.uniqid());
    config(['analytics.geo_path' => $dir]);
    Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/country.mmdb", 'the old one');
    touch("{$dir}/country.mmdb", now()->subDays(30)->getTimestamp());
    Illuminate\Support\Facades\Http::fake(['download.db-ip.com/*' => Illuminate\Support\Facades\Http::response(gzencode('not a database'))]);

    $this->artisan('ei:analytics-geo-update')->assertSuccessful();

    expect(file_get_contents("{$dir}/country.mmdb"))->toBe('the old one')
        ->and(glob("{$dir}/*.download*"))->toBe([]);
    Illuminate\Support\Facades\File::deleteDirectory($dir);
});

// ----- event page views (step 3) -----

function analyticsShowableEvent(): Event
{
    $organizer = App\Models\Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id, 'closingDate' => now()->addDays(30), 'hasLocation' => true]);
    App\Models\Events\Location::factory()->create(['event_id' => $event->id]);
    App\Models\Events\Show::factory()->create(['event_id' => $event->id]);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

test('an event page view is recorded with where the visitor came from', function () {
    $event = analyticsShowableEvent();

    $this->withoutVite()->get("/events/{$event->slug}", ['Referer' => 'https://www.google.com/'])->assertOk();
    $this->withoutVite()->get("/events/{$event->slug}", ['Referer' => url('/index/search?city=Boise')])->assertOk();
    $this->withoutVite()->get("/events/{$event->slug}")->assertOk();

    $rows = analyticsRows();
    expect($rows->pluck('type')->unique()->all())->toBe(['event_view'])
        ->and($rows->pluck('event_id')->unique()->all())->toBe([$event->id])
        ->and($rows->pluck('source')->all())->toBe(['search_engine', 'search', 'direct'])
        ->and(json_decode($rows[0]->props, true))->toBe(['ref' => 'google.com'])
        ->and($rows[1]->props)->toBeNull();
});

test('a prefetch and a page that 404s are not views', function () {
    $event = analyticsShowableEvent();

    $this->withoutVite()->get("/events/{$event->slug}", ['Sec-Purpose' => 'prefetch'])->assertOk();
    $this->withoutVite()->get('/events/no-such-event')->assertNotFound();

    expect(analyticsRows())->toHaveCount(0);
});

test('referrers sort into the right kinds', function (string $referer, string $source) {
    $request = Illuminate\Http\Request::create('https://everythingimmersive.com/events/x', 'GET', server: ['HTTP_REFERER' => $referer]);

    expect(Analytics::referrer($request)['source'])->toBe($source);
})->with([
    ['https://chatgpt.com/', 'ai'],
    ['https://gemini.google.com/app', 'ai'],
    ['https://www.bing.com/search?q=x', 'search_engine'],
    ['https://old.reddit.com/r/immersivetheatre', 'social'],
    ['https://t.co/abc', 'social'],
    ['https://nomorepencils.com/blog', 'referral'],
    ['https://www.everythingimmersive.com/', 'home'],
    ['https://everythingimmersive.com/communities/la/posts/top', 'community'],
]);

// ----- result clicks and impressions (step 5) -----

test('a search hands back its id and records which events it showed, in order', function () {
    $ids = Event::factory()->count(3)->published()->create()->pluck('id')->all();
    FakeSearchEngine::install($ids);

    $searchId = $this->getJson('/api/index/search?'.analyticsLaQuery())->assertOk()->json('search_id');

    $row = analyticsRows()->sole();
    expect($searchId)->toMatch(Analytics::SEARCH_ID_PATTERN)
        ->and($row->search_id)->toBe($searchId)
        // FakeSearchEngine returns the hits in the order installed.
        ->and(json_decode($row->props, true)['shown'])->toBe($ids);
});

test('Show more keeps the search id it was given, so clicks still trace back', function () {
    FakeSearchEngine::install(Event::factory()->count(25)->published()->create()->pluck('id')->all());

    $response = $this->getJson('/api/index/search?'.analyticsLaQuery().'&pages=2&sid=abcDEF123456')->assertOk();

    expect($response->json('search_id'))->toBe('abcDEF123456');
});

test('a result click is recorded with its search, event and position, without a session or CSRF token', function () {
    $this->withHeaders(['Referer' => url('/index/search'), 'Origin' => url('/')])
        ->post('/api/analytics/search-click', ['search_id' => 'abcDEF123456', 'event_id' => 42, 'position' => 3])
        ->assertNoContent();

    $row = analyticsRows()->sole();
    expect($row->type)->toBe('search_click')
        ->and($row->search_id)->toBe('abcDEF123456')
        ->and($row->event_id)->toBe(42)
        ->and(json_decode($row->props, true))->toBe(['position' => 3]);
});

test('a result click with junk in it is not recorded', function (array $body) {
    $this->post('/api/analytics/search-click', $body)->assertNoContent();

    expect(analyticsRows())->toHaveCount(0);
})->with([
    'no search id' => [['event_id' => 42, 'position' => 1]],
    'bad search id' => [['search_id' => 'abc', 'event_id' => 42, 'position' => 1]],
    'bad event' => [['search_id' => 'abcDEF123456', 'event_id' => 'x', 'position' => 1]],
    'position zero' => [['search_id' => 'abcDEF123456', 'event_id' => 42, 'position' => 0]],
]);

// ----- review fixes -----

test('a refresh or Back to the same search is not a second search', function () {
    FakeSearchEngine::install(Event::factory()->count(3)->published()->create()->pluck('id')->all());

    $first = $this->getJson('/api/index/search?'.analyticsLaQuery())->json('search_id');
    $again = $this->withoutVite()->get('/index/search?'.analyticsLaQuery().'&page=2')->viewData('searchedEvents')['search_id'];
    $other = $this->getJson('/api/index/search?'.analyticsLaQuery().'&tag=3')->json('search_id');

    expect($again)->toBe($first)->and($other)->not->toBe($first)
        ->and(analyticsRows()->where('type', 'search'))->toHaveCount(2);
});

test('the place text is cleaned and capped before it is buffered', function () {
    FakeSearchEngine::install([]);

    $this->getJson('/api/index/search?city='.urlencode("Boise\u{0007}\n".str_repeat('x', 300)).'&lat=43.6&lng=-116.2&searchType=evil&live=false')->assertOk();

    $row = analyticsRows()->sole();
    expect(mb_strlen($row->query))->toBe(100)
        ->and($row->query)->toStartWith('Boise ')
        ->and(json_decode($row->props, true))->not->toHaveKey('searchType');
});

test('one address changing its user agent on every hit still hits the daily cap', function () {
    config(['analytics.ip_daily_cap' => 3]);
    foreach (range(1, 4) as $i) {
        Analytics::record(Analytics::SEARCH, [], Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_USER_AGENT' => BROWSER_UA." v{$i}", 'HTTP_SEC_FETCH_SITE' => 'none']));
    }

    expect(analyticsRows()->pluck('bot')->all())->toBe([0, 0, 0, Analytics::BOT_OVER_DAILY_CAP]);
});

test('turning analytics off throws away what is still buffered', function () {
    Analytics::record(Analytics::SEARCH, ['query' => 'Gone']);
    config(['analytics.enabled' => false]);
    $this->artisan('ei:analytics-flush')->assertSuccessful();
    config(['analytics.enabled' => true]);

    expect(app(Analytics::class)->pop(10))->toBe([]);
});

test('an email address or phone number typed as a place is removed', function () {
    FakeSearchEngine::install([]);

    $this->getJson('/api/index/search?city='.urlencode('jane.doe@example.com call +1 (555) 123-4567').'&searchType=inPerson&live=false')->assertOk();

    expect(analyticsRows()->sole()->query)->toBe('[removed] call [removed]');
});

test('if building the rows fails, the notes go back too', function () {
    Analytics::record(Analytics::SEARCH, ['query' => 'Kept']);
    Illuminate\Support\Facades\Cache::shouldReceive('get')->andThrow(new RuntimeException('cache down'));

    try {
        test()->artisan('ei:analytics-flush');
    } catch (Throwable) {
        // expected
    }

    expect(app(Analytics::class)->pop(10))->toHaveCount(1);
});

test('a browser sending Global Privacy Control or Do Not Track is not recorded', function () {
    FakeSearchEngine::install([]);

    $this->getJson('/api/index/search?'.analyticsLaQuery(), ['Sec-GPC' => '1'])->assertOk();
    $this->getJson('/api/index/search?'.analyticsLaQuery().'&tag=3', ['DNT' => '1'])->assertOk();

    expect(analyticsRows())->toHaveCount(0);
});

test('the same search showing different events (a new listing on top) is a new search', function () {
    $first = Event::factory()->count(2)->published()->create()->pluck('id')->all();
    FakeSearchEngine::install($first);
    $before = $this->getJson('/api/index/search?'.analyticsLaQuery())->json('search_id');

    $newest = Event::factory()->published()->create()->id;
    FakeSearchEngine::install([$newest, ...$first]);
    $after = $this->getJson('/api/index/search?'.analyticsLaQuery())->json('search_id');

    expect($after)->not->toBe($before)
        ->and(json_decode(analyticsRows()->last()->props, true)['shown'])->toBe([$newest, ...$first]);
});

test('a note the database rejects as invalid is skipped and the rest are written', function () {
    Analytics::record(Analytics::SEARCH, ['query' => 'Good']);
    Analytics::record(Analytics::SEARCH, ['query' => 'Bad', 'results' => 99999999999]); // too big for the column

    expect(analyticsRows()->pluck('query')->all())->toBe(['Good'])
        ->and(app(Analytics::class)->pop(10))->toBe([]);
});

test('a visit missing what real browsers send is flagged as a bot', function () {
    $visit = fn (array $server) => Analytics::record(Analytics::SEARCH, [], Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.'.mt_rand(10, 250), ...$server]));
    $firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0';
    $oldSafari = 'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';

    $visit(['HTTP_USER_AGENT' => BROWSER_UA, 'HTTP_SEC_FETCH_SITE' => 'none']);          // a real Chrome
    $visit(['HTTP_USER_AGENT' => BROWSER_UA]);                                           // Chrome, no Sec-Fetch-Site
    $visit(['HTTP_USER_AGENT' => $firefox, 'HTTP_ACCEPT_LANGUAGE' => '']);               // no Accept-Language
    $visit(['HTTP_USER_AGENT' => $oldSafari]);                                           // old Safari: no Sec-Fetch, fine

    expect(analyticsRows()->pluck('bot')->all())->toBe([0, Analytics::BOT_HEADERS, Analytics::BOT_HEADERS, 0]);
});

test('a batch that fails and is retried does not count toward the daily cap twice', function () {
    config(['analytics.daily_cap' => 2]);
    foreach (range(1, 2) as $i) {
        Analytics::record(Analytics::SEARCH, [], Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => BROWSER_UA, 'HTTP_SEC_FETCH_SITE' => 'none']));
    }
    Illuminate\Support\Facades\Schema::rename('analytics_events', 'analytics_events_away');

    try {
        test()->artisan('ei:analytics-flush');
    } catch (Throwable) {
        // expected
    } finally {
        Illuminate\Support\Facades\Schema::rename('analytics_events_away', 'analytics_events');
    }

    expect(analyticsRows()->pluck('bot')->all())->toBe([0, 0]);
});
