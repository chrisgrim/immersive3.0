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
