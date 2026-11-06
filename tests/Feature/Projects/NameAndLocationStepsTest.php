<?php

use App\Actions\Projects\PrefillFromShow;
use App\Actions\Projects\RequestLink;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use App\Models\Venue;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSearchEngine;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->organizer = Organizer::factory()->create(['status' => 'p']);
    $this->owner = $this->organizer->owner;
    $this->actingAs($this->owner);
});

function draftOf(Organizer $organizer, array $overrides = []): Event
{
    $draft = Event::factory()->create(array_merge(['organizer_id' => $organizer->id, 'user_id' => $organizer->user_id, 'status' => '1', 'hasLocation' => true, 'attendance_type_id' => 1, 'description' => null, 'tag_line' => null], $overrides));
    $draft->location()->create([]);
    $draft->advisories()->create([]);

    return $draft->fresh();
}

function saveLocation(Event $event, array $location)
{
    return test()->postJson("/api/hosting/event/{$event->slug}", ['location' => $location]);
}

// ----- /api/projects/suggest -----

test('suggestions hold shows, then published standalone listings, never linked runs', function () {
    $project = Project::factory()->create(['name' => 'Ha Ha Ha']);
    $standalone = publishedListing('Ha Ha Ha Live', 'Rome');
    $run = publishedListing('Ha Ha Ha Tour', 'Paris');
    $run->update(['project_id' => $project->id, 'instance_slug' => 'paris-2026']);
    FakeSearchEngine::install([$standalone->id, $run->id]);

    $this->getJson('/api/projects/suggest?keywords=Ha%20Ha%20Ha')->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.type', 'project')
        ->assertJsonPath('1.id', $standalone->id);
});

test('someone on no team cannot ask', function () {
    $this->actingAs(User::factory()->create(['type' => 'u']))->getJson('/api/projects/suggest?keywords=Ha')->assertForbidden();
});

// ----- the name checks -----

test('the runs of the show it asked to join, and its source, are not duplicates', function () {
    $project = Project::factory()->create();
    $run = publishedListing('Ha Ha Ha', 'Paris');
    $run->update(['project_id' => $project->id, 'instance_slug' => 'paris-2026']);
    $other = publishedListing('Dracula', 'Rome');
    $draft = draftOf($this->organizer, ['name' => 'Something']);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['name' => 'Ha Ha Ha'])->assertStatus(409);

    app(RequestLink::class)->handle($draft, $project, $this->owner);
    $this->postJson("/api/hosting/event/{$draft->slug}", ['name' => 'Ha Ha Ha'])->assertOk();

    // An unrelated listing's name still warns.
    $this->postJson("/api/hosting/event/{$draft->slug}", ['name' => 'Dracula'])->assertStatus(409);

    expect(Event::nameTakenOnSite('Ha Ha Ha', $draft->id, $draft->sameShowIds()))->toBeFalse()
        ->and(Event::nameTakenOnSite('Ha Ha Ha', $draft->id))->toBeTrue()
        ->and(Event::nameTakenOnSite('Dracula', $draft->id, $draft->sameShowIds()))->toBeTrue();
});

test('asking to join a standalone listing clears its name too', function () {
    $source = publishedListing('Flushment Day', 'Rome');
    $draft = draftOf($this->organizer, ['name' => 'Something']);

    app(RequestLink::class)->handle($draft, $source, $this->owner);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['name' => 'Flushment Day'])->assertOk();
});

// ----- filling the form in -----

test('asking to join fills empty fields from the organizer\'s own earlier run and the show', function () {
    $project = Project::factory()->create(['description' => 'About the show.']);
    $own = publishedListing('Ha Ha Ha', 'Boston', ['tag_line' => 'Our tag line', 'ticketUrl' => 'https://tickets.example'], $this->organizer);
    $own->update(['project_id' => $project->id, 'instance_slug' => 'boston-2026']);
    $own->tickets()->create(['name' => 'GA', 'ticket_price' => 30, 'currency' => 'USD']);
    Location::where('event_id', $own->id)->update(['street' => '1 Own St', 'latitude' => 42.1, 'longitude' => -71.1]);
    $own->advisories()->update(['audience' => 'Own audience']);
    $draft = draftOf($this->organizer, ['name' => 'Ha Ha Ha']);

    $this->postJson("/hosting/event/{$draft->slug}/link-request", ['project_id' => $project->id])
        ->assertCreated()
        ->assertJsonPath('link_request.project_id', $project->id);

    $draft->refresh();

    expect($draft->tag_line)->toBe('Our tag line')
        ->and($draft->ticketUrl)->toBe('https://tickets.example')
        ->and($draft->description)->toBe($own->description)
        ->and($draft->location->street)->toBe('1 Own St')
        ->and($draft->advisories->audience)->toBe('Own audience')
        ->and($draft->tickets()->pluck('name')->all())->toBe(['GA']);
});

test('another presenter\'s details are never copied, only the show\'s page', function () {
    $project = Project::factory()->create(['description' => 'About the show.']);
    $theirs = publishedListing('Ha Ha Ha', 'Boston', ['tag_line' => 'Their tag line', 'ticketUrl' => 'https://theirs.example']);
    $theirs->update(['project_id' => $project->id, 'instance_slug' => 'boston-2026']);
    $theirs->tickets()->create(['name' => 'GA', 'ticket_price' => 30, 'currency' => 'USD']);
    $draft = draftOf($this->organizer, ['name' => 'Ha Ha Ha']);

    $filled = app(PrefillFromShow::class)->handle($draft, $project, $this->owner);

    $draft->refresh();

    expect($filled)->toBe(['description'])
        ->and($draft->description)->toBe('About the show.')
        ->and($draft->tag_line)->toBeNull()
        ->and($draft->ticketUrl)->toBeNull()
        ->and($draft->tickets()->count())->toBe(0);
});

test('a standalone listing of someone else\'s fills nothing', function () {
    $source = publishedListing('Flushment Day', 'Boston', ['tag_line' => 'Theirs']);
    $draft = draftOf($this->organizer);

    expect(app(PrefillFromShow::class)->handle($draft, $source, $this->owner))->toBe([]);
});

test('nothing typed is overwritten', function () {
    $own = publishedListing('Mine', 'Boston', ['tag_line' => 'Old'], $this->organizer);
    $draft = draftOf($this->organizer, ['tag_line' => 'Typed by me', 'description' => 'Mine too']);

    app(PrefillFromShow::class)->handle($draft, $own, $this->owner);

    expect($draft->fresh()->tag_line)->toBe('Typed by me')->and($draft->fresh()->description)->toBe('Mine too');
});

test('a secret address stays with its own run', function () {
    $own = publishedListing('Mine', 'Boston', [], $this->organizer);
    Location::where('event_id', $own->id)->update(['hiddenLocationToggle' => true, 'street' => '1 Hidden Way']);
    $draft = draftOf($this->organizer);

    app(PrefillFromShow::class)->handle($draft, $own->fresh(), $this->owner);

    expect($draft->fresh()->location->street)->toBeNull();
});

test('a live listing asking to join changes nothing about itself', function () {
    $own = publishedListing('Mine', 'Boston', ['tag_line' => 'Source'], $this->organizer);
    $live = publishedListing('Live', 'Rome', ['tag_line' => null], $this->organizer);

    expect(app(PrefillFromShow::class)->handle($live, $own, $this->owner))->toBe([]);
});

// ----- confirming before submit -----

test('a run joining a show cannot be submitted until its details are confirmed', function () {
    $project = publicProject();
    $draft = draftOf($this->organizer);
    app(RequestLink::class)->handle($draft, $project, $this->owner);

    $this->postJson("/hosting/event/{$draft->slug}/submit")->assertStatus(422)->assertJsonPath('message', Event::CONFIRM_DETAILS_MESSAGE);
    expect($draft->fresh()->status)->toBe('1');

    $this->postJson("/hosting/event/{$draft->slug}/link-request/confirm")->assertOk();
    $this->postJson("/hosting/event/{$draft->slug}/submit")->assertOk();

    expect($draft->fresh()->status)->toBe('r');
});

test('a duplicate must be confirmed too; removing the request lifts it', function () {
    $source = publishedListing('Mine', 'Boston', [], $this->organizer);
    $copy = $source->duplicate();

    $this->postJson("/hosting/event/{$copy->slug}/submit")->assertStatus(422);
    $this->deleteJson("/hosting/event/{$copy->slug}/link-request")->assertOk();
    $this->postJson("/hosting/event/{$copy->slug}/submit")->assertOk();
});

test('a listing with no request submits as ever', function () {
    $draft = draftOf($this->organizer);

    $this->postJson("/hosting/event/{$draft->slug}/submit")->assertOk();
});

test('the edit page carries the request for the Name and Review steps', function () {
    $draft = draftOf($this->organizer);
    app(RequestLink::class)->handle($draft, publicProject(['name' => 'Ha Ha Ha']), $this->owner);

    $this->get("/hosting/event/{$draft->slug}/edit")->assertOk()->assertSee('Ha Ha Ha', false);
});

test('a run saves its edition, type and festival from the Name step', function () {
    $draft = draftOf($this->organizer);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['edition' => 'Holiday', 'run_type' => 'workshop', 'festival' => 'SXSW'])->assertOk();

    expect($draft->fresh()->only(['edition', 'run_type', 'festival']))->toBe(['edition' => 'Holiday', 'run_type' => 'workshop', 'festival' => 'SXSW']);

    $this->postJson("/api/hosting/event/{$draft->slug}", ['run_type' => 'not_a_type'])->assertStatus(422);
});

// ----- venues -----

test('venue search finds a name or an address, and each suggestion carries its address', function () {
    Venue::factory()->create(['name' => 'The Goodman', 'home' => '170', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'region' => 'IL']);
    Venue::factory()->create(['name' => 'Gone', 'merged_into_id' => Venue::first()->id]);

    $this->getJson('/api/venues/search?keywords=Goodman')->assertOk()->assertJsonCount(1)->assertJsonPath('0.address', '170 N Dearborn St, Chicago, IL');
    $this->getJson('/api/venues/search?keywords=Dearborn')->assertJsonCount(1);
    $this->getJson('/api/venues/search?keywords=Gone')->assertJsonCount(0);
    $this->getJson('/api/venues/search?keywords=g')->assertJsonCount(0);
});

test('an address EI already knows matches its venue the other way round', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'home' => '170', 'street' => 'N Dearborn St', 'city' => 'Chicago']);

    $this->getJson('/api/venues/match?home=170&street=N%20Dearborn%20St&city=Chicago')->assertJsonPath('venue.id', $venue->id);
    $this->getJson('/api/venues/match?home=171&street=N%20Dearborn%20St&city=Chicago')->assertJsonPath('venue', null);
    $this->getJson('/api/venues/match?street=Nowhere&city=Chicago')->assertJsonPath('venue', null);
});

test('a team member proposes a venue, which waits for a moderator', function () {
    $this->postJson('/api/venues', ['name' => 'New Hall', 'street' => '1 Main St', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6])
        ->assertCreated()->assertJsonPath('venue.status', Venue::PENDING);

    $this->get('/venues/new-hall')->assertNotFound();

    $this->actingAs(User::factory()->create(['type' => 'u']))
        ->postJson('/api/venues', ['name' => 'X', 'street' => '1', 'city' => 'C', 'latitude' => 1, 'longitude' => 1])->assertForbidden();
});

test('picking a venue copies its address and point onto the listing', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);

    saveLocation($draft, ['venue_id' => $venue->id, 'city' => 'Stale', 'street' => 'Stale'])->assertOk();

    $draft->refresh();

    expect($draft->location->venue_id)->toBe($venue->id)
        ->and($draft->location->city)->toBe('Chicago')
        ->and($draft->location->venue)->toBe('The Goodman')
        ->and((float) $draft->location->latitude)->toBe(41.88)
        ->and($draft->location_latlon['lon'])->toBe(-87.63);
});

test('an address arriving without the venue takes it off', function () {
    $venue = Venue::factory()->create(['latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    saveLocation($draft->fresh(), ['city' => 'Boston', 'street' => '1 New St', 'latitude' => 42.3, 'longitude' => -71.0])->assertOk();

    expect($draft->fresh()->location->venue_id)->toBeNull()
        ->and($draft->fresh()->location_latlon['lat'])->toBe(42.3);
});

test('a partial address with no coordinates is geocoded, so the old point never stays', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'street' => 'Old St', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    $this->mock(GeocodingService::class)->shouldReceive('search')->once()->andReturn([['latitude' => 42.36, 'longitude' => -71.05]]);

    saveLocation($draft->fresh(), ['city' => 'Boston'])->assertOk();

    expect($draft->fresh()->location_latlon['lat'])->toBe(42.36)
        ->and((float) $draft->fresh()->location->longitude)->toBe(-71.05);
});

test('an address nobody can place is refused, and the former venue stays', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    $this->mock(GeocodingService::class)->shouldReceive('search')->andReturn([]);

    saveLocation($draft->fresh(), ['city' => 'Nowhere'])->assertStatus(422);

    expect($draft->fresh()->location->venue_id)->toBe($venue->id)
        ->and($draft->fresh()->location->city)->toBe('Chicago');
});

test('geocoding that throws is a refusal too, not a 500', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    $this->mock(GeocodingService::class)->shouldReceive('search')->andThrow(new RuntimeException('down'));

    saveLocation($draft->fresh(), ['city' => 'Boston'])->assertStatus(422);
});

test('a listing with no venue is not geocoded: it behaves as today', function () {
    $draft = draftOf($this->organizer);
    $this->mock(GeocodingService::class)->shouldNotReceive('search');

    saveLocation($draft, ['city' => 'Boston'])->assertOk();
});

test('a secret location refuses a venue, and going secret takes it off', function () {
    $venue = Venue::factory()->create(['latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);

    saveLocation($draft, ['venue_id' => $venue->id, 'hiddenLocationToggle' => true])->assertStatus(422);

    saveLocation($draft->fresh(), ['venue_id' => $venue->id])->assertOk();
    saveLocation($draft->fresh(), ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'])->assertOk();

    expect($draft->fresh()->location->venue_id)->toBeNull();

    saveLocation($draft->fresh(), ['venue_id' => $venue->id])->assertStatus(422);
});

test('going online takes the venue off', function () {
    $venue = Venue::factory()->create(['latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    $this->postJson("/api/hosting/event/{$draft->slug}", ['attendance_type_id' => 2])->assertOk();

    expect($draft->fresh()->location->venue_id)->toBeNull();
});

test('a merged venue cannot be picked', function () {
    $venue = Venue::factory()->create(['merged_into_id' => Venue::factory()->create()->id]);

    saveLocation(draftOf($this->organizer), ['venue_id' => $venue->id])->assertStatus(422);
});

// ----- the dashboard -----

test('the dashboard carries the shows this company keeps, with what is waiting', function () {
    $this->owner->update(['current_team_id' => $this->organizer->id]);
    $kept = Project::factory()->create(['organizer_id' => $this->organizer->id, 'name' => 'Mine']);
    $handedOn = Project::factory()->create(['organizer_id' => $this->organizer->id, 'name' => 'Handed On', 'editor_organizer_id' => Organizer::factory()->create()->id]);
    ProjectLinkRequest::create(['event_id' => publishedListing('A', 'Rome')->id, 'project_id' => $kept->id, 'submitted_at' => now()]);
    ProjectLinkRequest::create(['event_id' => publishedListing('B', 'Rome', ['status' => '1'])->id, 'project_id' => $kept->id]);

    $organizer = $this->get('/hosting/events')->assertOk()->viewData('organizer');

    expect($organizer->kept_projects->pluck('name')->all())->toBe(['Mine'])
        ->and($organizer->kept_projects->first()['pending_requests'])->toBe(1);
});

test('the lock wording drops the year advice and says the copy joins the show', function () {
    expect(Event::EDIT_LOCKED_MESSAGE)->toContain('asks to join the same show')->not->toContain('(2026)');
});
