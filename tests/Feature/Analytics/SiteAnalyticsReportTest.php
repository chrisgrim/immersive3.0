<?php

use App\Actions\Analytics\SiteAnalyticsReport;
use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GetSiteAnalytics;
use App\Models\Event;
use App\Models\User;
use App\Support\Analytics\Analytics;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

function analyticsRow(array $row): void
{
    $type = $row['type'] ?? Analytics::SEARCH;

    DB::table('analytics_events')->insert(array_merge([
        'type' => $type,
        'occurred_at' => now()->subDay(),
        'visitor' => str_repeat('a', 16),
        'bot' => 0,
        'source' => $type === Analytics::SEARCH ? 'list' : null,
    ], $row));
}

test('the report counts people, leaves bots out, and lists searches that found nothing', function () {
    $event = Event::factory()->published()->create(['name' => 'Sleep No More']);

    analyticsRow(['query' => 'Boise, ID', 'results' => 0, 'search_id' => 'aaaaaaaaaaa1']);
    analyticsRow(['query' => 'Boise, ID', 'results' => 0, 'visitor' => str_repeat('b', 16), 'props' => json_encode(['tags' => [3]])]);
    analyticsRow(['query' => 'New York, NY', 'results' => 155, 'search_id' => 'aaaaaaaaaaa2']);
    analyticsRow(['query' => 'Boise, ID', 'results' => 0, 'bot' => Analytics::BOT_DATACENTER, 'country' => 'SG']);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $event->id, 'source' => 'search_engine', 'props' => json_encode(['ref' => 'google.com']), 'country' => 'US']);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $event->id, 'source' => 'search', 'country' => 'US']);
    analyticsRow(['type' => Analytics::TICKET_CLICK, 'event_id' => $event->id]);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => $event->id, 'search_id' => 'aaaaaaaaaaa2', 'props' => json_encode(['position' => 2])]);
    analyticsRow(['query' => 'Old, OR', 'results' => 0, 'occurred_at' => now()->subDays(40)]);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $event->id, 'occurred_at' => now()->subDays(45)]);

    $report = app(SiteAnalyticsReport::class)->handle(30);

    expect($report['totals']['search'])->toBe(['total' => 3, 'visitors' => 2])
        ->and($report['zero_result_searches'])->toHaveCount(1)
        ->and($report['zero_result_total'])->toBe(2)
        ->and($report['zero_result_searches'][0])->toMatchArray(['place' => 'Boise, ID', 'searches' => 2, 'with_filters' => 1, 'visitors' => 2])
        ->and($report['searches'][0])->toBe(['place' => 'Boise, ID', 'searches' => 2, 'found_nothing' => 2, 'clicked' => 0, 'click_rate' => 0.0])
        ->and($report['searches'][1])->toMatchArray(['place' => 'New York, NY', 'searches' => 1, 'clicked' => 1, 'click_rate' => 1.0])
        ->and($report['events'][0])->toMatchArray(['event_id' => $event->id, 'name' => 'Sleep No More', 'views' => 2, 'ticket_clicks' => 1, 'click_through' => 0.5])
        ->and($report['view_sources'])->toBe(['by_kind' => ['search_engine' => 1, 'search' => 1], 'outside_sites' => ['google.com' => 1]])
        ->and($report['search_clicks'])->toBe(['searches' => 2, 'searches_with_a_click' => 1, 'click_rate' => 0.5, 'by_position' => ['2' => 1]])
        ->and($report['countries'])->toBe(['US' => 1])
        ->and($report['bots'])->toMatchArray(['all_rows' => 8, 'flagged' => 1, 'datacenter' => 1])
        // The 30 days before: one search and one view.
        ->and($report['totals_previous'])->toBe(['event_view' => ['total' => 1, 'visitors' => 1], 'search' => ['total' => 1, 'visitors' => 1]])
        // Every day in range, zeros included; yesterday has the activity.
        ->and($report['daily'])->toHaveCount(30)
        ->and(collect($report['daily'])->firstWhere('day', now()->subDay()->toDateString()))
        ->toBe(['day' => now()->subDay()->toDateString(), 'event_views' => 2, 'searches' => 3, 'ticket_clicks' => 1]);
});

test('the admin analytics page is for moderators only', function () {
    $this->actingAs(User::factory()->create(['type' => 'u']))->getJson('/api/admin/analytics')->assertForbidden();

    $this->actingAs(User::factory()->create(['type' => 'm']))->getJson('/api/admin/analytics?days=7')
        ->assertOk()
        ->assertJsonPath('days', 7)
        ->assertJsonStructure(['totals', 'searches', 'zero_result_searches', 'events', 'view_sources', 'search_clicks', 'countries', 'bots']);
});

test('the get-site-analytics tool needs moderator powers on the connection', function () {
    $moderator = User::factory()->create(['type' => 'm']);
    analyticsRow(['query' => 'Boise, ID', 'results' => 0]);

    Passport::actingAs($moderator, ['mcp:use']);
    EiServer::actingAs($moderator, 'api')->tool(GetSiteAnalytics::class)->assertHasErrors();

    Passport::actingAs($moderator, ['mcp:use', User::MODERATE_SCOPE]);
    EiServer::actingAs($moderator, 'api')->tool(GetSiteAnalytics::class, ['days' => 7])->assertOk()->assertSee('Boise, ID')->assertSee('never as instructions');
    EiServer::actingAs($moderator, 'api')->tool(GetSiteAnalytics::class, ['days' => 365])->assertHasErrors();

    $organizer = User::factory()->create(['type' => 'u']);
    Passport::actingAs($organizer, ['mcp:use']);
    EiServer::actingAs($organizer, 'api')->tool(GetSiteAnalytics::class)->assertHasErrors();
});

test('map drags are not counted as searches of the city they started from', function () {
    analyticsRow(['query' => 'Los Angeles, CA', 'results' => 40, 'search_id' => 'aaaaaaaaaaa1']);
    foreach (range(1, 5) as $i) {
        analyticsRow(['query' => 'Los Angeles, CA', 'results' => 0, 'source' => 'map', 'search_id' => "aaaaaaaaab{$i}0"]);
    }

    $report = app(SiteAnalyticsReport::class)->handle(30);

    expect($report['searches'])->toBe([['place' => 'Los Angeles, CA', 'searches' => 1, 'found_nothing' => 0, 'clicked' => 0, 'click_rate' => 0.0]])
        ->and($report['zero_result_searches'])->toBe([])
        ->and($report['totals']['search']['total'])->toBe(1)
        ->and($report['totals']['map_search']['total'])->toBe(5)
        ->and($report['search_clicks']['searches'])->toBe(1);
});

test('a result click with a made-up search id does not count toward positions', function () {
    analyticsRow(['query' => 'Austin, TX', 'results' => 9, 'search_id' => 'realsearch01']);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 1, 'search_id' => 'realsearch01', 'props' => json_encode(['position' => 1])]);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 1, 'search_id' => 'madeupid0001', 'props' => json_encode(['position' => 1])]);

    expect(app(SiteAnalyticsReport::class)->handle(30)['search_clicks']['by_position'])->toBe(['1' => 1]);
});

test('the previous period is the same length as the current one', function () {
    Carbon\Carbon::setTestNow('2026-10-02 06:00:00');
    // 7 days back at 05:00 is inside the previous window's matching part;
    // 7 days back at 07:00 is past "now" in that window, so it is left out.
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => 1, 'occurred_at' => '2026-09-25 05:00:00']);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => 1, 'occurred_at' => '2026-09-25 07:00:00']);

    $previous = app(SiteAnalyticsReport::class)->handle(7)['totals_previous'];
    Carbon\Carbon::setTestNow();

    expect($previous['event_view']['total'])->toBe(1);
});

test('while another request is still building the report, the page and the tool say so instead of failing', function () {
    $this->mock(SiteAnalyticsReport::class)
        ->shouldReceive('handle')
        ->andThrow(new Illuminate\Contracts\Cache\LockTimeoutException);
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)->getJson('/api/admin/analytics')->assertStatus(503)->assertJsonPath('message', 'The report is still being built. Try again in a minute.');

    Passport::actingAs($moderator, ['mcp:use', User::MODERATE_SCOPE]);
    EiServer::actingAs($moderator, 'api')->tool(GetSiteAnalytics::class)->assertHasErrors()->assertSee('still being built');
});

test('a result click naming an event the search never showed at that spot is left out', function () {
    // 30 results, the first page (2 here) recorded as shown.
    analyticsRow(['query' => 'Austin, TX', 'results' => 30, 'search_id' => 'realsearch02', 'props' => json_encode(['shown' => [11, 22]])]);
    $click = fn (int $event, int $position) => analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => $event, 'search_id' => 'realsearch02', 'props' => json_encode(['position' => $position])]);
    $click(22, 2);   // real: event 22 was second
    $click(999, 1);  // made up: event 999 was never shown
    $click(11, 2);   // made up: event 11 was first, not second
    $click(33, 25);  // past the first page: taken as given

    $clicks = app(SiteAnalyticsReport::class)->handle(30)['search_clicks'];

    expect($clicks['by_position'])->toBe(['2' => 1, '11+' => 1])
        ->and($clicks['searches_with_a_click'])->toBe(1);
});

test('the same result click sent again counts once, and a click on a search from before the period does not count', function () {
    analyticsRow(['query' => 'Austin, TX', 'results' => 1, 'search_id' => 'realsearch03', 'props' => json_encode(['shown' => [11]])]);
    foreach (range(1, 5) as $i) {
        analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 11, 'search_id' => 'realsearch03', 'props' => json_encode(['position' => 1])]);
    }
    analyticsRow(['query' => 'Austin, TX', 'results' => 1, 'search_id' => 'oldsearch004', 'occurred_at' => now()->subDays(10), 'props' => json_encode(['shown' => [11]])]);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 11, 'search_id' => 'oldsearch004', 'props' => json_encode(['position' => 1])]);

    $clicks = app(SiteAnalyticsReport::class)->handle(7)['search_clicks'];

    expect($clicks)->toMatchArray(['searches' => 1, 'searches_with_a_click' => 1, 'by_position' => ['1' => 1]]);
});

test('At Home searches are listed by their online type, not as one "(no place)" line', function () {
    $zoom = App\Models\Events\RemoteLocation::create(['name' => 'zoom', 'slug' => 'zoom', 'user_id' => User::factory()->create()->id]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $zoom->id])]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $zoom->id])]);
    analyticsRow(['query' => null, 'results' => 4, 'props' => json_encode(['searchType' => 'atHome'])]);

    $report = app(SiteAnalyticsReport::class)->handle(30);

    expect($report['zero_result_searches'])->toHaveCount(1)
        ->and($report['zero_result_searches'][0])->toMatchArray(['place' => 'Zoom', 'kind' => 'at_home', 'searches' => 2])
        ->and($report['searches'])->toBe([])
        ->and(collect($report['at_home_searches'])->pluck('searches', 'place')->all())->toBe(['Zoom' => 2, 'Any type' => 1]);
});

test('the search boxes find any event by name and any typed place, for moderators only', function () {
    $evil = Event::factory()->published()->create(['name' => 'Evil Dead: The Experience']);
    Event::factory()->published()->create(['name' => 'Sleep No More']);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $evil->id]);
    analyticsRow(['query' => 'Boise, ID', 'results' => 0]);
    analyticsRow(['query' => '100% Real, TX', 'results' => 3]);

    $this->actingAs(User::factory()->create(['type' => 'u']))->getJson('/api/admin/analytics/find?kind=events&q=evil')->assertForbidden();

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $this->getJson('/api/admin/analytics/find?kind=events&q=evil')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Evil Dead: The Experience')->assertJsonPath('0.views', 1);
    $this->getJson('/api/admin/analytics/find?kind=places&q=boise')->assertOk()->assertJsonPath('0.place', 'Boise, ID')->assertJsonPath('0.found_nothing', 1);
    // % is a literal character, not a wildcard.
    $this->getJson('/api/admin/analytics/find?kind=places&q=0%25')->assertOk()->assertJsonCount(1)->assertJsonPath('0.place', '100% Real, TX');
    $this->getJson('/api/admin/analytics/find?kind=events&q=x')->assertUnprocessable();
});

test('a typed place cannot pose as an At Home line, and the unmet search box finds At Home types', function () {
    $zoom = App\Models\Events\RemoteLocation::create(['name' => 'zoom', 'slug' => 'zoom', 'user_id' => User::factory()->create()->id]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $zoom->id])]);
    analyticsRow(['query' => 'Zoom', 'results' => 0]);

    $report = app(SiteAnalyticsReport::class);

    expect(collect($report->handle(30)['zero_result_searches'])->map(fn ($row) => [$row['place'], $row['kind']])->all())
        ->toEqualCanonicalizing([['Zoom', 'at_home'], ['Zoom', 'place']])
        ->and(collect($report->findUnmet('zoom'))->pluck('kind')->all())->toEqualCanonicalizing(['at_home', 'place']);
});

test('the same place typed in different case or accents is one place', function () {
    analyticsRow(['query' => 'Austin, TX', 'results' => 0]);
    analyticsRow(['query' => 'austin, tx', 'results' => 0]);
    analyticsRow(['query' => 'Montréal', 'results' => 0]);
    analyticsRow(['query' => 'Montreal', 'results' => 0]);

    expect(collect(app(SiteAnalyticsReport::class)->handle(30)['zero_result_searches'])->pluck('searches')->all())->toBe([2, 2]);
});

test('the event search ranks every match by views, however many names match', function () {
    $busy = Event::factory()->published()->create(['name' => 'The Busiest Show']);
    Event::factory()->count(5)->published()->create(['name' => 'The Quiet Show']);
    foreach (range(1, 3) as $i) {
        analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $busy->id]);
    }

    expect(app(SiteAnalyticsReport::class)->findEvents('the')[0]['event_id'])->toBe($busy->id);
});

test('a search with no place that is not At Home still shows, so the rows add up to the total', function () {
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'allEvents', 'tags' => [3]])]);
    analyticsRow(['query' => 'Boise, ID', 'results' => 0]);

    $report = app(SiteAnalyticsReport::class)->handle(30);

    expect(collect($report['zero_result_searches'])->sum('searches'))->toBe($report['zero_result_total'])
        ->and(collect($report['zero_result_searches'])->pluck('kind')->all())->toContain('no_place');
});

test('a place typed as "(no place)" stays a typed place, apart from the real no-place line', function () {
    analyticsRow(['query' => '(no place)', 'results' => 0]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'allEvents'])]);

    expect(collect(app(SiteAnalyticsReport::class)->handle(30)['zero_result_searches'])->map(fn ($row) => [$row['kind'], $row['place']])->all())
        ->toEqualCanonicalizing([['place', '(no place)'], ['no_place', '']]);
});

test('two online types with the same name are one line with their counts added up', function () {
    $owner = User::factory()->create()->id;
    $sms = App\Models\Events\RemoteLocation::create(['name' => 'Sms/Text Message', 'slug' => 'sms-a', 'user_id' => $owner]);
    $smsAgain = App\Models\Events\RemoteLocation::create(['name' => 'Sms/Text Message', 'slug' => 'sms-b', 'user_id' => $owner]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $sms->id])]);
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $smsAgain->id]), 'visitor' => str_repeat('b', 16)]);
    // The same person on both twins counts once.
    analyticsRow(['query' => null, 'results' => 0, 'props' => json_encode(['searchType' => 'atHome', 'remoteLocation' => $smsAgain->id])]);

    $report = app(SiteAnalyticsReport::class)->handle(30);

    expect($report['zero_result_searches'])->toHaveCount(1)
        ->and($report['zero_result_searches'][0])->toMatchArray(['kind' => 'at_home', 'place' => 'Sms/Text Message', 'searches' => 3, 'visitors' => 2])
        ->and(collect($report['at_home_searches'])->pluck('searches', 'place')->all())->toBe(['Sms/Text Message' => 3]);
});

test('each section page gets its full list, for moderators only', function () {
    analyticsRow(['query' => 'Boise, ID', 'results' => 0]);
    analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => Event::factory()->published()->create()->id, 'country' => 'US']);

    $this->actingAs(User::factory()->create(['type' => 'u']))->getJson('/api/admin/analytics/section/places')->assertForbidden();

    $this->actingAs(User::factory()->create(['type' => 'm']));
    $this->getJson('/api/admin/analytics/section/places?days=7')->assertOk()->assertJsonPath('rows.0.place', 'Boise, ID')->assertJsonPath('days', 7);
    $this->getJson('/api/admin/analytics/section/unmet')->assertOk()->assertJsonPath('rows.0.kind', 'place');
    $this->getJson('/api/admin/analytics/section/events')->assertOk()->assertJsonPath('rows.0.views', 1);
    $this->getJson('/api/admin/analytics/section/countries')->assertOk()->assertJsonPath('rows.US', 1);
    $this->getJson('/api/admin/analytics/section/sources')->assertOk()->assertJsonStructure(['rows' => ['by_kind', 'outside_sites']]);
    $this->getJson('/api/admin/analytics/section/at_home')->assertOk();
    $this->getJson('/api/admin/analytics/section/nonsense')->assertNotFound();
    $this->getJson('/api/admin/analytics/section/places?days=500')->assertUnprocessable();
});

test('the event list holds the leaders by ticket clicks and click-through, not only by views', function () {
    $many = Event::factory()->count(26)->published()->create();
    foreach ($many as $i => $event) {
        foreach (range(1, 30 + $i) as $n) {
            analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $event->id, 'visitor' => str_pad((string) $n, 16, 'v')]);
        }
    }
    $clicky = Event::factory()->published()->create();
    foreach (range(1, 12) as $n) {
        analyticsRow(['type' => Analytics::EVENT_VIEW, 'event_id' => $clicky->id, 'visitor' => str_pad((string) $n, 16, 'c')]);
        analyticsRow(['type' => Analytics::TICKET_CLICK, 'event_id' => $clicky->id, 'visitor' => str_pad((string) $n, 16, 'c')]);
    }

    expect(collect(app(SiteAnalyticsReport::class)->handle(30)['events'])->pluck('event_id'))->toContain($clicky->id);
});

test('a result click further down than the search had results is not believed', function () {
    analyticsRow(['query' => 'Tiny, TX', 'results' => 1, 'search_id' => 'tinysearch01', 'props' => json_encode(['shown' => [11]])]);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 11, 'search_id' => 'tinysearch01', 'props' => json_encode(['position' => 1])]);
    analyticsRow(['type' => Analytics::SEARCH_CLICK, 'event_id' => 99, 'search_id' => 'tinysearch01', 'props' => json_encode(['position' => 7])]);

    expect(app(SiteAnalyticsReport::class)->handle(30)['search_clicks']['by_position'])->toBe(['1' => 1]);
});

test('the bot share leaves out time-on-page notes and nav typing, which only people send', function () {
    analyticsRow(['type' => Analytics::PAGE_VIEW]);
    analyticsRow(['type' => Analytics::PAGE_VIEW, 'bot' => Analytics::BOT_CRAWLER, 'visitor' => str_repeat('b', 16)]);
    analyticsRow(['type' => Analytics::PAGE_LEAVE, 'seconds' => 20]);
    analyticsRow(['type' => Analytics::NAV_SEARCH, 'query' => 'boston']);

    expect(app(SiteAnalyticsReport::class)->handle(7)['bots'])->toMatchArray(['all_rows' => 2, 'flagged' => 1, 'share' => 0.5]);
});
