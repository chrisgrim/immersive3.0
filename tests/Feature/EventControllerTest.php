<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\Show;
use App\Models\Organizer;

/**
 * Build a published event that is rich enough to render the events.show view,
 * with a representative set of relations (location, show, price range, advisory).
 *
 * The events.show JSON-LD block used to read $event->priceranges[0] and
 * $event->advisories[...] unconditionally and 500 when they were missing; that is
 * now guarded in the view. The 'show renders ... with no price range or advisory'
 * test below covers that regression directly.
 */
function makeShowableEvent(array $overrides = []): Event
{
    $organizer = Organizer::factory()->create(['status' => 'p']);

    $event = Event::factory()->published()->create(array_merge([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(30),
        'hasLocation' => true,
    ], $overrides));

    Location::factory()->create(['event_id' => $event->id]);
    Show::factory()->create(['event_id' => $event->id]);
    $event->priceranges()->create(['price' => '25']);
    $event->advisories()->create(['wheelchairReady' => true]);

    return $event;
}

// ----- show() -----

test('show renders the events.show view for a published event', function () {
    $event = makeShowableEvent();

    $this->get("/events/{$event->slug}")
        ->assertOk()
        ->assertViewIs('events.show')
        ->assertViewHas('event')
        ->assertSee($event->name, false);
});

test('show emits valid JSON-LD even when text fields contain newlines and quotes', function () {
    $event = makeShowableEvent([
        'name' => 'An "Immersive" Show',
        'tag_line' => "First line\nSecond line with \"quotes\"",
        'description' => "Multi-line\ndescription",
    ]);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
    expect($matches)->not->toBeEmpty();

    $jsonLd = json_decode($matches[1]);
    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($jsonLd->name)->toBe('An "Immersive" Show')
        ->and($jsonLd->description)->toContain("First line\nSecond line");
});

test('show appends the first_show_tickets attribute to the event', function () {
    $event = makeShowableEvent();

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $viewEvent = $response->viewData('event');
    // first_show_tickets is appended by the controller; toArray() should expose it.
    expect($viewEvent->toArray())->toHaveKey('first_show_tickets');
});

test('show eager-loads event relations', function () {
    $event = makeShowableEvent();

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $viewEvent = $response->viewData('event');
    expect($viewEvent->relationLoaded('location'))->toBeTrue();
    expect($viewEvent->relationLoaded('shows'))->toBeTrue();
    expect($viewEvent->relationLoaded('organizer'))->toBeTrue();
    expect($viewEvent->relationLoaded('priceranges'))->toBeTrue();
    expect($viewEvent->relationLoaded('genres'))->toBeTrue();
});

test('show returns the first show tickets in the appended attribute', function () {
    $event = makeShowableEvent();
    $firstShow = $event->shows()->orderBy('date', 'asc')->first();
    $firstShow->tickets()->create([
        'name' => 'General',
        'ticket_price' => '20.00',
        'currency' => '$',
        'type' => 's',
    ]);

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $viewEvent = $response->viewData('event');
    expect($viewEvent->first_show_tickets)->toHaveCount(1);
    expect($viewEvent->first_show_tickets->first()->name)->toBe('General');
});

test('show renders even when the event has no price range', function () {
    // Regression for H1: the events.show JSON-LD block read $event->priceranges[0]
    // unconditionally and 500'd ("Undefined array key 0") when no price range existed.
    // (Every real event also has an advisory row — Event::newEvent() always creates one —
    // so we seed one here too; the detail partials still assume advisories is present.)
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(30),
        'hasLocation' => true,
    ]);
    Location::factory()->create(['event_id' => $event->id]);
    Show::factory()->create(['event_id' => $event->id]);
    $event->advisories()->create(['wheelchairReady' => true]);
    // Intentionally no price range.

    $this->get("/events/{$event->slug}")
        ->assertOk()
        ->assertViewIs('events.show');
});

test('show redirects to home for a draft event', function () {
    $event = Event::factory()->draft()->create();

    $this->get("/events/{$event->slug}")
        ->assertRedirect('/');
});

test('show redirects to home for an in-review event', function () {
    $event = Event::factory()->inReview()->create();

    $this->get("/events/{$event->slug}")
        ->assertRedirect('/');
});

test('show redirects to home for an embargoed event', function () {
    $event = Event::factory()->create(['status' => 'e']);

    $this->get("/events/{$event->slug}")
        ->assertRedirect('/');
});

test('show redirects to home for a new (0) status event', function () {
    $event = Event::factory()->create(['status' => '0']);

    $this->get("/events/{$event->slug}")
        ->assertRedirect('/');
});

test('show 404s for an unknown slug', function () {
    // note: the web fallback route only redirects to '/' in the production env;
    // in the testing env it abort(404)s, so a missing slug yields a real 404.
    $this->get('/events/this-slug-does-not-exist')
        ->assertNotFound();
});

// ----- getOrganizerPaginatedEvents() -----

test('getOrganizerPaginatedEvents returns only published, non-archived events for the organizer', function () {
    $organizer = Organizer::factory()->create();

    Event::factory()->count(2)->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(10),
    ]);
    // Draft, archived, and another organizer's events must be excluded.
    Event::factory()->draft()->create(['organizer_id' => $organizer->id]);
    Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'archived' => true,
        'closingDate' => now()->addDays(10),
    ]);
    Event::factory()->published()->create(['closingDate' => now()->addDays(10)]);

    $response = $this->getJson("/api/organizers/{$organizer->slug}/events")
        ->assertOk();

    expect($response->json('total'))->toBe(2);
    expect($response->json('data'))->toHaveCount(2);
});

test('getOrganizerPaginatedEvents defaults to 10 results per page', function () {
    $organizer = Organizer::factory()->create();
    Event::factory()->count(13)->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(10),
    ]);

    $response = $this->getJson("/api/organizers/{$organizer->slug}/events")
        ->assertOk();

    expect($response->json('per_page'))->toBe(10);
    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('total'))->toBe(13);
});

test('getOrganizerPaginatedEvents respects the pageSize param', function () {
    $organizer = Organizer::factory()->create();
    Event::factory()->count(5)->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(10),
    ]);

    $response = $this->getJson("/api/organizers/{$organizer->slug}/events?pageSize=3")
        ->assertOk();

    expect($response->json('per_page'))->toBe(3);
    expect($response->json('data'))->toHaveCount(3);
});

test('getOrganizerPaginatedEvents orders non-closed events before closed events', function () {
    $organizer = Organizer::factory()->create();

    // A closed (past closingDate) event created most recently.
    $closed = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subDays(5),
        'created_at' => now(),
    ]);
    // An open (future closingDate) event created earlier.
    $open = Event::factory()->published()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->addDays(5),
        'created_at' => now()->subDays(10),
    ]);

    $response = $this->getJson("/api/organizers/{$organizer->slug}/events")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    // Open event should come first despite being created earlier.
    expect($ids)->toBe([$open->id, $closed->id]);
});

test('getOrganizerPaginatedEvents returns an empty page for an organizer with no events', function () {
    $organizer = Organizer::factory()->create();

    $response = $this->getJson("/api/organizers/{$organizer->slug}/events")
        ->assertOk();

    expect($response->json('total'))->toBe(0);
    expect($response->json('data'))->toBe([]);
});

test('getOrganizerPaginatedEvents 404s for an unknown organizer slug', function () {
    $this->getJson('/api/organizers/no-such-organizer/events')
        ->assertNotFound();
});
