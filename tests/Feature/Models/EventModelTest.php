<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Genre;
use App\Models\Image;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ----- newEvent() -----

test('newEvent creates a draft event with status 0 and a location and advisories', function () {
    $organizer = Organizer::factory()->create();

    $this->actingAs($this->user);
    $event = Event::newEvent($organizer->id);

    expect($event)->toBeInstanceOf(Event::class);
    expect($event->status)->toBe('0');
    expect($event->organizer_id)->toBe($organizer->id);
    expect($event->user_id)->toBe($this->user->id);
    expect($event->slug)->toStartWith('new-event-');

    // location + advisories rows created via the relations
    expect($event->location()->exists())->toBeTrue();
    expect($event->advisories()->exists())->toBeTrue();
});

test('newEvent uses the authenticated user id as the owner', function () {
    $organizer = Organizer::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $event = Event::newEvent($organizer->id);

    expect($event->user_id)->toBe($other->id);
});

// ----- finalSlug() -----

test('finalSlug returns the plain base slug when no collision exists', function () {
    $event = Event::factory()->create(['name' => 'Unique Show Name']);

    expect(Event::finalSlug($event))->toBe('unique-show-name');
});

test('finalSlug excludes the event itself so its own slug does not count as a collision', function () {
    // event already owns slug 'taken-name'; finalSlug should still return base
    $event = Event::factory()->create(['name' => 'Taken Name', 'slug' => 'taken-name']);

    expect(Event::finalSlug($event))->toBe('taken-name');
});

test('finalSlug falls back to the city when the base slug is taken', function () {
    Event::factory()->create(['name' => 'Shared Title', 'slug' => 'shared-title']);

    $event = Event::factory()->create(['name' => 'Shared Title', 'slug' => 'placeholder-slug']);
    Location::factory()->create(['event_id' => $event->id, 'city' => 'London']);
    $event->load('location');

    expect(Event::finalSlug($event))->toBe('shared-title-london');
});

test('finalSlug falls back to the organizer name when base and city are taken', function () {
    Event::factory()->create(['name' => 'Shared Title', 'slug' => 'shared-title']);
    Event::factory()->create(['name' => 'x', 'slug' => 'shared-title-paris']);

    $organizer = Organizer::factory()->create(['name' => 'Cool Org']);
    $event = Event::factory()->create([
        'name' => 'Shared Title',
        'slug' => 'placeholder-2',
        'organizer_id' => $organizer->id,
    ]);
    Location::factory()->create(['event_id' => $event->id, 'city' => 'Paris']);
    $event->load(['location', 'organizer']);

    expect(Event::finalSlug($event))->toBe('shared-title-cool-org');
});

test('finalSlug adds an incremental numeric suffix when base city and organizer all collide', function () {
    $organizer = Organizer::factory()->create(['name' => 'Repeat Org']);

    Event::factory()->create(['name' => 'Repeat Title', 'slug' => 'repeat-title']);
    Event::factory()->create(['name' => 'x', 'slug' => 'repeat-title-berlin']);
    Event::factory()->create(['name' => 'x', 'slug' => 'repeat-title-repeat-org']);

    $event = Event::factory()->create([
        'name' => 'Repeat Title',
        'slug' => 'placeholder-3',
        'organizer_id' => $organizer->id,
    ]);
    Location::factory()->create(['event_id' => $event->id, 'city' => 'Berlin']);
    $event->load(['location', 'organizer']);

    // incremental suffix starts at 2
    expect(Event::finalSlug($event))->toBe('repeat-title-2');
});

test('finalSlug counts soft-deleted events as collisions via withTrashed', function () {
    $trashed = Event::factory()->create(['name' => 'Ghost Title', 'slug' => 'ghost-title']);
    $trashed->delete();

    $organizer = Organizer::factory()->create(['name' => 'Ghost Org']);
    $event = Event::factory()->create([
        'name' => 'Ghost Title',
        'slug' => 'placeholder-ghost',
        'organizer_id' => $organizer->id,
    ]);
    $event->load('organizer');

    // base 'ghost-title' is taken by the trashed row, no location => org fallback
    expect(Event::finalSlug($event))->toBe('ghost-title-ghost-org');
});

// ----- getMostExpensive() -----

test('getMostExpensive returns the highest price across published live events', function () {
    $event = Event::factory()->published()->create([
        'closingDate' => now()->addDays(10),
    ]);
    $event->priceranges()->create(['price' => 10]);
    $event->priceranges()->create(['price' => 55]);

    $other = Event::factory()->published()->create([
        'closingDate' => now()->addDays(10),
    ]);
    $other->priceranges()->create(['price' => 30]);

    expect(Event::getMostExpensive())->toEqual(55);
});

test('getMostExpensive returns null when there are no qualifying events', function () {
    // a draft (not status p) and a closed published event => neither qualifies
    $draft = Event::factory()->draft()->create(['closingDate' => now()->addDays(5)]);
    $draft->priceranges()->create(['price' => 99]);

    expect(Event::getMostExpensive())->toBeNull();
});

test('getMostExpensive ignores published events whose closingDate is in the past', function () {
    $closed = Event::factory()->published()->create(['closingDate' => now()->subDay()]);
    $closed->priceranges()->create(['price' => 200]);

    $live = Event::factory()->published()->create(['closingDate' => now()->addDay()]);
    $live->priceranges()->create(['price' => 12]);

    expect(Event::getMostExpensive())->toEqual(12);
});

// ----- countUnpublishedEvents() -----

test('countUnpublishedEvents counts every status except published and embargoed', function () {
    $organizer = Organizer::factory()->create();

    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0']);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'r']);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'n']);
    // excluded:
    Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'e']);

    expect(Event::countUnpublishedEvents($organizer->id))->toBe(4);
});

test('countUnpublishedEvents is scoped to the given organizer', function () {
    $a = Organizer::factory()->create();
    $b = Organizer::factory()->create();

    Event::factory()->draft()->create(['organizer_id' => $a->id]);
    Event::factory()->draft()->create(['organizer_id' => $b->id]);
    Event::factory()->draft()->create(['organizer_id' => $b->id]);

    expect(Event::countUnpublishedEvents($a->id))->toBe(1);
    expect(Event::countUnpublishedEvents($b->id))->toBe(2);
});

// ----- getFirstShowTicketsAttribute -----

test('firstShowTickets returns the tickets of the earliest show when shows are not loaded', function () {
    $event = Event::factory()->create();

    $earlier = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(1)]);
    $later = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(20)]);

    $earlier->tickets()->create(['name' => 'Early Bird', 'ticket_price' => 10]);
    $later->tickets()->create(['name' => 'Late', 'ticket_price' => 99]);

    // fresh instance with no relations loaded
    $fresh = Event::findOrFail($event->id);
    expect($fresh->relationLoaded('shows'))->toBeFalse();

    $tickets = $fresh->firstShowTickets;
    expect($tickets)->toHaveCount(1);
    // The accessor now reorders ascending, so it returns the earliest show's tickets.
    expect($tickets->first()->name)->toBe('Early Bird');
});

test('firstShowTickets returns the same earliest-show tickets when shows are eager loaded', function () {
    $event = Event::factory()->create();

    $earlier = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(2)]);
    $later = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(15)]);

    $earlier->tickets()->create(['name' => 'First Show Ticket', 'ticket_price' => 5]);
    $later->tickets()->create(['name' => 'Second Show Ticket', 'ticket_price' => 50]);

    // eager-load shows (and tickets) so the accessor uses the loaded path
    $loaded = Event::with('shows.tickets')->findOrFail($event->id);
    expect($loaded->relationLoaded('shows'))->toBeTrue();

    $tickets = $loaded->firstShowTickets;
    expect($tickets)->toHaveCount(1);
    // With shows eager-loaded the accessor sorts the loaded collection ascending, so it
    // returns the earliest show's tickets even though shows() loads them DESC.
    expect($tickets->first()->name)->toBe('First Show Ticket');
});

test('firstShowTickets returns an empty collection when the event has no shows', function () {
    $event = Event::factory()->create();
    $fresh = Event::findOrFail($event->id);

    expect($fresh->firstShowTickets)->toHaveCount(0);
});

// ----- duplicate() -----

test('duplicate creates a distinct row with its own slug copy name and draft status', function () {
    Storage::fake('digitalocean');

    $event = Event::factory()->published()->create([
        'name' => 'Original Event',
        'attendance_type_id' => 1,
    ]);
    $event->advisories()->create([]);
    $event->location()->create(['city' => 'Toronto']);

    $copy = $event->duplicate();

    expect($copy->id)->not->toBe($event->id);
    expect($copy->slug)->not->toBe($event->slug);
    expect($copy->slug)->toStartWith('new-event-');
    expect($copy->name)->toBe('Original Event (Copy)');
    expect($copy->status)->toBe('0');
    expect($copy->published_at)->toBeNull();
    // hasLocation is derived from attendance_type_id === 1 (in-person)
    expect($copy->hasLocation)->toBeTrue();

    // distinct rows persisted
    expect(Event::withTrashed()->whereIn('id', [$event->id, $copy->id])->count())->toBe(2);
});

test('duplicate clones genres and creates fresh empty location and advisories rows', function () {
    Storage::fake('digitalocean');

    $event = Event::factory()->create(['attendance_type_id' => 2]);
    $event->advisories()->create(['audience' => 'adults']);
    $event->location()->create(['city' => 'Madrid']);

    $g1 = Genre::factory()->create();
    $g2 = Genre::factory()->create();
    $event->genres()->sync([$g1->id, $g2->id]);

    $copy = $event->duplicate();

    // genres carried over
    expect($copy->genres->pluck('id')->sort()->values()->all())
        ->toBe(collect([$g1->id, $g2->id])->sort()->values()->all());

    // a new location row exists for the copy, distinct from the original
    expect($copy->location)->not->toBeNull();
    expect($copy->location->id)->not->toBe($event->location->id);
    // note: duplicate() intentionally creates an EMPTY location (city not copied)
    expect($copy->location->city)->toBeNull();

    // advisories replicated (audience carried over) with its own row
    expect($copy->advisories)->not->toBeNull();
    expect($copy->advisories->id)->not->toBe($event->advisories->id);
    expect($copy->advisories->audience)->toBe('adults');

    // attendance_type_id 2 (remote) => hasLocation false
    expect($copy->hasLocation)->toBeFalse();
});

test('duplicate copies image records through ImageHandler when source files exist', function () {
    Storage::fake('digitalocean');

    $event = Event::factory()->create(['attendance_type_id' => 1, 'slug' => 'has-image-event']);
    $event->advisories()->create([]);
    $event->location()->create([]);

    $image = Image::factory()->create([
        'imageable_id' => $event->id,
        'imageable_type' => Event::class,
        'large_image_path' => 'event-images/has-image-event/orig.webp',
        'thumb_image_path' => 'event-images/has-image-event/orig-thumb.webp',
        'rank' => 0,
    ]);

    // ImageHandler only copies files that already exist on the disk
    Storage::disk('digitalocean')->put('/public/'.$image->large_image_path, 'large-webp');
    Storage::disk('digitalocean')->put('/public/event-images/has-image-event/orig.jpg', 'large-jpg');
    Storage::disk('digitalocean')->put('/public/'.$image->thumb_image_path, 'thumb-webp');
    Storage::disk('digitalocean')->put('/public/event-images/has-image-event/orig-thumb.jpg', 'thumb-jpg');

    $copy = $event->duplicate();

    // a new image record is attached to the copy
    expect($copy->images)->toHaveCount(1);
    $newImage = $copy->images->first();
    expect($newImage->id)->not->toBe($image->id);
    // new image path is namespaced under the copy's slug directory
    expect($newImage->large_image_path)->toContain('event-images/'.$copy->slug);
    // rank-0 primary image also updates the model's own image columns
    expect($copy->largeImagePath)->toBe($newImage->large_image_path);
});
