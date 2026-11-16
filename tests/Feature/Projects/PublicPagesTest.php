<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\RemoteLocation;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\Venue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->project = Project::factory()->create(['name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha', 'description' => 'A show about laughing.']);
});

function runOf(Project $project, string $city, array $overrides = [], string $firstShow = '2026-12-10 00:00:00'): Event
{
    $run = publishedListing($overrides['name'] ?? $project->name, $city, $overrides, null, $firstShow);
    $run->update(['project_id' => $project->id, 'instance_slug' => Str::slug($city).'-'.$run->id]);

    return $run->fresh();
}

function ticketOn(Event $run, float $price, string $currency = 'USD'): void
{
    $run->tickets()->create(['name' => 'GA '.$price, 'ticket_price' => $price, 'currency' => $currency]);
}

function zoomRun(Project $project, array $platforms = ['Zoom']): Event
{
    // Dated from today: a fixed date stops being "coming up" once it passes.
    $run = runOf($project, 'Nowhere', ['hasLocation' => false, 'attendance_type_id' => 2], now()->addDays(20)->format('Y-m-d').' 00:00:00');

    foreach ($platforms as $name) {
        $run->remotelocations()->attach(RemoteLocation::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'user_id' => $run->user_id]));
    }

    return $run;
}

// ----- the project page -----

test('the page carries the name, about, maker, count and a canonical of its own', function () {
    runOf($this->project, 'Chicago');
    runOf($this->project, 'Boston');

    $this->get('/events/ha-ha-ha')->assertOk()
        ->assertViewIs('projects.show')
        ->assertSee('Ha Ha Ha')
        ->assertSee('A show about laughing.')
        ->assertSee($this->project->organizer->name)
        ->assertSee('<link rel="canonical" href="'.url('/events/ha-ha-ha').'"', false)
        ->assertSee('"@type": "EventSeries"', false)
        ->assertSee('data-run-count>2<', false);
});

test('runs split into now running, coming up and past', function () {
    runOf($this->project, 'Nowville', ['closingDate' => now()->addDays(5)], now()->subDays(5)->format('Y-m-d').' 00:00:00');
    runOf($this->project, 'Soonville', ['closingDate' => now()->addDays(40)], now()->addDays(20)->format('Y-m-d').' 00:00:00');
    runOf($this->project, 'Oldville', ['closingDate' => now()->subDays(40)], now()->subDays(60)->format('Y-m-d').' 00:00:00');

    $page = $this->get('/events/ha-ha-ha')->assertOk()->viewData('page');

    expect($page['now']->pluck('place')->join(' '))->toContain('Nowville')
        ->and($page['coming']->pluck('place')->join(' '))->toContain('Soonville')
        ->and($page['past']->pluck('place')->join(' '))->toContain('Oldville');

    $this->get('/events/ha-ha-ha')->assertSeeInOrder(['Nowville', 'Coming up', 'Soonville', 'Past', 'Oldville']);
});

test('past runs come ten a page, with a pager, and nothing current says so', function () {
    foreach (range(1, 12) as $i) {
        runOf($this->project, "Oldville{$i}", ['closingDate' => now()->subDays(40 + $i)], now()->subDays(60 + $i)->format('Y-m-d').' 00:00:00');
    }

    $first = $this->get('/events/ha-ha-ha')->assertOk()
        ->assertSee('No current events')
        ->assertSee('aria-label="Past runs pages"', false)
        ->assertSee('page=2#runs', false);
    expect($first->viewData('page')['past'])->toHaveCount(10);

    $second = $this->get('/events/ha-ha-ha?page=2')->assertOk();
    expect($second->viewData('page')['past'])->toHaveCount(2)
        ->and($second->viewData('page')['past']->total())->toBe(12);

    // A page past the end shows the last one, never an empty list.
    expect($this->get('/events/ha-ha-ha?page=99')->viewData('page')['past'])->toHaveCount(2);
});

test('first presented is the earliest run, latest is the current one', function () {
    runOf($this->project, 'Oldville', ['closingDate' => now()->subDays(400)], '2023-05-01 00:00:00');
    runOf($this->project, 'Nowville', ['closingDate' => now()->addDays(5)], now()->subDays(5)->format('Y-m-d').' 00:00:00');

    $page = $this->get('/events/ha-ha-ha')->viewData('page');

    expect($page['first']['place'])->toContain('Oldville')
        ->and($page['latest']['place'])->toContain('Nowville');
});

test('only published runs appear, and an embargoed one is not announced', function () {
    runOf($this->project, 'Chicago');
    runOf($this->project, 'Secretville', ['status' => 'e', 'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s')]);
    runOf($this->project, 'Draftville', ['status' => 'r']);

    $this->get('/events/ha-ha-ha')->assertOk()
        ->assertDontSee('Secretville')
        ->assertDontSee('Draftville')
        ->assertSee('data-run-count>1<', false);
});

test('the page is public only while a run is published', function () {
    $run = runOf($this->project, 'Chicago', ['status' => 'n']);

    $this->get('/events/ha-ha-ha')->assertNotFound();

    $run->update(['status' => 'p']);

    $this->get('/events/ha-ha-ha')->assertOk();
});

test('the from price is the cheapest current ticket, one per currency', function () {
    $current = runOf($this->project, 'Chicago', ['closingDate' => now()->addDays(10)]);
    ticketOn($current, 40);
    ticketOn($current, 25);
    ticketOn($current, 30, 'EUR');
    $other = runOf($this->project, 'Boston', ['closingDate' => now()->addDays(10)]);
    ticketOn($other, 35);

    $past = runOf($this->project, 'Oldville', ['closingDate' => now()->subDays(10)]);
    ticketOn($past, 5);
    $embargoed = runOf($this->project, 'Later', ['closingDate' => now()->addDays(10), 'status' => 'e']);
    ticketOn($embargoed, 1);

    $from = $this->get('/events/ha-ha-ha')->viewData('page')['from'];

    expect($from)->toHaveCount(2)
        ->and(collect($from)->join(' '))->toContain('$25')->toContain('€30');
});

test('with no current run there is no from price', function () {
    ticketOn(runOf($this->project, 'Oldville', ['closingDate' => now()->subDays(10)]), 5);

    expect($this->get('/events/ha-ha-ha')->viewData('page')['from'])->toBe([]);
});

test('a free current ticket reads free', function () {
    ticketOn(runOf($this->project, 'Chicago'), 0);

    expect($this->get('/events/ha-ha-ha')->viewData('page')['from'])->toBe(['Free']);
});

test('run type and festival are no longer shown: tags cover them', function () {
    runOf($this->project, 'Austin', ['run_type' => 'world_premiere', 'festival' => 'SXSW']);

    $this->get('/events/ha-ha-ha')->assertOk()->assertDontSee('World premiere')->assertDontSee('SXSW');
});

test('a run with its own name shows it exactly as typed', function () {
    runOf($this->project, 'Boston', ['name' => 'Ha Ha Ha: Holiday Edition']);

    $this->get('/events/ha-ha-ha')->assertSee('Ha Ha Ha: Holiday Edition');
});

test('"Presented by" shows only for a company other than the show\'s maker', function () {
    runOf($this->project, 'Boston')->update(['organizer_id' => $this->project->organizer_id]);
    runOf($this->project, 'Chicago')->update(['organizer_id' => App\Models\Organizer::factory()->create(['name' => 'Guest Co', 'status' => 'p'])->id]);

    $html = $this->get('/events/ha-ha-ha')->assertOk()->getContent();

    // The maker's own run says nothing; the guest's run names its presenter.
    expect(substr_count($html, 'Presented by'))->toBe(1)
        ->and($html)->toContain('Guest Co');
});

test('a secret-location run shows its city and never its street', function () {
    $run = runOf($this->project, 'Chicago');
    Location::where('event_id', $run->id)->update([
        'hiddenLocationToggle' => true,
        'street' => '1 Hidden Lane',
        'home' => '99',
        'postal_code' => '60601',
        'venue' => 'The Secret Cellar',
    ]);

    $this->get('/events/ha-ha-ha')->assertOk()
        ->assertSee('Chicago')
        ->assertDontSee('Hidden Lane')
        ->assertDontSee('60601')
        ->assertDontSee('Secret Cellar');
});

test('an online run reads its platform, or Online with several', function () {
    zoomRun($this->project);
    zoomRun($this->project, ['Zoom', 'Discord']);

    $places = $this->get('/events/ha-ha-ha')->viewData('page')['coming']->pluck('place')->all();

    expect($places)->toContain('Zoom')->toContain('Online');
});

test('the page never shows credits', function () {
    $run = runOf($this->project, 'Chicago');
    App\Models\Credit::factory()->for($run, 'creditable')->for(App\Models\Artist::factory()->state(['name' => 'Jo Unseen']))->create();

    $this->get('/events/ha-ha-ha')->assertDontSee('Jo Unseen');
});

test('user text cannot run as Vue on the page', function () {
    $this->project->update(['description' => '{{ constructor.constructor("alert(1)")() }}']);
    runOf($this->project, 'Chicago');

    $body = preg_replace('#<script\b.*?</script>#s', '', Str::after($this->get('/events/ha-ha-ha')->getContent(), '<body id="app">'));

    expect($body)->not->toContain('{{');
});

test('line breaks survive: Vue collapses them in the page, so they are real breaks', function () {
    $this->project->update(['description' => "First line.\nSecond <b>line</b>."]);
    runOf($this->project, 'Chicago');

    $this->get('/events/ha-ha-ha')
        ->assertSee('First line.<br>', false)
        ->assertSee('&lt;b&gt;line&lt;/b&gt;', false);
});

test('a venue\'s directions keep their lines and cannot run as Vue, on its page and the listing', function () {
    $venue = Venue::factory()->create(['directions' => "Park here.\n{{ constructor }}"]);
    $listing = publishedListing('Plain', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id]);

    foreach ([$venue->publicPath(), '/events/plain'] as $path) {
        $body = preg_replace('#<script\b.*?</script>#s', '', Str::after($this->get($path)->assertOk()->getContent(), '<body id="app">'));

        expect($body)->toContain('Park here.<br>')->not->toContain('{{');
    }
});

test('analytics: a show is not an event view', function () {
    runOf($this->project, 'Chicago');
    $request = $this->get('/events/ha-ha-ha')->baseRequest;

    expect($request->attributes->get('analytics.page'))->toBe('projects.show')
        ->and($request->attributes->get('analytics.path'))->toBe('/events/ha-ha-ha')
        ->and($request->attributes->get('analytics.event_id'))->toBeNull();
});

// ----- the listing page's line -----

test('a linked run says what it is part of, and names two others', function () {
    $run = runOf($this->project, 'Chicago', ['closingDate' => now()->addDays(10)]);
    runOf($this->project, 'New York', ['closingDate' => now()->addDays(20)]);
    zoomRun($this->project)->update(['closingDate' => now()->addDays(30)]);
    runOf($this->project, 'Boston', ['closingDate' => now()->addDays(40)]);

    $line = $this->get($run->publicPath())->assertOk()
        ->assertSee('data-project-line', false)
        ->viewData('projectLine');

    expect($line['also'])->toBe('Also in New York and on Zoom.');
});

test('two cities read as one list', function () {
    $run = runOf($this->project, 'Chicago');
    runOf($this->project, 'Rome', ['closingDate' => now()->addDays(10)]);
    runOf($this->project, 'Paris', ['closingDate' => now()->addDays(20)]);

    expect($this->get($run->publicPath())->viewData('projectLine')['also'])->toBe('Also in Rome and Paris.');
});

test('the same city twice counts once, several platforms read online', function () {
    $run = runOf($this->project, 'Chicago');
    runOf($this->project, 'Rome', ['closingDate' => now()->addDays(10)]);
    runOf($this->project, 'Rome', ['closingDate' => now()->addDays(11)]);
    zoomRun($this->project, ['Zoom', 'Discord'])->update(['closingDate' => now()->addDays(20)]);

    expect($this->get($run->publicPath())->viewData('projectLine')['also'])->toBe('Also in Rome and online.');
});

test('a platform that is not a place reads naturally', function () {
    $run = runOf($this->project, 'Chicago');
    zoomRun($this->project, ['Web browser'])->update(['closingDate' => now()->addDays(5)]);

    expect($this->get($run->publicPath())->viewData('projectLine')['also'])->toBe('Also online.');
});

test('a phone line and a city read as one sentence', function () {
    $run = runOf($this->project, 'Chicago');
    runOf($this->project, 'Rome', ['closingDate' => now()->addDays(5)]);
    zoomRun($this->project, ['Telephone'])->update(['closingDate' => now()->addDays(6)]);

    expect($this->get($run->publicPath())->viewData('projectLine')['also'])->toBe('Also in Rome and by phone.');
});

test('past, embargoed and unpublished runs are not named', function () {
    $run = runOf($this->project, 'Chicago');
    runOf($this->project, 'Oldville', ['closingDate' => now()->subDays(10)]);
    runOf($this->project, 'Later', ['status' => 'e']);
    runOf($this->project, 'Draftville', ['status' => 'r']);

    expect($this->get($run->publicPath())->viewData('projectLine')['also'])->toBeNull();
    $this->get($run->publicPath())->assertSee('Part of')->assertDontSee('Also in');
});

test('the line appears in the mobile layout too', function () {
    $run = runOf($this->project, 'Chicago');
    runOf($this->project, 'Rome', ['closingDate' => now()->addDays(10)]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1')
        ->get($run->publicPath())->assertSee('data-project-line', false)->assertSee('Part of')->assertDontSee('Also in');
});

test('a plain listing has no line', function () {
    publishedListing('Plain', 'Chicago');

    $this->get('/events/plain')->assertOk()->assertDontSee('data-project-line', false)->assertDontSee('Part of');
});

test('the run\'s page reports its address to analytics', function () {
    $run = runOf($this->project, 'Chicago');

    $request = $this->get($run->publicPath())->baseRequest;

    expect($request->attributes->get('analytics.path'))->toBe($run->publicPath())
        ->and($request->attributes->get('analytics.event_id'))->toBe($run->id);
});

// ----- the venue link on a listing -----

test('a known venue links from the listing, opens its directions and names the JSON-LD', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'room' => 'Albert Theatre', 'directions' => 'Park on Dearborn.']);
    $listing = publishedListing('Plain', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id, 'venue' => 'typed name']);

    $this->get('/events/plain')->assertOk()
        ->assertSee('href="'.$venue->publicPath().'"', false)
        ->assertSee('The Goodman, Albert Theatre')
        ->assertSee('Park on Dearborn.')
        ->assertSee('"name": "The Goodman"', false);
});

test('a free-text venue stays as it was', function () {
    $listing = publishedListing('Plain', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue' => 'A Warehouse']);

    $this->get('/events/plain')->assertSee('A Warehouse')->assertSee('"name": "A Warehouse"', false)->assertDontSee('How to get there');
});

test('a secret-location listing never reads its venue', function () {
    $venue = Venue::factory()->create(['name' => 'Should Not Show', 'directions' => 'Nor this']);
    $listing = publishedListing('Plain', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id, 'hiddenLocationToggle' => true]);

    $this->get('/events/plain')->assertOk()->assertDontSee('Should Not Show')->assertDontSee('Nor this')->assertDontSee($venue->publicPath());
});

test('a pending venue is not linked', function () {
    $venue = Venue::factory()->create(['name' => 'Pending Hall', 'status' => Venue::PENDING]);
    $listing = publishedListing('Plain', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id]);

    $this->get('/events/plain')->assertDontSee($venue->publicPath());
});

// ----- the venue page -----

test('a venue shows its place, what is on now and before, and how to get there', function () {
    $venue = Venue::factory()->create([
        'name' => 'The Goodman', 'room' => 'Albert Theatre', 'street' => '170 N Dearborn', 'city' => 'Chicago',
        'directions' => 'Park on Dearborn.', 'latitude' => 41.88, 'longitude' => -87.63,
    ]);
    $now = publishedListing('Current Show', 'Chicago');
    $old = publishedListing('Old Show', 'Chicago', ['closingDate' => now()->subDays(30)]);
    Location::whereIn('event_id', [$now->id, $old->id])->update(['venue_id' => $venue->id]);

    $this->get($venue->publicPath())->assertOk()
        ->assertViewIs('venues.show')
        ->assertSee('Albert Theatre')
        ->assertSee('170 N Dearborn')
        ->assertSee('Park on Dearborn.')
        ->assertSee('vue-venue-map', false)
        ->assertSee('Chicago');
});

test('only published, non-hidden, non-deleted runs are listed', function () {
    $venue = Venue::factory()->create();
    $shown = publishedListing('Shown', 'Chicago');
    $hidden = publishedListing('Hidden', 'Hiddenville');
    $draft = publishedListing('Draft', 'Draftville', ['status' => 'r']);
    $deleted = publishedListing('Deleted', 'Deletedville');
    Location::whereIn('event_id', [$shown->id, $hidden->id, $draft->id, $deleted->id])->update(['venue_id' => $venue->id]);
    Location::where('event_id', $hidden->id)->update(['hiddenLocationToggle' => true]);
    $deleted->delete();

    $current = $this->get($venue->publicPath())->assertOk()->viewData('current');

    expect($current->pluck('id')->all())->toBe([$shown->id]);
});

test('a pending venue, and a name nobody has, are 404s', function () {
    $this->get(Venue::factory()->create(['status' => Venue::PENDING])->publicPath())->assertNotFound();
    $this->get('/venues/no-such-place')->assertNotFound();
});

test('a merged venue resolves to its winner, through a chain', function () {
    $winner = Venue::factory()->create(['name' => 'Right']);
    $middle = Venue::factory()->create(['name' => 'Middle', 'merged_into_id' => $winner->id]);
    $first = Venue::factory()->create(['name' => 'First', 'merged_into_id' => $middle->id]);

    $this->get($first->publicPath())->assertRedirect('/venues/right')->assertStatus(301);
});

test('a renamed venue\'s old address redirects', function () {
    $venue = Venue::factory()->create(['name' => 'Old Hall']);
    $venue->update(['slug' => 'new-hall']);

    $this->get('/venues/old-hall')->assertRedirect('/venues/new-hall')->assertStatus(301);
});

test('analytics: a venue is its own page', function () {
    expect(App\Http\Middleware\RecordPageView::PAGES)->toContain('venues.show')->toContain('events.instance');
});

// ----- organizer pages -----

function organizerPage(Organizer $organizer, int $page = 1, int $pageSize = 10)
{
    return test()->getJson("/api/organizers/{$organizer->slug}/events?page={$page}&pageSize={$pageSize}")->assertOk();
}

test('a company with no shows keeps its first render too', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    publishedListing('Running', 'Chicago', [], $organizer);

    $page = $this->get($organizer->publicPath())->viewData('organizer');

    expect($page->events->first())->toBeInstanceOf(Event::class);
});

test('a company with no shows lists exactly what it always did', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $running = publishedListing('Running', 'Chicago', [], $organizer);
    $ended = publishedListing('Ended', 'Chicago', ['closingDate' => now()->subDays(10)], $organizer);
    publishedListing('Archived', 'Chicago', ['archived' => true], $organizer);

    $response = organizerPage($organizer);

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$running->id, $ended->id])
        ->and($response->json('total'))->toBe(2);
});

test('a show appears once, however many runs the company presents', function () {
    $maker = $this->project->organizer;
    runOf($this->project, 'Chicago', ['organizer_id' => $maker->id]);
    runOf($this->project, 'Boston', ['organizer_id' => $maker->id]);
    runOf($this->project, 'Rome', ['organizer_id' => $maker->id]);
    $plain = publishedListing('Plain', 'Chicago', [], $maker);

    $response = organizerPage($maker);
    $data = collect($response->json('data'));

    expect($response->json('total'))->toBe(2)
        ->and($data->where('id', 'project-'.$this->project->id)->sole())->toMatchArray([
            'public_url' => '/events/ha-ha-ha',
            'show' => ['original' => true, 'runs' => 3, 'depth' => 3],
        ])
        ->and($data->pluck('id')->all())->toContain($plain->id);
});

test('the stack is never more than three deep, the count is the truth', function () {
    foreach (['A', 'B', 'C', 'D', 'E'] as $city) {
        runOf($this->project, $city);
    }

    $show = organizerPage($this->project->organizer)->json('data.0.show');

    expect($show)->toBe(['original' => true, 'runs' => 5, 'depth' => 3]);
});

test('a maker that presents no run still lists its show as Original', function () {
    runOf($this->project, 'Chicago');

    $this->get($this->project->organizer->publicPath())->assertOk();

    expect(organizerPage($this->project->organizer)->json('data.0.show.original'))->toBeTrue();
});

test('a maker\'s page starts with its show in the first render', function () {
    runOf($this->project, 'Chicago');

    $organizer = $this->get($this->project->organizer->publicPath())->viewData('organizer');

    expect($organizer->events->first()['id'])->toBe('project-'.$this->project->id);
});

test('a presenter lists the show without the Original mark', function () {
    $presenter = Organizer::factory()->create(['status' => 'p']);
    runOf($this->project, 'Chicago', ['organizer_id' => $presenter->id]);

    expect(organizerPage($presenter)->json('data.0.show.original'))->toBeFalse();
});

test('an embargoed run never announces a presenter early', function () {
    $presenter = Organizer::factory()->create(['status' => 'p']);
    runOf($this->project, 'Rome');
    runOf($this->project, 'Chicago', ['organizer_id' => $presenter->id, 'status' => 'e']);

    expect(organizerPage($presenter)->json('total'))->toBe(0);
});

test('an archived run does not make a presenter either', function () {
    $presenter = Organizer::factory()->create(['status' => 'p']);
    runOf($this->project, 'Rome');
    runOf($this->project, 'Chicago', ['organizer_id' => $presenter->id, 'archived' => true]);

    expect(organizerPage($presenter)->json('total'))->toBe(0);
});

test('a project with no published run is not listed for its maker', function () {
    runOf($this->project, 'Chicago', ['status' => 'n']);

    expect(organizerPage($this->project->organizer)->json('total'))->toBe(0);
});

test('the dedupe happens before pagination: pages are full and nothing repeats', function () {
    $maker = $this->project->organizer;
    foreach (range(1, 4) as $i) {
        runOf($this->project, "City {$i}", ['organizer_id' => $maker->id]);
    }
    foreach (range(1, 5) as $i) {
        publishedListing("Plain {$i}", 'Chicago', [], $maker);
    }

    $first = organizerPage($maker, 1, 3);
    $second = organizerPage($maker, 2, 3);

    $ids = collect($first->json('data'))->concat($second->json('data'))->pluck('id');

    expect($first->json('total'))->toBe(6)
        ->and($first->json('last_page'))->toBe(2)
        ->and($first->json('data'))->toHaveCount(3)
        ->and($second->json('data'))->toHaveCount(3)
        ->and($ids->unique()->count())->toBe(6);
});

test('running shows sort before ended ones, across listings and shows', function () {
    $maker = $this->project->organizer;
    runOf($this->project, 'Oldville', ['organizer_id' => $maker->id, 'closingDate' => now()->subDays(10)]);
    $running = publishedListing('Running', 'Chicago', [], $maker);

    $data = organizerPage($maker)->json('data');

    expect($data[0]['id'])->toBe($running->id)
        ->and($data[1]['isShowing'])->toBeFalse();
});

test('a plain listing\'s page and JSON keep their address', function () {
    $listing = publishedListing('Plain', 'Chicago');

    expect($listing->fresh()->toArray()['public_url'])->toBe('/events/plain');
    $this->get('/events/plain')->assertSee('<link rel="canonical" href="'.url('/events/plain').'"', false);
});

test('a moved old address, a project and a venue never loop', function () {
    $run = runOf($this->project, 'Chicago');
    SlugRedirect::remember('events/an-old-name', $run);

    $this->get('/events/an-old-name')->assertRedirect($run->publicPath());
});
