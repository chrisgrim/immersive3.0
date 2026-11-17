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
use Illuminate\Support\Facades\DB;
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

test('a copied address brings its timezone, so the location step can be saved', function () {
    $own = publishedListing('Mine', 'Boston', ['timezone' => 'America/New_York'], $this->organizer);
    $draft = draftOf($this->organizer, ['timezone' => null]);

    app(PrefillFromShow::class)->handle($draft, $own->fresh(), $this->owner);

    expect($draft->fresh()->timezone)->toBe('America/New_York')
        ->and($draft->fresh()->location->city)->not->toBeNull();
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

    // Its team sees a preview while it waits; the public does not.
    $this->get('/venues/new-hall')->assertOk()->assertSee('Waiting for EI to approve it');
    $this->actingAs(User::factory()->create(['type' => 'u']))->get('/venues/new-hall')->assertNotFound();

    $this->actingAs(User::factory()->create(['type' => 'u']))
        ->postJson('/api/venues', ['name' => 'X', 'street' => '1', 'city' => 'C', 'latitude' => 1, 'longitude' => 1])->assertForbidden();
});

test('a pending venue is seen and picked only by its proposer\'s team, and moderators', function () {
    $this->postJson('/api/venues', ['name' => 'Back Room', 'street' => '9 Hidden Ln', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6])->assertCreated();
    $pending = Venue::where('name', 'Back Room')->first();

    // The proposer and a teammate see it.
    $this->getJson('/api/venues/search?keywords=Back%20Room')->assertJsonCount(1);
    $teammate = User::factory()->create();
    $this->organizer->users()->attach($teammate->id, ['role' => 'member']);
    $this->actingAs($teammate)->getJson('/api/venues/search?keywords=Hidden')->assertJsonCount(1);

    // Another team does not: not by name, not by its address, not by picking its id.
    $other = Organizer::factory()->create(['status' => 'p']);
    $this->actingAs($other->owner);
    $this->getJson('/api/venues/search?keywords=Back%20Room')->assertJsonCount(0);
    $this->getJson('/api/venues/match?street=9%20Hidden%20Ln&city=Chicago')->assertJsonPath('venue', null);
    $theirs = draftOf($other);
    saveLocation($theirs, ['venue_id' => $pending->id])->assertUnprocessable()->assertJsonValidationErrors('location.venue_id');
    expect($theirs->fresh()->location->street)->not->toBe('9 Hidden Ln');

    // Approved, everyone may.
    $pending->update(['status' => Venue::APPROVED]);
    $this->getJson('/api/venues/search?keywords=Back%20Room')->assertJsonCount(1);
    saveLocation($theirs, ['venue_id' => $pending->id])->assertOk();

    // A moderator always sees pending ones.
    $pending->update(['status' => Venue::PENDING]);
    $this->actingAs(User::factory()->create(['type' => 'm']))->getJson('/api/venues/search?keywords=Back%20Room')->assertJsonCount(1);
});

test('picking a venue that was merged away gives the venue it went into', function () {
    $winner = Venue::factory()->create(['name' => 'The Goodman', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $loser = Venue::factory()->create(['name' => 'Goodman Theatre', 'street' => 'Dearborn', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6, 'merged_into_id' => $winner->id]);
    $draft = draftOf($this->organizer);

    $this->actingAs(User::factory()->create(['type' => 'm']));
    app(App\Actions\Events\UpdateEventAction::class)->handle($draft, ['location' => ['venue_id' => $loser->id]], Illuminate\Http\Request::create('/', 'POST'));

    expect($draft->fresh()->location->venue_id)->toBe($winner->id)
        ->and($draft->fresh()->location->street)->toBe('N Dearborn St');
});

test('the location says which kind it is: a venue, somewhere else, or not said yet', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    $address = ['street' => '1 Park Rd', 'city' => 'Brooklyn', 'latitude' => 40.6, 'longitude' => -73.9];

    // At a venue.
    saveLocation($draft, ['venue_id' => $venue->id, 'kind' => 'venue'])->assertOk();
    expect($draft->fresh()->location->kind)->toBe('venue');

    // Somewhere else, with a name typed: only a label, never a venue.
    saveLocation($draft, [...$address, 'venue' => 'Prospect Park', 'venue_id' => null, 'kind' => 'place'])->assertOk();
    expect($draft->fresh()->location)->kind->toBe('place')->venue_id->toBeNull()->venue->toBe('Prospect Park');

    // "Venue" with no venue linked says nothing.
    saveLocation($draft, [...$address, 'venue_id' => null, 'kind' => 'venue'])->assertOk();
    expect($draft->fresh()->location->kind)->toBeNull();

    // A hidden location is always somewhere else.
    saveLocation($draft, [...$address, 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email.', 'kind' => 'venue'])->assertOk();
    expect($draft->fresh()->location->kind)->toBe('place');

    // Nothing else is allowed.
    saveLocation($draft, [...$address, 'kind' => 'forest'])->assertUnprocessable();
});

test('taken off its venue without saying "somewhere else", a listing is not said again', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id, 'kind' => 'venue'])->assertOk();

    // As an older client or the assistant would: a new address, no kind.
    saveLocation($draft, ['street' => '1 Park Rd', 'city' => 'Brooklyn', 'latitude' => 40.6, 'longitude' => -73.9, 'venue_id' => null])->assertOk();

    expect($draft->fresh()->location)->venue_id->toBeNull()->kind->toBeNull();
});

test('saying the kind alone: "venue" keeps the venue it has, "place" takes it off', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id, 'kind' => 'venue'])->assertOk();

    // As the assistant might: just the kind, the venue already linked.
    saveLocation($draft, ['kind' => 'venue'])->assertOk();
    expect($draft->fresh()->location)->venue_id->toBe($venue->id)->kind->toBe('venue');

    saveLocation($draft, ['kind' => 'place'])->assertOk();
    expect($draft->fresh()->location)->venue_id->toBeNull()->kind->toBe('place');
});

test('a draft venue is never linked to a listing, not even by staff', function () {
    $draftVenue = Venue::factory()->create(['name' => 'Half Added', 'status' => Venue::DRAFT, 'street' => null, 'city' => null, 'latitude' => null, 'longitude' => null]);
    $draft = draftOf($this->organizer);

    saveLocation($draft, ['venue_id' => $draftVenue->id, 'kind' => 'venue'])->assertUnprocessable();
    expect($draft->fresh()->location?->venue_id)->toBeNull();
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

test('moving off a venue geocodes once, with no row locked', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();
    $outside = DB::transactionLevel();
    $levelDuringTheCall = null;

    $this->mock(GeocodingService::class)->shouldReceive('search')->once()
        ->andReturnUsing(function () use (&$levelDuringTheCall) {
            $levelDuringTheCall = DB::transactionLevel();

            return [['latitude' => 42.36, 'longitude' => -71.05]];
        });

    saveLocation($draft->fresh(), ['city' => 'Boston'])->assertOk();

    expect($levelDuringTheCall)->toBe($outside)
        ->and($draft->fresh()->location->venue_id)->toBeNull();
});

test('secrecy that lands during the geocode is still decided under the lock', function () {
    $venue = Venue::factory()->create(['name' => 'The Goodman', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();

    $this->mock(GeocodingService::class)->shouldReceive('search')->once()
        ->andReturnUsing(function () use ($draft) {
            Location::where('event_id', $draft->id)->update(['hiddenLocationToggle' => true]);

            return [['latitude' => 42.3611, 'longitude' => -71.0512]];
        });

    saveLocation($draft->fresh(), ['city' => 'Boston'])->assertOk();

    expect($draft->fresh()->location->venue_id)->toBeNull()
        ->and($draft->fresh()->location->venue)->toBeNull()
        ->and($draft->fresh()->location_latlon)->toBe(['lat' => 42.365, 'lon' => -71.055]);
});

test('a secret location is not geocoded on the way to the lock', function () {
    $venue = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $venue->id])->assertOk();
    $this->mock(GeocodingService::class)->shouldNotReceive('search');

    saveLocation($draft->fresh(), ['city' => 'Boston', 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'])->assertOk();
});

test('picking a venue is not geocoded on the way to the lock', function () {
    $held = Venue::factory()->create(['city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $next = Venue::factory()->create(['city' => 'Boston', 'latitude' => 42.36, 'longitude' => -71.05]);
    $draft = draftOf($this->organizer);
    saveLocation($draft, ['venue_id' => $held->id])->assertOk();
    $this->mock(GeocodingService::class)->shouldNotReceive('search');

    saveLocation($draft->fresh(), ['venue_id' => $next->id, 'city' => 'Boston'])->assertOk();
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

test('a venue merged away while the form was open saves as the one it went into', function () {
    $winner = Venue::factory()->create(['status' => Venue::APPROVED, 'street' => 'N Dearborn St', 'city' => 'Chicago', 'latitude' => 41.88, 'longitude' => -87.63]);
    $venue = Venue::factory()->create(['merged_into_id' => $winner->id]);
    $draft = draftOf($this->organizer);

    saveLocation($draft, ['venue_id' => $venue->id])->assertSuccessful();

    expect($draft->fresh()->location->venue_id)->toBe($winner->id);
});

test('a venue merged into one that is not open to this company still cannot be picked', function () {
    $venue = Venue::factory()->create(['merged_into_id' => Venue::factory()->create(['status' => Venue::PENDING])->id]);

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
    expect(Event::EDIT_LOCKED_MESSAGE)->toContain('make a copy')->not->toContain('(2026)');
});

test('the dashboard tells a draft that is asking to join which show run it joins', function () {
    $this->owner->update(['current_team_id' => $this->organizer->id]);
    $show = Project::factory()->create(['organizer_id' => $this->organizer->id, 'name' => 'Mine']);
    $draft = draftOf($this->organizer);
    ProjectLinkRequest::create(['event_id' => $draft->id, 'project_id' => $show->id]);

    $events = $this->get('/hosting/events')->assertOk()->viewData('organizer')->events;

    expect($events->firstWhere('id', $draft->id)->joining)->toBe(['id' => $show->id, 'name' => 'Mine', 'slug' => $show->slug]);
});

test('"Add a run": one call makes the run and its request, filled with what carries over, never the name, place or dates', function () {
    $this->owner->update(['current_team_id' => $this->organizer->id]);
    $show = Project::factory()->create(['organizer_id' => $this->organizer->id, 'name' => 'Mine', 'description' => 'What the show is.']);
    $earlier = publishedListing('Mine', 'Rome', ['timezone' => 'Europe/Rome', 'description' => 'Our Rome run.'], $this->organizer);
    $earlier->update(['project_id' => $show->id]);
    $earlier->tickets()->create(['name' => 'GA', 'ticket_price' => 40, 'currency' => 'EUR']);

    $slug = $this->postJson('/hosting/event/create', ['organizer_id' => $this->organizer->id, 'project_id' => $show->id])->assertCreated()->json('event.slug');

    $draft = Event::where('slug', $slug)->first();
    expect(ProjectLinkRequest::where('event_id', $draft->id)->value('project_id'))->toBe($show->id)
        ->and($draft->name)->toBeNull()
        ->and($draft->closingDate)->toBeNull()
        ->and($draft->shows()->count())->toBe(0)
        // From this company's earlier run first (then the show page): the words and the tickets.
        ->and($draft->description)->toBe('Our Rome run.')
        ->and($draft->tickets()->pluck('name')->all())->toContain('GA')
        // Not the place: a new event has not said it is in person yet, so its address is its own to pick.
        ->and($draft->location->city)->toBeNull();
});

test('"Add a run" for a show the company may not join makes no draft at all', function () {
    $this->owner->update(['current_team_id' => $this->organizer->id]);
    $hidden = Project::factory()->create(['organizer_id' => Organizer::factory()->create()->id]);
    $before = Event::withTrashed()->count();

    $this->postJson('/hosting/event/create', ['organizer_id' => $this->organizer->id, 'project_id' => $hidden->id])->assertUnprocessable();
    $this->postJson('/hosting/event/create', ['organizer_id' => $this->organizer->id, 'project_id' => 999999])->assertUnprocessable();

    expect(Event::withTrashed()->count())->toBe($before);
});

test('the bottom bar offers the show runs this company made, keeps or has runs of, never another company\'s', function () {
    $made = Project::factory()->create(['organizer_id' => $this->organizer->id, 'name' => 'Made Here']);
    $other = Organizer::factory()->create();
    $runOfTheirs = Project::factory()->create(['organizer_id' => $other->id, 'name' => 'Presented Here']);
    publishedListing('Presented Here', 'Rome')->update(['project_id' => $runOfTheirs->id, 'organizer_id' => $this->organizer->id]);
    Project::factory()->create(['organizer_id' => $other->id, 'name' => 'Not Ours']);
    $draft = draftOf($this->organizer);

    $names = collect($this->getJson("/hosting/event/{$draft->slug}/show-runs")->assertOk()->json('choices'))->pluck('name')->all();

    expect($names)->toBe(['Made Here', 'Presented Here']);

    $stranger = App\Models\User::factory()->create(['type' => 'u']);
    $this->actingAs($stranger)->getJson("/hosting/event/{$draft->slug}/show-runs")->assertForbidden();
});

test('show suggestions are only names that really are this show', function () {
    $core = fn ($name) => App\Actions\Projects\SuggestShows::core($name);
    $same = fn ($typed, $name) => App\Actions\Projects\SuggestShows::sameShow($core($typed), $core($name));

    expect($same("Uncle Barry's Birthday Party", "Uncle Barry's Birthday Party (Edinburgh 2025)"))->toBeTrue()
        ->and($same("Uncle Barry's", "Uncle Barry's Birthday Party"))->toBeTrue()
        ->and($same('Ha Ha Ha in Chicago', 'Ha Ha Ha'))->toBeTrue()
        ->and($same("Uncle Barry's Birthday Party", 'The Birthday Party'))->toBeFalse()
        ->and($same("Uncle Barry's Birthday Party", 'Birthday Show Party (Round 2!)'))->toBeFalse()
        ->and($same("Uncle Barry's Birthday Party", 'UNKLE'))->toBeFalse();
});

test('the show run switch also lists the other company show run this draft is named after', function () {
    $theirs = publishedListing('Nona Supper', 'Rome');
    $project = app(App\Actions\Projects\LinkEventToProject::class)->startFrom($theirs, $theirs->organizer->owner);

    $mine = App\Models\Organizer::factory()->create(['status' => 'p']);
    $draft = App\Models\Event::factory()->create(['organizer_id' => $mine->id, 'status' => '1', 'name' => 'Nona Supper (Texas)']);

    $this->actingAs($mine->owner)->getJson("/hosting/event/{$draft->slug}/show-runs")
        ->assertOk()->assertJsonPath('choices.0.id', $project->id);
});
