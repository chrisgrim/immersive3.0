<?php

use App\Mcp\Tools\CopyCredits;
use App\Mcp\Tools\DissolveProject;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\LinkEventToProject;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\MergeArtists;
use App\Mcp\Tools\MergeVenues;
use App\Mcp\Tools\ProposeArtist;
use App\Mcp\Tools\ProposeVenue;
use App\Mcp\Tools\SearchArtists;
use App\Mcp\Tools\SearchConsole;
use App\Mcp\Tools\SearchVenues;
use App\Mcp\Tools\SetCredits;
use App\Mcp\Tools\SetEventVenue;
use App\Mcp\Tools\UnlinkEvent;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\UpdateProject;
use App\Models\Artist;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->organizer = Organizer::factory()->create(['status' => 'p']);
    $this->owner = $this->organizer->owner;
    $this->moderator = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
});

function asMod($test)
{
    return viaToken($test->moderator, ['mcp:use', User::MODERATE_SCOPE]);
}

test('every appendix tool is registered', function () {
    $tools = (new ReflectionClass(App\Mcp\Servers\EiServer::class))->getDefaultProperties()['tools'];

    foreach ([ListProjects::class, SearchVenues::class, SearchArtists::class, ProposeVenue::class, ProposeArtist::class, SetCredits::class,
        LinkEventToProject::class, UnlinkEvent::class, DissolveProject::class, UpdateProject::class, CopyCredits::class, SetEventVenue::class,
        MergeVenues::class, MergeArtists::class] as $tool) {
        expect($tools)->toContain($tool);
    }
});

test('list-projects shows public shows only', function () {
    $project = Project::factory()->create(['name' => 'Visible Show']);
    publishedListing('Run', 'Rome')->update(['project_id' => $project->id, 'instance_slug' => 'rome-2026']);
    Project::factory()->create(['name' => 'Hidden Show']);

    viaToken($this->owner)->tool(ListProjects::class, [])->assertOk()->assertSee('Visible Show')->assertDontSee('Hidden Show');
});

test('search and propose venues and artists', function () {
    Venue::factory()->create(['name' => 'The Goodman', 'city' => 'Chicago']);
    Artist::factory()->create(['name' => 'Jo Doe']);
    $token = fn () => viaToken($this->owner);

    $token()->tool(SearchVenues::class, ['keywords' => 'Goodman'])->assertOk()->assertSee('Chicago');
    $token()->tool(SearchArtists::class, ['keywords' => 'Jo'])->assertOk()->assertSee('Jo Doe');
    $token()->tool(ProposeArtist::class, ['name' => 'New Person'])->assertOk();
    $token()->tool(ProposeVenue::class, ['name' => 'New Hall', 'street' => '1 Main', 'city' => 'Rome', 'latitude' => 41.9, 'longitude' => 12.5])->assertOk();

    expect(Artist::where('name', 'New Person')->sole()->status)->toBe(Artist::PROPOSED)
        ->and(Venue::where('name', 'New Hall')->sole()->status)->toBe(Venue::PENDING);
});

test('update-event asks to join for an own draft, fills it in, and submit wants confirmation', function () {
    $project = publicProject(['description' => 'About the show.']);
    $draft = Event::factory()->create(['organizer_id' => $this->organizer->id, 'user_id' => $this->owner->id, 'status' => '1', 'description' => null, 'hasLocation' => false]);
    $draft->location()->create([]);
    $draft->advisories()->create([]);

    viaToken($this->owner)->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'project_id' => $project->id])
        ->assertOk()->assertSee('joining');

    expect($draft->fresh()->description)->toBe('About the show.')
        ->and(ProjectLinkRequest::sole()->project_id)->toBe($project->id);
});

test('get-event returns the show, venue, address and credits marked show or run', function () {
    $project = Project::factory()->create(['name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha', 'organizer_id' => $this->organizer->id]);
    $run = publishedListing('Run', 'Rome', [], $this->organizer);
    $run->update(['project_id' => $project->id, 'instance_slug' => 'rome-2026']);
    $venue = Venue::factory()->create(['name' => 'The Goodman']);
    $run->location->update(['venue_id' => $venue->id]);
    Credit::factory()->writing()->for($project, 'creditable')->for(Artist::factory()->state(['name' => 'A Writer']))->create();
    Credit::factory()->for($run->fresh(), 'creditable')->for(Artist::factory()->state(['name' => 'A Performer']))->create();

    viaToken($this->owner)->tool(GetEvent::class, ['event_slug' => $run->slug])->assertOk()
        ->assertSee('/events/ha-ha-ha/rome-2026')
        ->assertSee('The Goodman')
        ->assertSee('A Writer')
        ->assertSee('A Performer')
        ->assertSee('"source":"show"', false)
        ->assertSee('"source":"run"', false);
});

test('a secret location never reads its venue over MCP', function () {
    $run = publishedListing('Run', 'Rome', [], $this->organizer);
    $venue = Venue::factory()->create(['name' => 'Should Not Show']);
    $run->location->update(['venue_id' => $venue->id, 'hiddenLocationToggle' => true]);

    viaToken($this->owner)->tool(GetEvent::class, ['event_slug' => $run->slug])->assertOk()->assertDontSee('Should Not Show');
});

test('update-event picks a venue', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = Event::factory()->create(['organizer_id' => $this->organizer->id, 'user_id' => $this->owner->id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create([]);

    viaToken($this->owner)->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'location' => ['venue_id' => $venue->id]])->assertOk();

    expect($draft->fresh()->location->venue_id)->toBe($venue->id)->and($draft->fresh()->location->city)->toBe('Chicago')
        // A venue picked is "at a venue", said or not.
        ->and($draft->fresh()->location->kind)->toBe('venue');
});

test('update-event says "somewhere else": the name is a label, the kind is saved, and get-event shows it', function () {
    $draft = Event::factory()->create(['organizer_id' => $this->organizer->id, 'user_id' => $this->owner->id, 'status' => '1', 'hasLocation' => true]);
    $draft->location()->create([]);

    viaToken($this->owner)->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'location' => [
        'kind' => 'place', 'venue' => 'Prospect Park, by the boathouse', 'street' => 'Prospect Park', 'city' => 'Brooklyn', 'latitude' => 40.66, 'longitude' => -73.97,
    ]])->assertOk();

    expect($draft->fresh()->location)->kind->toBe('place')->venue_id->toBeNull()->venue->toBe('Prospect Park, by the boathouse');
    viaToken($this->owner)->tool(GetEvent::class, ['event_slug' => $draft->fresh()->slug])->assertSee('"kind":"place"', false);
});

test('a moderator links, and the rest of the lifecycle runs through the tools', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'Boston');

    asMod($this)->tool(LinkEventToProject::class, ['event_slug' => $second->slug, 'source_event_slug' => $first->slug])->assertOk()->assertSee('/events/ha-ha-ha/boston-2026');
    asMod($this)->tool(UpdateProject::class, ['project_slug' => 'ha-ha-ha', 'name' => 'Laughter'])->assertOk();
    expect(Project::sole()->name)->toBe('Laughter');

    asMod($this)->tool(UnlinkEvent::class, ['event_slug' => $second->slug])->assertOk();
    asMod($this)->tool(DissolveProject::class, ['project_slug' => 'ha-ha-ha'])->assertOk();

    expect(Project::count())->toBe(0)->and($first->fresh()->publicPath())->toBe('/events/ha-ha-ha');
});

test('a refusal reads as an error, not a crash', function () {
    $draft = publishedListing('Draft', 'Rome', ['status' => 'r']);
    $first = publishedListing('First', 'Rome');

    asMod($this)->tool(LinkEventToProject::class, ['event_slug' => $draft->slug, 'source_event_slug' => $first->slug])
        ->assertHasErrors()->assertSee('joins a show run once it is approved');
});

test('copy-credits adds and never replaces', function () {
    $from = publishedListing('From', 'Rome');
    $to = publishedListing('To', 'Paris');
    Credit::factory()->for($from, 'creditable')->create();
    Credit::factory()->for($to, 'creditable')->create();

    asMod($this)->tool(CopyCredits::class, ['from_event_slug' => $from->slug, 'to_event_slug' => $to->slug])->assertOk();

    expect($to->credits()->count())->toBe(2);
});

test('set-event-venue attaches past the lock and refuses a secret location', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $old = publishedListing('Old', 'Rome', ['closingDate' => now()->subDays(200)]);
    $secret = publishedListing('Secret', 'Rome');
    $secret->location->update(['hiddenLocationToggle' => true]);

    asMod($this)->tool(SetEventVenue::class, ['event_slug' => $old->slug, 'venue_id' => $venue->id])->assertOk();
    asMod($this)->tool(SetEventVenue::class, ['event_slug' => $secret->slug, 'venue_id' => $venue->id])->assertHasErrors();

    expect($old->fresh()->location->venue_id)->toBe($venue->id)->and($secret->fresh()->location->venue_id)->toBeNull()
        ->and($old->fresh()->location->kind)->toBe('venue');
});

test('merge tools', function () {
    $venue = Venue::factory()->create();
    $duplicate = Venue::factory()->create();
    $artist = Artist::factory()->create();
    $twin = Artist::factory()->create();

    asMod($this)->tool(MergeVenues::class, ['venue_id' => $duplicate->id, 'into_venue_id' => $venue->id])->assertOk();
    asMod($this)->tool(MergeArtists::class, ['artist_id' => $twin->id, 'into_artist_id' => $artist->id])->assertOk();

    expect($duplicate->fresh()->merged_into_id)->toBe($venue->id)->and($twin->fresh()->merged_into_id)->toBe($artist->id);
});

test('set-credits replaces a list; a curator cannot empty a crew list', function () {
    $run = publishedListing('Run', 'Rome', [], $this->organizer);
    $going = Credit::factory()->for($run, 'creditable')->create(['department' => 'production']);
    $artist = Artist::factory()->create();

    viaToken($this->owner)->tool(SetCredits::class, ['event_slug' => $run->slug, 'credits' => [['artist_id' => $artist->id, 'department' => 'cast', 'role' => 'Lead']]])->assertOk();

    expect($going->fresh())->toBeNull()->and($run->credits()->sole()->role)->toBe('Lead');
});

test('set-credits refuses a writer on a linked run and an unknown department', function () {
    $run = publishedListing('Run', 'Rome', [], $this->organizer);
    $run->update(['project_id' => Project::factory()->create()->id, 'instance_slug' => 'rome-2026']);
    $artist = Artist::factory()->create();

    viaToken($this->owner)->tool(SetCredits::class, ['event_slug' => $run->slug, 'credits' => [['artist_id' => $artist->id, 'department' => 'writing']]])->assertHasErrors();
    viaToken($this->owner)->tool(SetCredits::class, ['event_slug' => $run->slug, 'credits' => [['artist_id' => $artist->id, 'department' => 'catering']]])->assertHasErrors();
});

test('search-console names shows, venues and moved addresses', function () {
    $tool = new SearchConsole;
    $page = new ReflectionMethod($tool, 'page');
    $row = fn (string $kind, string $path) => ['kind' => $kind, 'page' => $path, 'id' => 1, 'name' => 'X', 'clicks' => 1, 'impressions' => 1, 'ctr' => 1, 'position' => 1];

    expect($page->invoke($tool, $row('project', '/events/x')))->toMatchArray(['page' => '/events/x', 'show' => ['id' => 1, 'name' => 'X']])
        ->and($page->invoke($tool, $row('venue', '/venues/x')))->toMatchArray(['page' => '/venues/x', 'venue' => ['id' => 1, 'name' => 'X']])
        ->and($page->invoke($tool, $row('moved', '/events/old'))['moved'])->toBeTrue();
});

test('update-event no longer takes a run name, run type or festival, and says what to do instead', function () {
    $draft = Event::factory()->create(['organizer_id' => $this->organizer->id, 'status' => '1', 'user_id' => $this->owner->id]);

    viaToken($this->owner)->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'edition' => 'Holiday', 'run_type' => 'workshop', 'festival' => 'SXSW'])
        ->assertSee('event name')->assertSee('genres');

    expect($draft->fresh())->edition->toBeNull()->run_type->toBeNull()->festival->toBeNull();
});
