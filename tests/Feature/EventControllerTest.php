<?php

use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\Show;
use App\Models\Image;
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
        'currency' => 'USD',
        'type' => 's',
    ]);

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $viewEvent = $response->viewData('event');
    expect($viewEvent->first_show_tickets)->toHaveCount(1);
    expect($viewEvent->first_show_tickets->first()->name)->toBe('General');
});

test('show formats a zero-decimal currency price without cents', function () {
    // Regression: the CTA price was unconditionally number_format(...,2),
    // so a KRW/JPY/CNY event (none of which have a minor unit) rendered
    // "₩25.00" instead of the correct "₩25". The CTA's displayed price
    // comes from priceranges (makeShowableEvent() seeds one at 25), while
    // the currency symbol comes from the first show's ticket — two
    // separate sources this template already combines, unrelated to this
    // fix.
    //
    // Exactly one image is required: this Blade CTA only renders in the
    // events.show "single image" layout branch (totalMediaCount === 1) —
    // 0 or 2+ images render pricing via the show-purchase.vue Vue
    // component instead, which a plain HTTP test can't observe since it
    // never executes client-side JS.
    $event = makeShowableEvent();
    Image::factory()->create(['imageable_id' => $event->id, 'imageable_type' => Event::class]);
    $firstShow = $event->shows()->orderBy('date', 'asc')->first();
    $firstShow->tickets()->create([
        'name' => 'General',
        'ticket_price' => '25.00',
        'currency' => 'KRW',
        'type' => 's',
    ]);

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $response->assertSeeText('₩25', escape: false);
    $response->assertDontSeeText('₩25.00', escape: false);
});

test('show still shows two decimal places for a currency with a minor unit', function () {
    $event = makeShowableEvent();
    Image::factory()->create(['imageable_id' => $event->id, 'imageable_type' => Event::class]);
    $firstShow = $event->shows()->orderBy('date', 'asc')->first();
    $firstShow->tickets()->create([
        'name' => 'General',
        'ticket_price' => '25.00',
        'currency' => 'USD',
        'type' => 's',
    ]);

    $this->get("/events/{$event->slug}")
        ->assertOk()
        ->assertSeeText('$25.00', escape: false);
});

test('show does not duplicate the wheelchair-accessible line when advisory id 22 is also attached', function () {
    // Regression: MobilityAdvisory id 22, "Event is wheelchair accessible.",
    // duplicates the hardcoded wheelchairReady line whenever an organizer
    // also selects it as an advisory — both used to render, saying the same
    // thing twice.
    $event = makeShowableEvent();
    // updateOrCreate()/create() silently drop 'id' — it's not in the
    // model's $fillable — so this forces the specific id the Blade fix
    // filters on, rather than whatever id autoincrement would assign.
    (new MobilityAdvisory())->forceFill([
        'id' => 22,
        'name' => 'Event is wheelchair accessible.',
        'user_id' => $event->user_id,
        'slug' => 'event-is-wheelchair-accessible',
    ])->save();
    $event->mobilityAdvisories()->attach(22);

    $response = $this->get("/events/{$event->slug}")->assertOk();
    $content = $response->getContent();

    // Scoped to just the visible "Mobility Advisories" list — the raw
    // $event JSON serialized elsewhere on the page (for vue-show-purchase,
    // vue-show-map, etc.) also carries the full, unfiltered
    // mobilityAdvisories collection, so a whole-page string search would
    // find "Event is wheelchair accessible." there regardless of whether
    // this fix works.
    $start = strpos($content, 'Mobility Advisories');
    $section = substr($content, $start, strpos($content, 'Tags</h3>', $start) - $start);

    // The hardcoded line (advisories.wheelchairReady) — distinct from the
    // advisory row's own rendering (plain text, trailing period, no nested
    // empty <span>) by its literal "Event is <span></span> wheelchair
    // accessible" markup.
    expect($section)->toContain('Event is <span></span> wheelchair accessible');
    // The advisory row's distinct text (with its trailing period) must not
    // appear at all — that's the second, now-filtered occurrence.
    expect($section)->not->toContain('Event is wheelchair accessible.');
});

test('show renders a fallback message when no interaction advisories are set', function () {
    // Regression: zero contact levels rendered a bare "Interaction
    // Advisories" header with nothing underneath — no fallback, unlike
    // every other advisory section on the page.
    $event = makeShowableEvent();

    $this->get("/events/{$event->slug}")
        ->assertOk()
        ->assertSeeText('No interaction advisories listed', escape: false);
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

test('show renders the events.show-deleted view for a soft-deleted event, not a 404', function () {
    // Regression: implicit route-model binding's default query excludes
    // soft-deleted rows, so a link to an event that's since been deleted
    // (e.g. an old notification — see SavedEventNewDatesNotification::
    // toDatabase(), which stores the slug at notify time) 404'd outright
    // with no explanation. events.show-deleted already existed fully built
    // for exactly this, just was never wired to a route.
    $event = makeShowableEvent();
    $event->delete();

    $response = $this->get("/events/{$event->slug}")
        ->assertOk()
        ->assertViewIs('events.show-deleted');

    expect($response->viewData('event')->id)->toBe($event->id);
});

test('show 404s for a soft-deleted event that was never published, instead of rendering show-deleted', function () {
    // Regression: HostEventController::destroy() lets an organizer delete an
    // event in ANY status, not just published ones — a deleted draft/rejected
    // event never had a public page, so the "this event was removed" page
    // (with its name/image) must not render for it either. published_at is
    // only ever set on approval/embargo-publish and never cleared, so it's
    // the signal that distinguishes this from the case above.
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
        'published_at' => null,
    ]);
    $event->delete();

    $this->get("/events/{$event->slug}")->assertNotFound();
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
