<?php

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Projects\CopyCredits;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\MergeProjects;
use App\Actions\Projects\PrefillFromShow;
use App\Actions\Projects\RequestLink;
use App\Actions\Projects\SetCredits;
use App\Actions\Projects\SuggestShows;
use App\Actions\Projects\UnlinkEvent;
use App\Actions\Projects\UpdateProject;
use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Image;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventNotificationDispatcher;
use App\Services\GeocodingService;
use App\Support\Projects\InstanceAddress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeSearchEngine;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
    $this->stranger = Organizer::factory()->create(['status' => 'p'])->owner;
});

function runIn(Project $project, string $city, string $instance, array $overrides = []): Event
{
    $run = publishedListing($overrides['name'] ?? 'Run in '.$city, $city, $overrides);
    $run->update(['project_id' => $project->id, 'instance_slug' => $instance]);

    return $run->fresh();
}

function pictureFor($model, string $folder): Image
{
    Storage::disk('digitalocean')->put("/public/{$folder}/a.webp", 'large');
    Storage::disk('digitalocean')->put("/public/{$folder}/a-thumb.webp", 'thumb');

    return $model->images()->create(['large_image_path' => "{$folder}/a.webp", 'thumb_image_path' => "{$folder}/a-thumb.webp", 'rank' => 0]);
}

// ----- 1. embargoed discovery -----

test('a show with no published run is not suggested to a stranger, but is to its own company', function () {
    $hidden = Project::factory()->create(['name' => 'Unannounced Show']);
    runIn($hidden, 'Paris', 'paris-2027', ['status' => 'e']);
    publicProject(['name' => 'Unannounced Show Public']);

    $names = fn (?User $viewer) => collect(app(SuggestShows::class)->handle('Unannounced Show', null, $viewer))->pluck('name')->all();

    expect($names($this->stranger))->toBe(['Unannounced Show Public'])
        ->and($names(null))->toBe(['Unannounced Show Public'])
        ->and($names($hidden->organizer->owner))->toContain('Unannounced Show')
        ->and($names($this->moderator))->toContain('Unannounced Show');
});

test('the suggest route reads as the signed-in person', function () {
    Project::factory()->create(['name' => 'Unannounced Show']);

    $this->actingAs($this->stranger)->getJson('/api/projects/suggest?keywords=Unannounced')->assertOk()->assertJsonCount(0);
});

test('someone else\'s embargoed listing does not surface in the duplicate check', function () {
    $project = Project::factory()->create(['slug' => 'secret-show']);
    runIn($project, 'Paris', 'paris-2027', ['name' => 'Same Name', 'status' => 'e']);

    expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $this->stranger))->toBeNull();
});

test('its own team and a moderator still see it, with its address', function () {
    $run = runIn(Project::factory()->create(['slug' => 'secret-show']), 'Paris', 'paris-2027', ['name' => 'Same Name', 'status' => 'e']);

    foreach ([$run->organizer->owner, $this->moderator] as $viewer) {
        expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $viewer)->first()['public_url'])->toBe('/events/secret-show/paris-2027');
    }
});

test('someone else\'s draft warns for the claim when its company can be claimed, and gives no address', function () {
    $staff = User::factory()->create(['type' => 'm']);
    $unclaimed = Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'p']);
    $draft = publishedListing('Same Name', 'Paris', ['status' => 'r'], $unclaimed);

    $row = app(CheckDuplicateEventNames::class)->handle('Same Name', null, $this->stranger)->first();

    expect($row['slug'])->toBeNull()
        ->and($row['public_url'])->toBeNull()
        ->and($row['organizer']['id'])->toBe($draft->organizer_id);
});

test('a stranger cannot ask to join what they may not know about, and every refusal reads alike', function () {
    $listing = publishedListing('Mine', 'Rome', [], Organizer::where('user_id', $this->stranger->id)->first());
    $embargoed = publishedListing('Unannounced', 'Paris', ['status' => 'e']);
    $hiddenShow = Project::factory()->create();

    $this->actingAs($this->stranger);

    foreach ([['source_event_id' => $embargoed->id], ['project_id' => $hiddenShow->id], ['source_event_id' => 99999999]] as $ask) {
        $this->postJson("/hosting/event/{$listing->slug}/link-request", $ask)
            ->assertStatus(422)
            ->assertJsonPath('errors.project.0', RequestLink::UNAVAILABLE);
    }

    expect(ProjectLinkRequest::count())->toBe(0);
});

test('a team can still ask to join its own embargoed listing', function () {
    $organizer = Organizer::where('user_id', $this->stranger->id)->first();
    $listing = publishedListing('Mine', 'Rome', [], $organizer);
    $own = publishedListing('Mine Too', 'Paris', ['status' => 'e'], $organizer);

    expect(app(RequestLink::class)->handle($listing, $own, $this->stranger)->source_event_id)->toBe($own->id);
});

// ----- 2. the credits panel -----

test('the credits panel lists only published runs and the caller\'s own', function () {
    $project = publicProject();
    $run = runIn($project, 'Lima', 'lima-2026');
    $hidden = runIn($project, 'Secretville', 'secretville-2027', ['status' => 'e']);
    $own = runIn($project, 'Ownville', 'ownville-2027', ['status' => 'e', 'organizer_id' => $run->organizer_id]);

    $ids = fn (User $user) => collect($this->actingAs($user)->getJson("/hosting/event/{$run->slug}/credits")->assertOk()->json('other_runs'))->pluck('id');

    expect($ids($run->organizer->owner))->toContain($own->id)->not->toContain($hidden->id)
        ->and($ids(User::factory()->create(['type' => 'c'])))->not->toContain($hidden->id)->not->toContain($own->id)
        ->and($ids($this->moderator))->toContain($hidden->id);
});

test('credits cannot be copied from a sibling\'s unannounced run', function () {
    $project = publicProject();
    $run = runIn($project, 'Lima', 'lima-2026');
    $hidden = runIn($project, 'Secretville', 'secretville-2027', ['status' => 'e']);
    Credit::factory()->for($hidden, 'creditable')->create();

    expect(fn () => app(CopyCredits::class)->handle($hidden, $run, $run->organizer->owner))->toThrow(ValidationException::class);
});

// ----- 3. secret destinations -----

test('a secret draft takes no venue or address from an earlier run', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $venue = Venue::factory()->create(['name' => 'The Goodman']);
    $own = publishedListing('Mine', 'Boston', [], $organizer);
    $own->location->update(['venue_id' => $venue->id, 'venue' => 'The Goodman', 'street' => '1 Own St']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create(['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email']);

    app(PrefillFromShow::class)->handle($draft->fresh(), $own->fresh(), $organizer->owner);

    expect($draft->fresh()->location->venue_id)->toBeNull()
        ->and($draft->fresh()->location->venue)->toBeNull()
        ->and($draft->fresh()->location->street)->toBeNull();
});

test('going secret takes the venue\'s name off with its id', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create([]);
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'latitude' => 41.8, 'longitude' => -87.6]);
    $this->actingAs($organizer->owner);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['venue_id' => $venue->id]])->assertOk();
    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['venue' => 'The Goodman', 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email']])->assertOk();

    expect($draft->fresh()->location->venue)->toBeNull();
});

test('a secret listing still holding a venue names it nowhere on its page', function () {
    $listing = publishedListing('Secret Show', 'Chicago');
    $venue = Venue::factory()->create(['name' => 'Hidden Hall']);
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id, 'venue' => 'Hidden Hall', 'hiddenLocationToggle' => true]);

    $response = $this->get('/events/secret-show')->assertOk()->assertDontSee('Hidden Hall');

    expect($response->getContent())->not->toContain('venue_id');
});

test('a free-text name on a secret listing stays, as it always has', function () {
    $listing = publishedListing('Secret Show', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue' => 'Somewhere Downtown', 'hiddenLocationToggle' => true]);

    $this->get('/events/secret-show')->assertOk()->assertSee('Somewhere Downtown');
});

// ----- 4. get-event -----

test('a read-only key sees no pending venue; the team does', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $run = publishedListing('Run', 'Rome', [], $organizer);
    $run->location->update(['venue_id' => Venue::factory()->create(['name' => 'Pending Hall', 'status' => Venue::PENDING])->id, 'venue' => 'typed']);

    viaToken(User::factory()->create(['email_verified_at' => now()]), [User::READ_SCOPE])
        ->tool(GetEvent::class, ['event_slug' => $run->slug])->assertOk()->assertDontSee('Pending Hall');

    viaToken($organizer->owner)->tool(GetEvent::class, ['event_slug' => $run->slug])->assertOk()->assertSee('Pending Hall');
});

test('a read-only key sees an approved venue', function () {
    $run = publishedListing('Run', 'Rome');
    $run->location->update(['venue_id' => Venue::factory()->create(['name' => 'Approved Hall'])->id]);

    viaToken(User::factory()->create(['email_verified_at' => now()]), [User::READ_SCOPE])
        ->tool(GetEvent::class, ['event_slug' => $run->slug])->assertOk()->assertSee('Approved Hall');
});

// ----- 5. set-credits -----

test('an empty list writes nothing for someone with no rights', function () {
    $project = Project::factory()->create();
    $run = publishedListing('Run', 'Rome');

    foreach ([$project, $run] as $target) {
        expect(fn () => app(SetCredits::class)->handle($target, [], $this->stranger))->toThrow(AuthorizationException::class);
    }

    expect(AuditLog::where('action', 'credits.set')->count())->toBe(0);
});

test('the team can still clear its own list', function () {
    $run = publishedListing('Run', 'Rome');
    Credit::factory()->for($run, 'creditable')->create();

    app(SetCredits::class)->handle($run, [], $run->organizer->owner);

    expect($run->credits()->count())->toBe(0);
});

// ----- 6. files and rollbacks -----

test('approval moves its files after the commit, outside any transaction it opened', function () {
    $listing = publishedListing('Pending', 'Rome', ['status' => 'r']);
    pictureFor($listing, 'event-images/pending-temp');

    $levels = [];
    Image::updated(function () use (&$levels) {
        $levels[] = DB::transactionLevel();
    });
    $resting = DB::transactionLevel();

    $this->actingAs($this->moderator)->postJson("/api/admin/approve/events/{$listing->slug}/approve")->assertOk();

    expect($levels)->not->toBeEmpty()->and(max($levels))->toBe($resting);
    Storage::disk('digitalocean')->assertExists('/public/'.$listing->images()->first()->large_image_path);
});

test('a failed keeper save keeps the old picture on disk', function () {
    $project = Project::factory()->create(['slug' => 'a-show']);
    $image = pictureFor($project, 'project-images/a-show');
    AuditLog::creating(fn () => throw new RuntimeException('database hiccup'));

    $this->actingAs($project->organizer->owner)->post('/hosting/projects/a-show', [
        'image' => UploadedFile::fake()->image('new.jpg', 900, 1300),
    ], ['Accept' => 'application/json'])->assertStatus(500);

    expect(Image::find($image->id))->not->toBeNull();
    Storage::disk('digitalocean')->assertExists('/public/project-images/a-show/a.webp');
    expect(Storage::disk('digitalocean')->allFiles('public/project-images/a-show'))->toHaveCount(2);
});

test('a rolled-back link leaves no copied files behind', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    pictureFor($first, 'event-images/ha-ha-ha');
    $second = publishedListing('Ha Ha Ha', 'Boston');
    $this->actingAs($this->moderator);

    // The last write of the link fails, after the project and its picture.
    AuditLog::creating(function (AuditLog $log) {
        if ($log->action === 'project.linked' && str_contains(json_encode($log->details), 'boston')) {
            throw new RuntimeException('database hiccup');
        }
    });

    expect(fn () => app(LinkEventToProject::class)->link($second, $first, $this->moderator))->toThrow(RuntimeException::class);

    expect(Project::count())->toBe(0)
        ->and(Storage::disk('digitalocean')->allFiles('public/project-images'))->toBe([]);
});

test('the project still gets its picture when the link commits', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    pictureFor($first, 'event-images/ha-ha-ha');
    $this->actingAs($this->moderator);

    app(LinkEventToProject::class)->link(publishedListing('Ha Ha Ha', 'Boston'), $first, $this->moderator);

    expect(Project::sole()->largeImagePath)->toStartWith('project-images/ha-ha-ha/');
});

// ----- 7. nested addresses on a rename -----

/** Merge A into B, each with a Chicago run: A's takes chicago-2026-2 and reserves A's path. */
function mergedShows(): array
{
    $a = Project::factory()->create(['slug' => 'show-a']);
    $b = Project::factory()->create(['slug' => 'show-b']);
    $fromA = runIn($a, 'Chicago', 'chicago-2026');
    $own = runIn($b, 'Chicago', 'chicago-2026');

    app(MergeProjects::class)->handle($a, $b, test()->moderator);

    return [$b->refresh(), $fromA->fresh(), $own->fresh()];
}

test('renaming onto a merged show\'s address moves a run off the reserved path', function () {
    [$b, $fromA, $own] = mergedShows();

    expect($fromA->instance_slug)->toBe('chicago-2026-2');

    app(UpdateProject::class)->handle($b, ['slug' => 'show-a'], $this->moderator);

    $own->refresh();

    expect($own->instance_slug)->not->toBe('chicago-2026')
        ->and(SlugRedirect::where('path', 'events/show-a/chicago-2026')->sole()->redirectable_id)->toBe($fromA->id);

    // The reserved path leads to the run it was reserved for.
    $this->get('/events/show-a/chicago-2026')->assertRedirect($fromA->fresh()->publicPath());
    // The moved run answers at its new address, and its old one leads to it.
    $this->get($own->publicPath())->assertOk();
    $this->get('/events/show-b/chicago-2026')->assertRedirect($own->publicPath());
});

test('rename, unlink and rename back leave every address leading the right way', function () {
    [$b, $fromA, $own] = mergedShows();
    $update = app(UpdateProject::class);

    $update->handle($b, ['slug' => 'show-a'], $this->moderator);
    $moved = $own->fresh()->publicPath();

    app(UnlinkEvent::class)->handle($own->fresh(), $this->moderator);
    $plain = $own->fresh()->publicPath();

    $update->handle($b->refresh(), ['slug' => 'show-b'], $this->moderator);

    $this->get($moved)->assertRedirect($plain);
    $this->get('/events/show-b/chicago-2026')->assertRedirect($plain);
    $this->get('/events/show-a/chicago-2026')->assertRedirect($fromA->fresh()->publicPath());
    $this->get($fromA->fresh()->publicPath())->assertOk();
    $this->get($plain)->assertOk();
});

test('a run that returns to one of its own old paths loses the alias', function () {
    $project = Project::factory()->create(['slug' => 'a-show']);
    $run = runIn($project, 'Chicago', 'chicago-2026');
    SlugRedirect::remember('events/other-name/chicago-2026', $run);

    $project->update(['slug' => 'other-name']);

    expect($run->fresh()->instance_slug)->toBe('chicago-2026')
        ->and(SlugRedirect::where('path', 'events/other-name/chicago-2026')->exists())->toBeFalse();
});

test('a project cannot take the slug of one of its own runs that it never went by', function () {
    $project = Project::factory()->create(['slug' => 'a-show']);
    runIn($project, 'Chicago', 'chicago-2026', ['name' => 'Second Run']);

    expect(Project::slugTaken('second-run', null, $project->id))->toBeTrue();
});

test('it can go back to an address it held, though its first listing still has that slug', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->actingAs($this->moderator);
    app(LinkEventToProject::class)->link(publishedListing('Ha Ha Ha', 'Boston'), $first, $this->moderator);
    $project = Project::sole();

    expect(Project::slugTaken('ha-ha-ha', null, $project->id))->toBeFalse();

    $project->update(['slug' => 'laughter']);

    expect(Project::slugTaken('ha-ha-ha', null, $project->id))->toBeFalse();
});

// ----- 8. no redirect to itself -----

test('a listing linked to a show that is gone answers at its address, with no redirect loop', function () {
    $listing = publishedListing('Orphan', 'Rome');
    Event::whereKey($listing->id)->update(['project_id' => 99999999, 'instance_slug' => 'rome-2026']);

    $this->get('/events/orphan')->assertOk();
});

// ----- 9. online listings and venues -----

test('an online listing cannot take a venue', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => false, 'attendance_type_id' => 2]);
    $draft->location()->create([]);

    $this->actingAs($organizer->owner)
        ->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['venue_id' => Venue::factory()->create()->id]])
        ->assertStatus(422);

    expect($draft->fresh()->location->venue_id)->toBeNull();
});

test('a venue page lists no online run, whatever a stale row says', function () {
    $venue = Venue::factory()->create();
    $online = publishedListing('Online Now', 'Rome', ['hasLocation' => false]);
    Location::where('event_id', $online->id)->update(['venue_id' => $venue->id]);

    expect($this->get($venue->publicPath())->assertOk()->viewData('current'))->toHaveCount(0);
});

// ----- 10. copying a list -----

test('copying keeps credits that differ by department, character or kind', function () {
    $from = publishedListing('From', 'Rome');
    $to = publishedListing('To', 'Paris');
    $artist = Artist::factory()->create();
    Credit::factory()->for($to, 'creditable')->create(['artist_id' => $artist->id, 'role' => 'Performer', 'department' => 'cast', 'character' => 'Hero', 'kind' => 'regular']);

    foreach ([['department' => 'production'], ['character' => 'Villain'], ['kind' => 'understudy']] as $difference) {
        Credit::factory()->for($from, 'creditable')->create(['artist_id' => $artist->id, 'role' => 'Performer', 'department' => 'cast', 'character' => 'Hero', 'kind' => 'regular', ...$difference]);
    }

    expect(app(CopyCredits::class)->handle($from, $to, $this->moderator))->toBe(3);
});

test('copying still skips the same line', function () {
    $from = publishedListing('From', 'Rome');
    $to = publishedListing('To', 'Paris');
    $artist = Artist::factory()->create();
    $line = ['artist_id' => $artist->id, 'role' => 'Performer', 'department' => 'cast', 'character' => 'Hero', 'kind' => 'regular'];
    Credit::factory()->for($from, 'creditable')->create($line);
    Credit::factory()->for($to, 'creditable')->create($line);

    expect(app(CopyCredits::class)->handle($from, $to, $this->moderator))->toBe(0);
});

test('the writer merge keeps its own comparison', function () {
    $a = Project::factory()->create();
    $b = Project::factory()->create();
    $artist = Artist::factory()->create();
    Credit::factory()->writing()->for($a, 'creditable')->create(['artist_id' => $artist->id, 'role' => 'Book', 'character' => 'x']);
    Credit::factory()->writing()->for($b, 'creditable')->create(['artist_id' => $artist->id, 'role' => 'Book', 'character' => 'y']);

    app(MergeProjects::class)->handle($a, $b, $this->moderator);

    expect($b->writers()->count())->toBe(1);
});

// ----- 11. one aggregate, however many runs -----

test('project and venue pages read show days once, however many runs', function () {
    $project = publicProject(['slug' => 'big-show']);
    $venue = Venue::factory()->create();

    foreach (['A', 'B', 'C', 'D', 'E'] as $city) {
        $run = runIn($project, 'City '.$city, 'city-'.strtolower($city));
        Location::where('event_id', $run->id)->update(['venue_id' => $venue->id]);
    }

    foreach (['/events/big-show', $venue->publicPath()] as $path) {
        $queries = collect();
        DB::listen(fn ($query) => $queries->push($query->sql));

        $this->get($path)->assertOk();

        expect($queries->filter(fn ($sql) => str_contains($sql, 'MIN(date)'))->count())->toBe(1);
    }
});

test('a batched first day is the same as a run\'s own', function () {
    $run = publishedListing('Run', 'Chicago', [], null, '2027-01-01 02:00:00');
    Show::factory()->create(['event_id' => $run->id, 'date' => '2027-03-01 19:00:00']);
    $empty = publishedListing('Always', 'Chicago', ['showtype' => 'a', 'published_at' => '2024-06-01 12:00:00']);

    $events = Event::whereKey([$run->id, $empty->id])->get();
    App\Support\Projects\InstanceAddress::primeFirstDays($events);

    foreach ($events as $event) {
        expect(App\Support\Projects\InstanceAddress::startDay($event))->toBe(App\Support\Projects\InstanceAddress::startDay(Event::find($event->id)));
    }
});

// ----- 12. the queue -----

test('the queue keeps the request and runs no name search; the review screen has the matches', function () {
    Project::factory()->create(['name' => 'Ha Ha Ha']);
    publicProject(['name' => 'Ha Ha Ha Too']);
    $listing = publishedListing('Ha Ha Ha', 'Rome', ['status' => 'r']);
    ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => Project::first()->id]);
    $this->actingAs($this->moderator);

    $queue = $this->getJson('/api/admin/approve/events')->assertOk();

    expect($queue->json('data.0.link_request.id'))->not->toBeNull()
        ->and($queue->json('data.0'))->not->toHaveKey('name_matches');

    expect($this->getJson("/api/admin/events/{$listing->slug}")->assertOk()->json('name_matches'))->not->toBeEmpty();
});

// ----- 13. venue pictures -----

test('a moderator sets a venue\'s picture, and replacing it clears the old one', function () {
    $venue = Venue::factory()->create(['name' => 'The Hall']);
    $this->actingAs($this->moderator);

    foreach (['first.jpg', 'second.jpg'] as $file) {
        $this->post('/api/admin/venues/the-hall/image', ['image' => UploadedFile::fake()->image($file, 900, 700)], ['Accept' => 'application/json'])->assertOk();
    }

    expect($venue->fresh()->largeImagePath)->toStartWith('venue-images/the-hall/')
        ->and($venue->images()->count())->toBe(1);

    Storage::disk('digitalocean')->assertExists('/public/'.$venue->fresh()->largeImagePath);
    $this->get('/venues/the-hall')->assertSee($venue->fresh()->largeImagePath);
});

test('only a moderator, and only a picture', function () {
    Venue::factory()->create(['name' => 'The Hall']);

    $this->actingAs($this->stranger)->post('/api/admin/venues/the-hall/image', ['image' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertForbidden();
    $this->actingAs($this->moderator)->post('/api/admin/venues/the-hall/image', ['image' => 'nope'], ['Accept' => 'application/json'])->assertStatus(422);
});

// ----- the second look -----

test('a team cannot ask to join its own draft, which approval would refuse anyway', function () {
    $organizer = Organizer::where('user_id', $this->stranger->id)->first();
    $listing = publishedListing('Mine', 'Rome', [], $organizer);

    foreach (['d', '0', 'r', 'n'] as $status) {
        $draft = publishedListing('Draft '.$status, 'Paris', ['status' => $status], $organizer);

        expect(fn () => app(RequestLink::class)->handle($listing, $draft, $this->stranger))
            ->toThrow(ValidationException::class, RequestLink::UNAVAILABLE);
    }

    expect(ProjectLinkRequest::count())->toBe(0);
});

test('a listing in review with an embargo ahead stays out of a stranger\'s duplicate check', function () {
    publishedListing('Same Name', 'Paris', ['status' => 'r', 'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s')]);

    expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $this->stranger))->toBeNull();
});

test('its own team still sees it, and a stranger gets no status for a plain draft', function () {
    $pending = publishedListing('Same Name', 'Paris', ['status' => 'r', 'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s')]);

    expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $pending->organizer->owner)->first()['status'])->toBe('r');

    $unclaimed = Organizer::factory()->create(['user_id' => User::factory()->create(['type' => 'm'])->id, 'status' => 'p']);
    publishedListing('Other Name', 'Paris', ['status' => 'r'], $unclaimed);

    expect(app(CheckDuplicateEventNames::class)->handle('Other Name', null, $this->stranger)->first()['status'])->toBeNull();
});

test('every address a show has gone by leads a moved run to its new home', function () {
    $a = Project::factory()->create(['slug' => 'show-a']);
    $b = Project::factory()->create(['slug' => 'first-name']);
    $fromA = runIn($a, 'Chicago', 'chicago-2026');
    $own = runIn($b, 'Chicago', 'chicago-2026');

    $b->update(['slug' => 'show-b']);
    app(MergeProjects::class)->handle($a, $b->refresh(), $this->moderator);
    app(UpdateProject::class)->handle($b->refresh(), ['slug' => 'show-a'], $this->moderator);

    $this->get('/events/first-name/chicago-2026')->assertRedirect($own->fresh()->publicPath());
    $this->get('/events/show-b/chicago-2026')->assertRedirect($own->fresh()->publicPath());
    $this->get('/events/show-a/chicago-2026')->assertRedirect($fromA->fresh()->publicPath());
});

test('a former path reserved for something else keeps its reservation', function () {
    $project = Project::factory()->create(['slug' => 'old-name']);
    $project->update(['slug' => 'next-name']);
    $run = runIn($project, 'Chicago', 'chicago-2026');
    $reservedHere = publishedListing('Reserved Here', 'Rome');
    $reservedThere = publishedListing('Reserved There', 'Rome');
    SlugRedirect::remember('events/new-name/chicago-2026', $reservedHere);
    SlugRedirect::remember('events/old-name/chicago-2026', $reservedThere);

    $project->update(['slug' => 'new-name']);

    expect($run->fresh()->instance_slug)->not->toBe('chicago-2026')
        ->and(SlugRedirect::where('path', 'events/old-name/chicago-2026')->sole()->redirectable_id)->toBe($reservedThere->id)
        ->and(SlugRedirect::where('path', 'events/next-name/chicago-2026')->sole()->redirectable_id)->toBe($run->id);
});

function draftWithVenue(array $venue = []): array
{
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create([]);
    $picked = Venue::factory()->create(['name' => 'The Goodman', 'latitude' => 41.8, 'longitude' => -87.6, ...$venue]);
    test()->actingAs($organizer->owner);
    test()->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['venue_id' => $picked->id]])->assertOk();

    return [$draft->fresh(), $picked];
}

test('going secret clears a name the venue left behind after the link had gone', function () {
    [$draft] = draftWithVenue();
    Location::where('event_id', $draft->id)->update(['venue_id' => null]);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email']])->assertOk();

    expect($draft->fresh()->location->venue)->toBeNull();
});

test('a typed name that is no venue survives going secret', function () {
    [$draft] = draftWithVenue();
    Location::where('event_id', $draft->id)->update(['venue_id' => null, 'venue' => 'Somewhere Downtown']);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email']])->assertOk();

    expect($draft->fresh()->location->venue)->toBe('Somewhere Downtown');
});

test('a listing already secret keeps its wording on a later save', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    Venue::factory()->create(['name' => 'The Goodman']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create(['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email', 'venue' => 'The Goodman']);

    $this->actingAs($organizer->owner)->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['hiddenLocation' => 'By text']])->assertOk();

    expect($draft->fresh()->location->venue)->toBe('The Goodman');
});

test('the wizard\'s own payload going secret takes the venue off instead of refusing', function () {
    [$draft, $venue] = draftWithVenue();

    $this->postJson("/api/hosting/event/{$draft->slug}", [
        'location' => ['venue_id' => $venue->id, 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'],
        'timezone' => 'America/Chicago',
    ])->assertOk();

    $location = $draft->fresh()->location;

    expect($location->venue_id)->toBeNull()
        ->and($location->venue)->toBeNull()
        ->and($location->hiddenLocationToggle)->toBeTruthy()
        ->and($location->hiddenLocation)->toBe('By email');
});

test('a different venue on a secret listing is still refused', function () {
    [$draft] = draftWithVenue();
    $other = Venue::factory()->create();

    $this->postJson("/api/hosting/event/{$draft->slug}", [
        'location' => ['venue_id' => $other->id, 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'],
    ])->assertStatus(422);
});

/** location.vue's payload once the picker has dropped the venue: every part, null or not. */
function wizardAddressPayload(Event $draft, array $own): array
{
    return ['location' => [
        ...$draft->location->only(['latitude', 'longitude', 'home', 'street', 'city', 'region', 'region_long', 'postal_code', 'country', 'country_long', 'venue']),
        'venue_id' => null,
        ...$own,
    ], 'timezone' => 'America/Los_Angeles'];
}

test('going secret from the filled-in form saves when the picked venue has empty address parts', function () {
    [$draft] = draftWithVenue(['city' => 'Las Vegas', 'home' => null, 'street' => null, 'region' => null, 'region_long' => null, 'country' => null, 'country_long' => null]);

    $this->postJson("/api/hosting/event/{$draft->slug}", wizardAddressPayload($draft, [
        'hiddenLocationToggle' => true,
        'hiddenLocation' => 'By email',
    ]))->assertOk();

    $location = $draft->fresh()->location;

    expect($location->hiddenLocationToggle)->toBeTruthy()
        ->and($location->venue_id)->toBeNull()
        ->and($location->venue)->toBeNull();
});

test('an empty address part clears what was there rather than keeping the last address', function () {
    [$draft] = draftWithVenue(['city' => 'Las Vegas', 'region' => 'NV', 'postal_code' => '89102']);

    $payload = wizardAddressPayload($draft, ['hiddenLocationToggle' => false, 'hiddenLocation' => null]);
    $payload['location']['city'] = 'Singapore';
    $payload['location']['region'] = '';
    $payload['location']['postal_code'] = null;

    $this->postJson("/api/hosting/event/{$draft->slug}", $payload)->assertOk();

    expect($draft->fresh()->location->region)->toBeNull()
        ->and($draft->fresh()->location->postal_code)->toBeNull();
});

test('a location still needs a city', function () {
    [$draft] = draftWithVenue();

    $payload = wizardAddressPayload($draft, ['hiddenLocationToggle' => false, 'hiddenLocation' => null]);
    $payload['location']['city'] = null;

    $this->postJson("/api/hosting/event/{$draft->slug}", $payload)->assertJsonValidationErrorFor('location.city');
});

// ----- third look -----

test('a run unlinked after a plain rename keeps its old path when the show goes back', function () {
    $project = Project::factory()->create(['slug' => 'show-a']);
    runIn($project, 'Boston', 'boston-2026');
    $chicago = runIn($project, 'Chicago', 'chicago-2026');
    $update = app(UpdateProject::class);

    $update->handle($project, ['slug' => 'show-b'], $this->moderator);
    app(UnlinkEvent::class)->handle($chicago->fresh(), $this->moderator);
    $plain = $chicago->fresh()->publicPath();

    $update->handle($project->refresh(), ['slug' => 'show-a'], $this->moderator);

    $this->get('/events/show-a/chicago-2026')->assertRedirect($plain);

    // The next Chicago run cannot take the address over.
    $next = publishedListing('Next Chicago', 'Chicago');

    expect(InstanceAddress::allocate($next, $project->refresh(), 'chicago-2026'))->not->toBe('chicago-2026');
});

test('unlinking leaves another record\'s reservation under a former address alone', function () {
    $project = Project::factory()->create(['slug' => 'show-a']);
    $chicago = runIn($project, 'Chicago', 'chicago-2026');
    $other = publishedListing('Someone Else', 'Rome');

    app(UpdateProject::class)->handle($project, ['slug' => 'show-b'], $this->moderator);
    SlugRedirect::remember('events/show-a/chicago-2026', $other);

    app(UnlinkEvent::class)->handle($chicago->fresh(), $this->moderator);

    expect(SlugRedirect::where('path', 'events/show-a/chicago-2026')->sole()->redirectable_id)->toBe($other->id);
});

/** A published secret listing still holding its venue, the state the rule guards against. */
function secretListingHoldingVenue(): Event
{
    $listing = publishedListing('Secret Show', 'Chicago');
    $venue = Venue::factory()->create(['name' => 'Hidden Hall']);
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id, 'venue' => 'Hidden Hall', 'hiddenLocationToggle' => true]);

    return $listing->fresh();
}

test('Saved Events does not name a secret listing\'s venue', function () {
    $listing = secretListingHoldingVenue();
    $this->actingAs($this->stranger);
    $listing->favorite();

    $this->getJson("/api/hub/events/{$listing->slug}")->assertOk()
        ->assertJsonPath('event.location.venue', null)
        ->assertDontSee('Hidden Hall');
});

test('Saved Events keeps the free-text name of a secret listing', function () {
    $listing = publishedListing('Secret Show', 'Chicago');
    Location::where('event_id', $listing->id)->update(['venue' => 'Somewhere Downtown', 'hiddenLocationToggle' => true]);
    $this->actingAs($this->stranger);
    $listing->favorite();

    $this->getJson("/api/hub/events/{$listing->slug}")->assertJsonPath('event.location.venue', 'Somewhere Downtown');
});

test('similar events do not name a secret listing\'s venue', function () {
    secretListingHoldingVenue();
    $beside = publishedListing('Next Door', 'Chicago');

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk()
        ->assertSee('Secret Show')
        ->assertDontSee('Hidden Hall');
});

test('search does not name a secret listing\'s venue', function () {
    $listing = secretListingHoldingVenue();
    FakeSearchEngine::install([$listing->id]);

    $response = $this->getJson('/api/index/search?city=Chicago%2C+IL&lat=41.88&lng=-87.63&searchType=inPerson&live=false')->assertOk();

    expect($response->json('data.0.location'))->not->toHaveKeys(['venue', 'venue_id'])
        ->and($response->getContent())->not->toContain('Hidden Hall');
});

test('similar events keep an open listing\'s venue', function () {
    $listing = publishedListing('Open Show', 'Chicago');
    $venue = Venue::factory()->create(['name' => 'Open Hall']);
    Location::where('event_id', $listing->id)->update(['venue_id' => $venue->id, 'venue' => 'Open Hall']);
    $beside = publishedListing('Next Door', 'Chicago');

    $location = collect($this->getJson("/api/events/{$beside->slug}/similar")->json('events'))->firstWhere('id', $listing->id)['location'];

    expect($location['venue'])->toBe('Open Hall')
        ->and($location['venue_id'])->toBe($venue->id);
});

test('an owner who opens a secret listing, turns secrecy off and saves keeps the venue', function () {
    [$draft, $venue] = draftWithVenue();
    Location::where('event_id', $draft->id)->update(['hiddenLocationToggle' => true]);

    // What the wizard was given, and what location.vue sends back from it.
    $seen = $this->get("/hosting/event/{$draft->slug}/edit")->assertOk()->viewData('event')->toArray()['location'];
    $own = ['hiddenLocationToggle' => false, 'hiddenLocation' => null];

    $this->postJson("/api/hosting/event/{$draft->slug}", $seen['venue_id']
        ? ['location' => ['venue_id' => $seen['venue_id'], ...$own]]
        : wizardAddressPayload($draft->fresh(), [...$own, 'venue' => $seen['venue']]))->assertOk();

    expect($draft->fresh()->location)
        ->venue_id->toBe($venue->id)
        ->venue->toBe('The Goodman');
});

test('the admin review of a secret listing still shows its venue', function () {
    $listing = secretListingHoldingVenue();

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$listing->slug}")->assertOk()
        ->assertJsonPath('location.venue', 'Hidden Hall');
});

test('address parts sent empty are not geocoded from the old address', function () {
    [$draft] = draftWithVenue(['home' => '170', 'street' => 'N Dearborn St', 'postal_code' => '60601', 'city' => 'Chicago', 'region' => 'IL', 'country' => 'US']);

    $this->mock(GeocodingService::class)->shouldReceive('search')->once()
        ->withArgs(fn (string $query) => $query === 'Chicago IL US')
        ->andReturn([['latitude' => 41.9, 'longitude' => -87.7]]);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => [
        'venue_id' => null, 'home' => null, 'street' => null, 'postal_code' => null, 'city' => 'Chicago',
    ]])->assertOk();

    expect($draft->fresh()->location)
        ->street->toBeNull()
        ->venue_id->toBeNull()
        ->latitude->toEqual(41.9);
});

test('address parts left out are still read from the old address', function () {
    [$draft] = draftWithVenue(['home' => '170', 'street' => 'N Dearborn St', 'postal_code' => '60601', 'city' => 'Chicago', 'region' => 'IL', 'country' => 'US']);

    $this->mock(GeocodingService::class)->shouldReceive('search')->once()
        ->withArgs(fn (string $query) => str_contains($query, 'N Dearborn St') && str_contains($query, 'Evanston'))
        ->andReturn([['latitude' => 42.0, 'longitude' => -87.7]]);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => ['venue_id' => null, 'city' => 'Evanston']])->assertOk();
});

test('a project picture that fails after the commit does not fail the approval', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    $picture = pictureFor($source, 'event-images/ha-ha-ha');
    $copy = publishedListing('Ha Ha Ha', 'Boston', ['status' => 'r']);
    ProjectLinkRequest::create(['event_id' => $copy->id, 'source_event_id' => $source->id]);

    Exceptions::fake();
    $dispatcher = $this->spy(EventNotificationDispatcher::class);
    Image::retrieved(function (Image $image) use ($picture) {
        if ($image->id === $picture->id) {
            throw new RuntimeException('database hiccup');
        }
    });

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$copy->slug}/approve", ['link' => ['action' => 'confirm']])
        ->assertOk();

    expect($copy->fresh()->status)->toBe('p')
        ->and(Project::count())->toBe(1);

    $dispatcher->shouldHaveReceived('newEventFromFollowedOrganizer')->once();
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'database hiccup');
});

test('a stranger\'s duplicate check says nothing of a draft under a company that cannot be claimed', function () {
    publishedListing('Same Name', 'Paris', ['status' => 'r']);
    publishedListing('Same Name', 'Rome', ['status' => '1']);

    expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $this->stranger))->toBeNull();
});

test('a draft\'s own company and a published listing still warn', function () {
    $draft = publishedListing('Same Name', 'Paris', ['status' => 'r']);

    expect(app(CheckDuplicateEventNames::class)->handle('Same Name', null, $draft->organizer->owner)->first()['id'])->toBe($draft->id);

    $live = publishedListing('Other Name', 'Rome');

    expect(app(CheckDuplicateEventNames::class)->handle('Other Name', null, $this->stranger)->first()['id'])->toBe($live->id);
});

test('update-event answers the same for a hidden show and one that does not exist', function () {
    $organizer = Organizer::where('user_id', $this->stranger->id)->first();
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $this->stranger->id, 'status' => '1', 'hasLocation' => true]);
    $hidden = Project::factory()->create();
    $full = 'That show is not available. The other fields were saved. Show ids come from list-projects.';

    foreach ([$hidden->id, $hidden->id + 1000] as $id) {
        EiServer::actingAs($this->stranger)
            ->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'project_id' => $id])
            ->assertHasErrors()
            ->assertSee($full);
    }
});

test('a merge leaves the old paths of a run unlinked earlier with that run', function () {
    $project = Project::factory()->create(['slug' => 'show-a']);
    runIn($project, 'Boston', 'boston-2026');
    $r = runIn($project, 'Chicago', 'chicago-2026');
    $update = app(UpdateProject::class);

    $update->handle($project, ['slug' => 'show-b'], $this->moderator);
    app(UnlinkEvent::class)->handle($r->fresh(), $this->moderator);
    $plain = $r->fresh()->publicPath();

    // A later Chicago run takes the same instance at the show's new address.
    $update->handle($project->refresh(), ['slug' => 'show-c'], $this->moderator);
    $s = runIn($project->refresh(), 'Chicago', 'chicago-2026', ['name' => 'Second Chicago']);

    app(MergeProjects::class)->handle($project->refresh(), Project::factory()->create(['slug' => 'show-d']), $this->moderator);

    $this->get('/events/show-a/chicago-2026')->assertRedirect($plain);
    $this->get('/events/show-b/chicago-2026')->assertRedirect($plain);
    $this->get('/events/show-c/chicago-2026')->assertRedirect($s->fresh()->publicPath());
});

test('a merge still carries a moved run\'s former paths with it', function () {
    $from = Project::factory()->create(['slug' => 'show-a']);
    $run = runIn($from, 'Chicago', 'chicago-2026');

    app(UpdateProject::class)->handle($from, ['slug' => 'show-b'], $this->moderator);
    app(MergeProjects::class)->handle($from->refresh(), Project::factory()->create(['slug' => 'show-d']), $this->moderator);

    $this->get('/events/show-a/chicago-2026')->assertRedirect($run->fresh()->publicPath());
});

test('a stale save cannot put a venue on a location that has since gone secret', function () {
    [$draft, $venue] = draftWithVenue();
    $stale = Event::with('location')->find($draft->id);
    $other = Venue::factory()->create(['name' => 'Another Hall', 'latitude' => 41.9, 'longitude' => -87.7]);
    $this->postJson("/api/hosting/event/{$draft->slug}", ['location' => [
        'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email',
    ]])->assertOk();

    $request = new Illuminate\Http\Request;
    $request->replace(['location' => ['venue_id' => $other->id]]);
    $request->setUserResolver(fn () => $draft->organizer->owner);

    expect(fn () => app(App\Actions\Events\UpdateEventAction::class)->handle($stale, ['location' => ['venue_id' => $other->id]], $request))
        ->toThrow(ValidationException::class);

    expect($draft->fresh()->location->venue_id)->toBeNull();
});

test('a prefill that loaded the draft before it went secret copies no address onto it', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $earlier = publishedListing('Earlier', 'Chicago', [], $organizer);
    Location::where('event_id', $earlier->id)->update(['street' => '1 Real Road', 'latitude' => 41.8841, 'longitude' => -87.6321]);
    $earlier->update(['location_latlon' => ['lat' => 41.8841, 'lon' => -87.6321]]);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create([]);
    $stale = Event::with('location')->find($draft->id);

    Location::where('event_id', $draft->id)->update(['hiddenLocationToggle' => true]);

    $filled = app(PrefillFromShow::class)->handle($stale, $earlier->fresh(), $organizer->owner);

    expect($filled)->not->toContain('address')
        ->and($draft->fresh()->location->street)->toBeNull()
        ->and($draft->fresh()->location_latlon)->toBeNull();
});
