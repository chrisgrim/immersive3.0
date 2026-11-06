<?php

use App\Jobs\SyncEventSearchIndex;
use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GetEvent;
use App\Models\Admin\Dock;
use App\Models\Curated\Card;
use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\Support\FakeSearchEngine;

/*
 * A secret location's exact point stays on its own row, for the team. All the
 * public ever gets is the centre of its 0.01 degree cell.
 */
const PRIVATE_LAT = 41.8841;
const PRIVATE_LON = -87.6321;
const CELL = ['lat' => 41.885, 'lon' => -87.635];

function listingWithPoint(array $event = []): Event
{
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $listing = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $organizer->user_id,
        'status' => '1',
        'hasLocation' => true,
        'attendance_type_id' => 1,
        'closingDate' => now()->addDays(30),
        ...$event,
    ]);

    Location::factory()->create(['event_id' => $listing->id, 'city' => 'Chicago', 'latitude' => null, 'longitude' => null]);

    return $listing;
}

/** Saved through the wizard, as the listing would be, then published. */
function savePoint(Event $listing, bool $secret): Event
{
    test()->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['location' => [
        'latitude' => PRIVATE_LAT,
        'longitude' => PRIVATE_LON,
        'city' => 'Chicago',
        'hiddenLocationToggle' => $secret,
        'hiddenLocation' => $secret ? 'By email' : null,
    ]])->assertOk();

    auth()->forgetGuards();

    return $listing->fresh();
}

function secretListing(): Event
{
    $listing = savePoint(listingWithPoint(), true);
    $listing->update(['status' => 'p', 'published_at' => now()]);

    return $listing->fresh();
}

function holdsNoPrivatePoint(string $body): void
{
    expect($body)->not->toContain('41.8841')
        ->and($body)->not->toContain('87.6321');
}

function chicagoSearch(): string
{
    return 'city=Chicago%2C+IL&lat=41.88&lng=-87.63&searchType=inPerson&live=false';
}

// ----- the point -----

test('a secret location gives the centre of its cell, an open one its exact point', function () {
    $location = new Location(['latitude' => PRIVATE_LAT, 'longitude' => PRIVATE_LON]);

    expect($location->publicPoint())->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);

    $location->hiddenLocationToggle = true;

    expect($location->publicPoint())->toBe(CELL);
});

test('every location in a cell gives the same point', function () {
    $point = fn (float $lat, float $lon) => (new Location(['latitude' => $lat, 'longitude' => $lon]))
        ->forceFill(['hiddenLocationToggle' => true])->publicPoint();

    expect($point(41.8801, -87.6301))->toBe(CELL)
        ->and($point(41.8899, -87.6399))->toBe(CELL);
});

test('no point for a location without coordinates', function (mixed $lat, mixed $lon) {
    expect((new Location(['latitude' => $lat, 'longitude' => $lon]))->publicPoint())->toBeNull();
})->with([[null, null], [0, 0], [41.88, null]]);

test('the point stays on the map at its very edge', function () {
    $location = (new Location(['latitude' => 90, 'longitude' => 180]))->forceFill(['hiddenLocationToggle' => true]);

    expect($location->publicPoint())->toBe(['lat' => 89.995, 'lon' => 179.995]);
});

test('the event page\'s 800 metre circle holds a cell\'s far corner anywhere on earth', function () {
    $metres = function (float $lat, float $lon, array $to) {
        $radians = fn (float $degrees) => deg2rad($degrees);
        $a = sin($radians($to['lat'] - $lat) / 2) ** 2
            + cos($radians($lat)) * cos($radians($to['lat'])) * sin($radians($to['lon'] - $lon) / 2) ** 2;

        return 2 * 6371000 * asin(sqrt($a));
    };

    foreach ([0.0049, 41.8849, -33.8649, 64.1449] as $lat) {
        $lon = 10.0001;
        $location = (new Location(['latitude' => $lat, 'longitude' => $lon]))->forceFill(['hiddenLocationToggle' => true]);

        expect($metres($lat, $lon, $location->publicPoint()))->toBeLessThan(800);
    }
});

// ----- what events.location_latlon holds -----

test('going secret through the wizard stores the public point, and the exact one stays on the location', function () {
    $listing = savePoint(listingWithPoint(), true);

    expect($listing->location_latlon)->toBe(CELL)
        ->and((float) $listing->location->latitude)->toBe(PRIVATE_LAT)
        ->and((float) $listing->location->longitude)->toBe(PRIVATE_LON);
});

test('turning secrecy on and off alone rewrites the stored point', function () {
    $listing = savePoint(listingWithPoint(), false);
    expect($listing->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);

    $this->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['location' => [
        'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email',
    ]])->assertOk();
    expect($listing->fresh()->location_latlon)->toBe(CELL);

    $this->postJson("/api/hosting/event/{$listing->slug}", ['location' => ['hiddenLocationToggle' => false]])->assertOk();
    expect($listing->fresh()->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
});

test('a location saved with no usable point takes the stored one away', function () {
    $listing = savePoint(listingWithPoint(), false);

    $this->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['location' => [
        'latitude' => 0, 'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email',
    ]])->assertOk();

    expect($listing->fresh()->location_latlon)->toBeNull();
});

test('storeEventLocation stores the public point too', function () {
    $listing = listingWithPoint();
    $listing->location->forceFill(['hiddenLocationToggle' => true])->save();

    Location::storeEventLocation(new Illuminate\Http\Request(['latitude' => PRIVATE_LAT, 'longitude' => PRIVATE_LON]), $listing);

    expect($listing->fresh()->location_latlon)->toBe(CELL);
});

// ----- the index -----

test('the index holds the public point, and the listing still counts as located', function () {
    $document = secretListing()->toSearchableArray();

    expect($document['location_latlon'])->toBe(CELL)
        ->and($document['hasLocation'])->toBeTrue();
});

test('an open listing is indexed at its exact point', function () {
    expect(savePoint(listingWithPoint(), false)->toSearchableArray()['location_latlon'])
        ->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
});

test('an online listing is indexed with no point, whatever its old location row holds', function (bool $secret) {
    $listing = savePoint(listingWithPoint(), $secret);
    Event::withoutGlobalScopes()->whereKey($listing->id)->update(['hasLocation' => false, 'attendance_type_id' => 2]);

    $document = $listing->fresh()->toSearchableArray();

    expect($document['location_latlon'])->toBeNull()
        ->and($document['hasLocation'])->toBeFalse();
})->with([[true], [false]]);

test('an in-person listing with hasLocation wrongly unset keeps its place in the index', function () {
    $listing = savePoint(listingWithPoint(), true);
    Event::withoutGlobalScopes()->whereKey($listing->id)->update(['hasLocation' => false, 'attendance_type_id' => 1]);

    $document = $listing->fresh()->toSearchableArray();

    expect($document['location_latlon'])->toBe(CELL)
        ->and($document['hasLocation'])->toBeTrue();
});

test('a secrecy change alone reaches the index', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);

    config(['scout.queue' => true]);
    Queue::fake();

    $this->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['location' => [
        'hiddenLocationToggle' => true, 'hiddenLocation' => 'By email',
    ]]);

    Queue::assertPushed(SyncEventSearchIndex::class);
});

/**
 * Keeps each document handed to the index. The engine is what a save reaches
 * inline; FakeSearchEngine answers searches with an empty _source, so nothing
 * here proves what Elasticsearch itself stores or finds.
 */
function recordingIndex(bool $down = false): object
{
    $engine = new class extends Laravel\Scout\Engines\NullEngine
    {
        public array $documents = [];

        public bool $down = false;

        public function update($models)
        {
            throw_if($this->down, new RuntimeException('the cluster is down'));

            foreach ($models as $model) {
                $this->documents[] = $model->toSearchableArray()['location_latlon'];
            }
        }
    };
    $engine->down = $down;

    $manager = resolve(Laravel\Scout\EngineManager::class);
    $manager->forgetDrivers();
    $manager->extend('null', fn () => $engine);

    config(['scout.queue' => true]);
    Queue::fake();

    return $engine;
}

function publishedOpenListing(): Event
{
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);

    return $listing->fresh();
}

function saveLocationFields(Event $listing, array $fields)
{
    return test()->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['location' => $fields]);
}

test('turning secrecy on replaces the index document before the save returns', function () {
    $listing = publishedOpenListing();
    $index = recordingIndex();

    saveLocationFields($listing, ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'])->assertOk();

    expect($index->documents)->toBe([CELL]);
    Queue::assertPushed(SyncEventSearchIndex::class);
});

test('moving a secret point to another cell replaces the index document before the save returns', function () {
    $listing = secretListing();
    $index = recordingIndex();

    saveLocationFields($listing, ['latitude' => 41.9012, 'longitude' => -87.6012])->assertOk();

    expect($index->documents)->toBe([['lat' => 41.905, 'lon' => -87.605]]);
});

test('with the cluster down the secrecy save stands, is reported, and the queued job is the retry', function () {
    $listing = publishedOpenListing();
    recordingIndex(down: true);
    Exceptions::fake();

    saveLocationFields($listing, ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'])->assertOk();

    expect($listing->fresh()->location_latlon)->toBe(CELL)
        ->and((bool) $listing->fresh()->location->hiddenLocationToggle)->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'the cluster is down');
    Queue::assertPushed(SyncEventSearchIndex::class);
});

test('other location saves leave the index to the queue', function (bool $secret, array $fields) {
    $listing = $secret ? secretListing() : publishedOpenListing();
    $index = recordingIndex();

    saveLocationFields($listing, $fields)->assertOk();

    expect($index->documents)->toBe([]);
    Queue::assertPushed(SyncEventSearchIndex::class);
})->with([
    'turning secrecy off' => [true, ['hiddenLocationToggle' => false]],
    'a secret save that moves nothing' => [true, ['hiddenLocation' => 'By text']],
    'a secret move inside its cell' => [true, ['latitude' => 41.8842, 'longitude' => -87.6322]],
    'an open move' => [false, ['latitude' => 41.9012, 'longitude' => -87.6012]],
]);

test('a save that does not touch the location leaves the index to the queue', function () {
    $listing = secretListing();
    $index = recordingIndex();

    $this->actingAs($listing->organizer->owner)->postJson("/api/hosting/event/{$listing->slug}", ['tag_line' => 'Another line'])->assertOk();

    expect($index->documents)->toBe([]);
});

test('with no queue the index is written once, by the save itself', function () {
    $listing = publishedOpenListing();
    $index = recordingIndex();
    config(['scout.queue' => false]);

    saveLocationFields($listing, ['hiddenLocationToggle' => true, 'hiddenLocation' => 'By email'])->assertOk();

    expect($index->documents)->toBe([CELL]);
});

// ----- surfaces that read location_latlon -----

test('map pins show a secret listing at its public point', function () {
    $listing = secretListing();
    FakeSearchEngine::install([$listing->id]);

    $response = $this->getJson('/api/index/search?'.chicagoSearch())->assertOk();

    expect($response->json('pins.0.location_latlon'))->toBe(CELL);
    holdsNoPrivatePoint(json_encode($response->json('pins')));
});

test('Saved Events show a secret listing at its public point, in the list and alone', function () {
    $listing = secretListing();
    $this->actingAs(User::factory()->create());
    $listing->favorite();

    foreach (['/api/hub/events', "/api/hub/events/{$listing->slug}"] as $url) {
        $response = $this->getJson($url)->assertOk();

        expect($response->getContent())->toContain('41.885');
        holdsNoPrivatePoint($response->getContent());
    }
});

test('nav search carries only the public point', function () {
    $listing = secretListing();
    FakeSearchEngine::install([$listing->id]);

    $response = $this->getJson('/api/search/nav/events?keywords=anything')->assertOk();

    expect($response->getContent())->toContain('41.885');
    holdsNoPrivatePoint($response->getContent());
});

test('a curated card on the home page carries only the public point', function () {
    $listing = secretListing();
    $community = Community::factory()->create(['status' => 'p']);
    $shelf = Shelf::factory()->create(['community_id' => $community->id]);
    $post = Post::factory()->create(['community_id' => $community->id, 'shelf_id' => $shelf->id, 'status' => 'p', 'is_hidden' => false]);
    Card::factory()->create(['post_id' => $post->id, 'event_id' => $listing->id]);
    $dock = Dock::factory()->create(['location' => 'home', 'order' => 0]);
    $dock->shelves()->attach($shelf);

    $response = $this->get('/')->assertOk();
    $docks = $response->viewData('docks')->toJson();

    expect($docks)->toContain('41.885');
    holdsNoPrivatePoint($docks);
    holdsNoPrivatePoint($response->getContent());
});

// ----- surfaces that send the location itself -----

function hasNoPointOfItsOwn(array $location): void
{
    expect($location)->not->toHaveKeys(['latitude', 'longitude']);
}

test('similar events carry only the public point', function () {
    $listing = secretListing();
    $beside = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();
    $row = collect($response->json('events'))->firstWhere('id', $listing->id);

    hasNoPointOfItsOwn($row['location']);
    expect($row['location_latlon'])->toBe(CELL);
    holdsNoPrivatePoint($response->getContent());
});

test('similar events by location carry only the public point', function () {
    $listing = secretListing();

    $response = $this->getJson('/api/events/similar-by-location?lat=41.88&lng=-87.63')->assertOk();
    $row = collect($response->json('events'))->firstWhere('id', $listing->id);

    hasNoPointOfItsOwn($row['location']);
    expect($row['location_latlon'])->toBe(CELL);
    holdsNoPrivatePoint($response->getContent());
});

test('the search API carries only the public point', function () {
    $listing = secretListing();
    FakeSearchEngine::install([$listing->id]);

    $response = $this->getJson('/api/index/search?'.chicagoSearch())->assertOk();

    hasNoPointOfItsOwn($response->json('data.0.location'));
    expect($response->json('data.0.location_latlon'))->toBe(CELL);
    holdsNoPrivatePoint($response->getContent());
});

test('the search page carries only the public point', function () {
    $listing = secretListing();
    FakeSearchEngine::install([$listing->id]);

    $response = $this->withoutVite()->get('/index/search?'.chicagoSearch())->assertOk();

    hasNoPointOfItsOwn($response->viewData('searchedEvents')['data'][0]['location']);
    holdsNoPrivatePoint($response->getContent());
});

/** The rest of what the event page needs to render. */
function readyForItsPage(Event $listing): Event
{
    Show::factory()->create(['event_id' => $listing->id]);
    $listing->priceranges()->create(['price' => '25']);
    $listing->advisories()->create(['wheelchairReady' => true]);

    return $listing->fresh();
}

test('the event page carries only the public point, for the map and the city link', function () {
    $listing = readyForItsPage(secretListing());

    $response = $this->withoutVite()->get("/events/{$listing->slug}")->assertOk();

    holdsNoPrivatePoint($response->getContent());
    expect($response->getContent())->toContain('lat=41.885&amp;lng=-87.635');
});

test('an open listing\'s city link keeps its exact point', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);

    $this->withoutVite()->get('/events/'.readyForItsPage($listing)->slug)->assertOk()->assertSee('lat=41.8841&amp;lng=-87.6321', false);
});

test('get-event keeps a secret point from those who cannot edit the event', function () {
    $listing = secretListing();
    $reader = User::factory()->create(['email_verified_at' => now()]);
    Passport::actingAs($reader, [User::READ_SCOPE]);

    EiServer::actingAs($reader, 'api')->tool(GetEvent::class, ['event_slug' => $listing->slug])
        ->assertOk()
        ->assertDontSee('41.8841')
        ->assertDontSee('87.6321');
});

test('get-event still answers for a published listing with no location row', function () {
    $listing = listingWithPoint(['status' => 'p', 'published_at' => now()]);
    $listing->location->delete();
    $reader = User::factory()->create(['email_verified_at' => now()]);
    Passport::actingAs($reader, [User::READ_SCOPE]);

    EiServer::actingAs($reader, 'api')->tool(GetEvent::class, ['event_slug' => $listing->slug])->assertOk();
});

// ----- those who edit the listing keep the exact point -----

test('get-event gives the team the exact point', function () {
    $listing = secretListing();
    $owner = $listing->organizer->owner;
    Passport::actingAs($owner, ['mcp:use']);

    EiServer::actingAs($owner, 'api')->tool(GetEvent::class, ['event_slug' => $listing->slug])
        ->assertOk()
        ->assertSee('41.8841');
});

test('the wizard gives the team the exact point', function () {
    $listing = saveEditable();

    $this->actingAs($listing->organizer->owner)->withoutVite()->get("/hosting/event/{$listing->slug}/edit")
        ->assertOk()
        ->assertSee('41.8841');
});

test('the admin review gives moderators the exact point', function () {
    $listing = secretListing();

    $this->actingAs(User::factory()->create(['type' => 'm']))->getJson("/api/admin/events/{$listing->slug}")
        ->assertOk()
        ->assertSee('41.8841');
});

function saveEditable(): Event
{
    return savePoint(listingWithPoint(), true);
}

// ----- the radius of similar events -----

function nearbyIds(float $lat): array
{
    return collect(test()->getJson("/api/events/similar-by-location?lat={$lat}&lng=-87.63&radius=1")->assertOk()->json('events'))
        ->pluck('id')->all();
}

test('a small radius that holds a private point but not the public one finds nothing', function () {
    $listing = secretListing();

    // One mile is 0.0145 degrees: this box tops out between the two points.
    expect(nearbyIds(41.8701))->not->toContain($listing->id);
});

test('a small radius that holds the public point finds the listing', function () {
    $listing = secretListing();

    // And this one starts between them.
    expect(nearbyIds(41.8990))->toContain($listing->id);
});

test('an open listing is found at its exact point, and at one stored as text', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);

    expect(nearbyIds(41.8701))->toContain($listing->id);

    $listing->update(['location_latlon' => ['lat' => '41.8841', 'lon' => '-87.6321']]);

    expect(nearbyIds(41.8701))->toContain($listing->id);
});

test('a listing with no point at all is simply not nearby', function () {
    $listing = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    expect(nearbyIds(41.8701))->not->toContain($listing->id);
});

test('the radius keeps the location columns\' eight decimals', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now(), 'location_latlon' => ['lat' => 41.88410049, 'lon' => PRIVATE_LON]]);

    // The box tops out just under the point, and over that point cut to six decimals.
    expect(nearbyIds(41.8841003 - 1 / 69))->not->toContain($listing->id);
});

// ----- what similar events cached -----

test('a cached online listing that has since gone secret in person shows only the public point', function () {
    $listing = savePoint(listingWithPoint(), false);
    Location::where('event_id', $listing->id)->update(['city' => 'Denver']);
    Event::withoutGlobalScopes()->whereKey($listing->id)->update([
        'status' => 'p', 'published_at' => now(), 'hasLocation' => false, 'attendance_type_id' => 2,
    ]);
    $beside = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk()->assertSee('41.8841');

    // As a shared store hands it back: online, with no location loaded.
    $key = "similar_events_{$beside->slug}";
    Cache::put($key, unserialize(serialize(Cache::get($key))), 60);

    Event::withoutGlobalScopes()->whereKey($listing->id)->update(['hasLocation' => true, 'attendance_type_id' => 1]);
    Location::where('event_id', $listing->id)->update(['hiddenLocationToggle' => true]);

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();
    $row = collect($response->json('events'))->firstWhere('id', $listing->id);

    expect($row['location_latlon'])->toBe(CELL)
        ->and($row)->not->toHaveKey('location');
    holdsNoPrivatePoint($response->getContent());
});

test('a cached event whose location has gone keeps no point', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);
    $beside = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    Location::where('event_id', $listing->id)->delete();

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    expect(collect($response->json('events'))->firstWhere('id', $listing->id)['location_latlon'])->toBeNull();
});

test('a cached entry\'s stored point is worked out again from the location', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);
    $beside = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk()->assertSee('41.8841');

    // A shared store hands back a copy from before the change.
    $key = "similar_events_{$beside->slug}";
    Cache::put($key, unserialize(serialize(Cache::get($key))), 60);

    // The row alone, so nothing but the cache hit can have dealt with it.
    Location::where('event_id', $listing->id)->update(['hiddenLocationToggle' => true]);

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    expect(collect($response->json('events'))->firstWhere('id', $listing->id)['location_latlon'])->toBe(CELL);
    holdsNoPrivatePoint($response->getContent());
});

test('a cached entry stays exact for a listing that is not secret', function () {
    $listing = savePoint(listingWithPoint(), false);
    $listing->update(['status' => 'p', 'published_at' => now()]);
    $beside = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    $response = $this->getJson("/api/events/{$beside->slug}/similar")->assertOk();

    expect(collect($response->json('events'))->firstWhere('id', $listing->id)['location_latlon'])->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
});

// ----- the backfill -----

/** A secret listing as it stands before the backfill: the exact point stored. */
function secretListingFromBefore(array $event = []): Event
{
    $listing = listingWithPoint(['status' => 'p', 'published_at' => now(), ...$event]);
    $listing->location->forceFill(['latitude' => PRIVATE_LAT, 'longitude' => PRIVATE_LON, 'hiddenLocationToggle' => true])->save();
    Event::withoutGlobalScopes()->whereKey($listing->id)->update([
        'location_latlon' => json_encode(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]),
        'updated_at' => now()->subYear(),
    ]);

    return $listing->fresh();
}

test('the backfill changes nothing and queues nothing unless told to apply', function () {
    $listing = secretListingFromBefore();
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points')
        ->expectsOutputToContain('Would rewrite 1 secret listing(s), fill 0 open listing(s) with no point, and reindex 1')
        ->assertSuccessful();

    expect($listing->fresh()->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
    Queue::assertNothingPushed();
});

test('the backfill rewrites the point, queues the reindex and leaves updated_at', function () {
    $listing = secretListingFromBefore();
    $before = $listing->updated_at;
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')
        ->expectsOutputToContain('Rewrote 1 secret listing(s), filled 0 open listing(s) with no point, queued 1 for reindex')
        ->assertSuccessful();

    expect($listing->fresh()->location_latlon)->toBe(CELL)
        ->and($listing->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and((float) $listing->fresh()->location->latitude)->toBe(PRIVATE_LAT);
    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('the backfill leaves open listings alone', function () {
    $open = savePoint(listingWithPoint(), false);
    $open->update(['status' => 'p', 'published_at' => now()]);
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($open->fresh()->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
    Queue::assertNothingPushed();
});

test('the backfill repairs and reindexes a secret location kept on an online listing', function () {
    $online = secretListingFromBefore(['hasLocation' => false, 'attendance_type_id' => 2]);
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($online->fresh()->location_latlon)->toBe(CELL);
    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('the backfill says it reindexed, not queued, when the index is written inline', function () {
    secretListingFromBefore();
    config(['scout.queue' => false]);

    $this->artisan('ei:secret-location-points --apply')
        ->expectsOutputToContain('Rewrote 1 secret listing(s), filled 0 open listing(s) with no point, reindexed 1.')
        ->assertSuccessful();
});

test('an edit between the backfill\'s read and write keeps its secrecy', function () {
    $listing = secretListingFromBefore();
    $edited = false;
    Location::retrieved(function (Location $location) use (&$edited) {
        if (! $edited) {
            $edited = true;
            DB::table('locations')->where('id', $location->id)->update(['hiddenLocationToggle' => false]);
        }
    });

    $this->artisan('ei:secret-location-points --apply')
        ->expectsOutputToContain('Rewrote 0 secret listing(s)')
        ->assertSuccessful();

    expect($listing->fresh()->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON]);
});

test('an edit that moves a secret point between the backfill\'s read and write wins', function () {
    $listing = secretListingFromBefore();
    $edited = false;
    Location::retrieved(function (Location $location) use (&$edited) {
        if (! $edited) {
            $edited = true;
            DB::table('locations')->where('id', $location->id)->update(['latitude' => 41.9012, 'longitude' => -87.6012]);
        }
    });

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($listing->fresh()->location_latlon)->toBe(['lat' => 41.905, 'lon' => -87.605]);
});

/** An open listing whose location has its point and whose events row never did. */
function openListingWithNoStoredPoint(array $event = []): Event
{
    $listing = savePoint(listingWithPoint(), false);
    Event::withoutGlobalScopes()->whereKey($listing->id)->update([
        'location_latlon' => null, 'status' => 'p', 'published_at' => now(), ...$event,
    ]);

    return $listing->fresh();
}

test('the backfill counts missing open points on their own in a dry run', function () {
    secretListingFromBefore();
    $open = openListingWithNoStoredPoint();

    $this->artisan('ei:secret-location-points')
        ->expectsOutputToContain('Would rewrite 1 secret listing(s), fill 1 open listing(s) with no point, and reindex 1')
        ->assertSuccessful();

    expect($open->fresh()->location_latlon)->toBeNull();
});

test('the backfill fills a missing open point, so nearby similar events find it', function () {
    $open = openListingWithNoStoredPoint();
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')
        ->expectsOutputToContain('Rewrote 0 secret listing(s), filled 1 open listing(s) with no point')
        ->assertSuccessful();

    expect($open->fresh()->location_latlon)->toBe(['lat' => PRIVATE_LAT, 'lon' => PRIVATE_LON])
        ->and(nearbyIds(41.8701))->toContain($open->id);
    Queue::assertNothingPushed();
});

test('the backfill fills no point for an online listing or a location without one', function () {
    $online = openListingWithNoStoredPoint(['hasLocation' => false]);
    $noPoint = listingWithPoint(['status' => 'p', 'published_at' => now()]);

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($online->fresh()->location_latlon)->toBeNull()
        ->and($noPoint->fresh()->location_latlon)->toBeNull();
});

test('the backfill rewrites drafts and deleted listings, and indexes neither', function () {
    $draft = secretListingFromBefore(['status' => '1']);
    $deleted = secretListingFromBefore();
    $deleted->delete();
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($draft->fresh()->location_latlon)->toBe(CELL)
        ->and(Event::withTrashed()->find($deleted->id)->location_latlon)->toBe(CELL);
    Queue::assertNothingPushed();
});

test('running the backfill again rewrites nothing and still reindexes', function () {
    secretListingFromBefore();
    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();
    config(['scout.queue' => true]);
    Queue::fake();

    $this->artisan('ei:secret-location-points --apply')
        ->expectsOutputToContain('Rewrote 0 secret listing(s), filled 0 open listing(s) with no point, queued 1 for reindex')
        ->assertSuccessful();

    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('what the backfill queues puts the public point in the index', function () {
    $listing = secretListingFromBefore();

    $this->artisan('ei:secret-location-points --apply')->assertSuccessful();

    expect($listing->fresh()->toSearchableArray()['location_latlon'])->toBe(CELL);
});
