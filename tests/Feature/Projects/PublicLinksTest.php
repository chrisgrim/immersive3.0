<?php

use App\Actions\Analytics\SearchConsoleReport;
use App\Mail\FollowedOrganizerNewEventMail;
use App\Mail\SavedEventNewDatesMail;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\FollowedOrganizerNewEventNotification;
use App\Notifications\SavedEventNewDatesNotification;
use App\Support\Analytics\Analytics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha']);
    $this->run = publishedListing('Ha Ha Ha', 'Chicago');
    $this->run->update(['project_id' => $this->project->id, 'instance_slug' => 'chicago-2026']);
    $this->run = $this->run->fresh();
    $this->plain = publishedListing('Plain', 'Chicago');
    Cache::flush();
});

test('map pins carry the address, and cost one project query however many', function () {
    foreach (['A', 'B', 'C'] as $i => $name) {
        publishedListing($name, 'Rome')->update(['project_id' => $this->project->id, 'instance_slug' => 'rome-'.$i]);
    }

    $queries = collect();
    DB::listen(fn ($q) => $queries->push($q->sql));

    $pins = collect(Event::mapPins(Event::pluck('id')->all()))->keyBy('id');

    expect($pins[$this->run->id]['public_url'])->toBe('/events/ha-ha-ha/chicago-2026')
        ->and($pins[$this->plain->id]['public_url'])->toBe('/events/plain')
        ->and($queries->filter(fn ($sql) => str_contains($sql, '`projects`'))->count())->toBe(1);
});

test('the sitemap lists runs at their address, shows and venues, and never a project with no live run', function () {
    Project::factory()->create(['slug' => 'nothing-live']);
    $venue = Venue::factory()->create(['name' => 'The Hall']);
    Venue::factory()->create(['name' => 'Pending', 'status' => Venue::PENDING]);
    Venue::factory()->create(['name' => 'Merged', 'merged_into_id' => $venue->id]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain('<loc>'.url('/events/ha-ha-ha/chicago-2026').'</loc>')
        ->toContain('<loc>'.url('/events/ha-ha-ha').'</loc>')
        ->toContain('<loc>'.url('/events/plain').'</loc>')
        ->toContain('<loc>'.url('/venues/the-hall').'</loc>')
        ->not->toContain('nothing-live')
        ->not->toContain('/venues/pending')
        ->not->toContain('/venues/merged');
});

test('an organizer that only made a public show is in the sitemap', function () {
    $maker = $this->project->organizer->fresh();
    $maker->update(['status' => 'p']);
    $unlisted = Organizer::factory()->create(['status' => 'p']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.url('/organizers/'.$maker->slug).'</loc>')
        ->not->toContain('/organizers/'.$unlisted->slug.'<');
});

test('notifications carry the address, and the emails link it', function () {
    $user = User::factory()->create();

    expect((new SavedEventNewDatesNotification($this->run))->toDatabase($user)['event_url'])->toBe('/events/ha-ha-ha/chicago-2026')
        ->and((new FollowedOrganizerNewEventNotification($this->run))->toDatabase($user)['event_url'])->toBe('/events/ha-ha-ha/chicago-2026');

    foreach ([new FollowedOrganizerNewEventMail($this->run), new SavedEventNewDatesMail($this->run)] as $mail) {
        $mail->assertSeeInHtml(url('/events/ha-ha-ha/chicago-2026'), false);
    }
});

test('the hub, and duplicate warnings, carry the address', function () {
    $check = app(App\Actions\Events\CheckDuplicateEventNames::class)->handle('Ha Ha Ha');

    expect($check->first()['public_url'])->toBe('/events/ha-ha-ha/chicago-2026');
});

test('search console tells shows, runs, venues, moved and gone addresses apart', function () {
    Venue::factory()->create(['name' => 'The Hall']);
    SlugRedirect::remember('events/an-old-name', $this->plain);
    SlugRedirect::remember('venues/old-hall', Venue::first());
    $other = publishedListing('Later Run', 'Rome');
    $other->update(['project_id' => $this->project->id, 'instance_slug' => 'rome-2026']);

    $report = new ReflectionMethod(SearchConsoleReport::class, 'withPageNames');
    $row = fn (string $page) => (object) ['key' => $page, 'clicks' => 1, 'impressions' => 2, 'position_sum' => 2];

    $pages = ['/events/ha-ha-ha', '/events/ha-ha-ha/chicago-2026', '/events/plain', '/events/an-old-name',
        '/events/later-run', '/events/ha-ha-ha/typo-2026', '/events/never-was', '/venues/the-hall', '/venues/old-hall',
        '/venues/never-was', '/organizers/never-was', '/help', '/events/a/b/c'];

    $rows = $report->invoke(app(SearchConsoleReport::class), array_map($row, $pages));

    expect(collect($rows)->pluck('kind', 'page')->all())->toBe([
        '/events/ha-ha-ha' => 'project',
        '/events/ha-ha-ha/chicago-2026' => 'event',
        '/events/plain' => 'event',
        '/events/an-old-name' => 'moved',
        '/events/later-run' => 'moved',
        '/events/ha-ha-ha/typo-2026' => 'moved',
        '/events/never-was' => 'gone',
        '/venues/the-hall' => 'venue',
        '/venues/old-hall' => 'moved',
        '/venues/never-was' => 'gone',
        '/organizers/never-was' => 'gone',
        '/help' => 'page',
        '/events/a/b/c' => 'page',
    ]);

    expect(collect($rows)->firstWhere('page', '/events/ha-ha-ha')['name'])->toBe('Ha Ha Ha')
        ->and(collect($rows)->firstWhere('page', '/events/ha-ha-ha/chicago-2026')['id'])->toBe($this->run->id);
});

test('a page view of a run counts as an event view in the report', function () {
    $insert = fn (string $page, int $bot = 0) => DB::table('analytics_events')->insert([
        'type' => Analytics::PAGE_VIEW,
        'page' => $page,
        'path' => '/x',
        'event_id' => $this->run->id,
        'visitor' => str_repeat('a', 16),
        'bot' => $bot,
        'occurred_at' => now()->subHour(),
    ]);
    $insert('events.instance');
    $insert('events.show');
    $insert('projects.show');

    $report = app(App\Actions\Analytics\SiteAnalyticsReport::class);
    $sql = new ReflectionMethod($report, 'eventViewSql');

    expect(DB::table('analytics_events')->whereRaw($sql->invoke($report))->count())->toBe(2);
});
