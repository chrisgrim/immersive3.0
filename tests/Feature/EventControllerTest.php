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
    (new MobilityAdvisory)->forceFill([
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

// ----- the Venue line (events/show/about.blade.php) -----

test('show names the place when an in-person event has no venue name, not "Remote Event"', function () {
    // The venue name is optional; ~1,500 published in-person events go
    // without one, and the fallback used to be the remote-event wording.
    $event = makeShowableEvent();
    $event->location->update(['venue' => '', 'city' => 'Brooklyn', 'region' => 'NY', 'country' => 'US']);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('Brooklyn, NY')
        ->not->toContain('Remote Event');
});

test('show still prints the venue name when there is one', function () {
    $event = makeShowableEvent();
    $event->location->update(['venue' => 'The Bushwick Starr', 'city' => 'Brooklyn', 'region' => 'NY', 'country' => 'US']);

    $this->get("/events/{$event->slug}")->assertOk()->assertSee('The Bushwick Starr');
});

test('a place label is city and state at home, city and country abroad', function () {
    expect((new Location(['city' => 'brooklyn', 'region' => 'NY', 'country' => 'US']))->placeLabel())->toBe('Brooklyn, NY')
        ->and((new Location(['city' => 'London', 'country' => 'GB', 'country_long' => 'United Kingdom']))->placeLabel())->toBe('London, United Kingdom')
        ->and((new Location(['city' => 'Paris', 'country' => 'France']))->placeLabel())->toBe('Paris, France')
        ->and((new Location(['city' => 'Reykjavik']))->placeLabel())->toBe('Reykjavik');
});

// ----- page payload: one embed, upcoming shows only, run summary -----
//
// A long-running event (one has 3,542 show rows) made its page 2.5 MB on
// desktop and 4.1 MB on mobile: every row, with timestamps, inlined as an
// attribute on each of 7 (desktop) / 5 (mobile) Vue islands. The page now
// serializes the event once into window.Laravel.page with only the upcoming
// shows (id, event_id, date) and a summary of the whole run.

test('show embeds the event JSON exactly once and every island binds pageData.event', function () {
    $event = makeShowableEvent();

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    expect(substr_count($html, 'window.Laravel.page = { event:'))->toBe(1);
    // The serialized model appears once (Js::from hex-escapes the quotes around keys).
    expect(substr_count($html, 'first_show_tickets'))->toBe(1);

    // Order matters: the layout assigns a fresh `window.Laravel = {…}`; the
    // payload must come AFTER it (or it is wiped) and outside the Vue root
    // (#app is the <body>), before the module script that mounts the app.
    $layout = strpos($html, 'window.Laravel = {');
    $page = strpos($html, 'window.Laravel.page = { event:');
    $body = strpos($html, '<body');
    expect($layout)->not->toBeFalse()->and($page)->not->toBeFalse();
    expect($layout)->toBeLessThan($page);
    expect($page)->toBeLessThan($body);
    expect(strpos($html, 'resources/js/app.js'))->not->toBeFalse();
    expect($html)->toContain(':event="pageData.event"');
    expect($html)->not->toContain('{!! $eventJson !!}');
});

test('show loads only upcoming shows, newest first, with only id, event_id and date', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    foreach ([-30, -10, -3] as $days) {
        Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays($days)]);
    }
    $soon = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(2)]);
    $later = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(9)]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->shows->pluck('id')->all())->toBe([$later->id, $soon->id]);
    expect(array_keys($viewEvent->shows->first()->getAttributes()))->toBe(['id', 'event_id', 'date']);
});

test('past show dates are embedded as bare strings, newest first, so the calendars can highlight history', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    $old = now()->subDays(40)->startOfMinute();
    $recent = now()->subDays(5)->startOfMinute();
    Show::factory()->create(['event_id' => $event->id, 'date' => $old]);
    Show::factory()->create(['event_id' => $event->id, 'date' => $recent]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(3)]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->past_show_dates)->toBe([$recent->format('Y-m-d H:i:s'), $old->format('Y-m-d H:i:s')]);
    expect($viewEvent->shows)->toHaveCount(1);
});

test('past show dates are capped, dropping the oldest first', function () {
    config(['ei.event_page_max_past_dates' => 2]);
    $event = makeShowableEvent();
    $event->shows()->delete();
    foreach ([30, 20, 10] as $days) {
        Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays($days)->startOfMinute()]);
    }

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->past_show_dates)->toHaveCount(2);
    expect(substr($viewEvent->past_show_dates[0], 0, 10))->toBe(now()->subDays(10)->toDateString());
    expect(substr($viewEvent->past_show_dates[1], 0, 10))->toBe(now()->subDays(20)->toDateString());
});

test('show_summary describes the whole run even though only upcoming shows are embedded', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    $first = now()->subDays(40)->startOfMinute();
    $last = now()->addDays(12)->startOfMinute();
    Show::factory()->create(['event_id' => $event->id, 'date' => $first]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(20)]);
    Show::factory()->create(['event_id' => $event->id, 'date' => $last]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->show_summary['total'])->toBe(3);
    expect($viewEvent->show_summary['first_date'])->toBe($first->format('Y-m-d H:i:s'));
    expect($viewEvent->show_summary['last_date'])->toBe($last->format('Y-m-d H:i:s'));
    expect($viewEvent->shows)->toHaveCount(1);
});

test('an ended run still embeds its most recent shows so the page can describe it', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    foreach (range(1, 12) as $i) {
        Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(30 + $i)]);
    }

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->shows)->toHaveCount(10);
    expect($viewEvent->show_summary['total'])->toBe(12);
    expect($viewEvent->show_summary['upcoming_total'])->toBe(0);
    // Exactly the newest ten (days 31..40 back), newest first, like the live relation.
    expect($viewEvent->shows->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all())
        ->toBe(collect(range(1, 10))->map(fn ($i) => now()->subDays(30 + $i)->toDateString())->all());
});

test('first_show_tickets are the earliest UPCOMING show tickets, not an old past show', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    $past = Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(20)]);
    $past->tickets()->create(['name' => 'Early bird', 'ticket_price' => '10.00', 'currency' => 'USD', 'type' => 's']);
    $next = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(3)]);
    $next->tickets()->create(['name' => 'General', 'ticket_price' => '20.00', 'currency' => 'USD', 'type' => 's']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(10)]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->first_show_tickets->pluck('name')->all())->toBe(['General']);
});

test('the about block and the CTA still render for an ended run (summary-driven, not shows-driven)', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(40)]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(5)]);
    Image::factory()->create(['imageable_id' => $event->id, 'imageable_type' => Event::class]);

    $response = $this->get("/events/{$event->slug}")->assertOk();

    $response->assertSee('Start date')->assertSee('End date');
    $response->assertSee($event->localDate(now()->subDays(40), 'F jS, Y'), false);
    // The single-image layout's Blade CTA is gated on the run having shows at all.
    $response->assertSee('Get Tickets');
});

test('the embedded show list is capped but the summary count is not', function () {
    config(['ei.event_page_max_shows' => 3]);
    $event = makeShowableEvent();
    $event->shows()->delete();
    foreach (range(1, 5) as $i) {
        Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays($i)]);
    }

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->shows)->toHaveCount(3);
    expect($viewEvent->show_summary['upcoming_total'])->toBe(5);
    expect($viewEvent->show_summary['total'])->toBe(5);
    // The soonest three, so the calendar's next dates are never the ones dropped.
    expect($viewEvent->shows->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->sort()->values()->all())
        ->toBe(collect([1, 2, 3])->map(fn ($i) => now()->addDays($i)->toDateString())->all());
});

test('curtain times are judged from the whole run, not just the embedded upcoming rows', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    // Only a PAST row carries a real time; the upcoming rows are date-only.
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subDays(10)->setTime(19, 30)]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(3)->setTime(0, 0, 0)]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->show_summary['curtain_times'])->toBeTrue();
    expect($viewEvent->timed_shows_count)->toBe(1);
    // PHP-side: Event::usesCurtainTimes() reads the aggregate, not the truncated relation.
    expect($viewEvent->usesCurtainTimes())->toBeTrue();
    expect(\App\Models\Events\Show::usesCurtainTimes($viewEvent->shows))->toBeFalse();
});

test('cutoff, date-only schedule: 11:30pm in Los Angeles (already tomorrow in UTC) still keeps today\'s show', function () {
    $tz = 'America/Los_Angeles';
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-14 23:30:00', $tz));
    $event = makeShowableEvent(['timezone' => $tz]);
    $event->shows()->delete();
    $today = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-14 00:00:00']);
    $yesterday = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-13 00:00:00']);
    $next = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-20 00:00:00']);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->shows->pluck('id')->all())->toBe([$next->id, $today->id]);
    expect($viewEvent->show_summary['upcoming_total'])->toBe(2);
    expect($viewEvent->show_summary['curtain_times'])->toBeFalse();
});

test('cutoff, curtain-time schedule: 12:30am in Tokyo keeps tonight\'s 8am show and drops last night\'s', function () {
    $tz = 'Asia/Tokyo';
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-14 00:30:00', $tz));
    $event = makeShowableEvent(['timezone' => $tz]);
    $event->shows()->delete();
    $thisMorning = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-13 23:00:00']); // 08:00 JST today
    $lastNight = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-13 14:00:00']);   // 23:00 JST yesterday
    $tonight = Show::factory()->create(['event_id' => $event->id, 'date' => '2026-09-14 11:00:00']);     // 20:00 JST today

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    expect($viewEvent->shows->pluck('id')->all())->toBe([$tonight->id, $thisMorning->id]);
    expect($viewEvent->shows->pluck('id')->all())->not->toContain($lastNight->id);
    expect($viewEvent->show_summary['upcoming_total'])->toBe(2);
    expect($viewEvent->show_summary['curtain_times'])->toBeTrue();
});

test('the earliest upcoming show arrives with its tickets loaded, so first_show_tickets costs no extra query per access', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    $next = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(3)]);
    $next->tickets()->create(['name' => 'General', 'ticket_price' => '20.00', 'currency' => 'USD', 'type' => 's']);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(10)]);

    $viewEvent = $this->get("/events/{$event->slug}")->assertOk()->viewData('event');

    $loadedNext = $viewEvent->shows->firstWhere('id', $next->id);
    expect($loadedNext->relationLoaded('tickets'))->toBeTrue();
    expect($viewEvent->first_show_tickets->pluck('name')->all())->toBe(['General']);
});

test('the JSON-LD startDate is the NEXT upcoming show for a live run, and the first date once it has ended', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subYears(3)->startOfMinute()]);
    $next = now()->addDays(2)->startOfMinute();
    Show::factory()->create(['event_id' => $event->id, 'date' => $next]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(20)]);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();
    expect($html)->toContain('"startDate": "'.\Carbon\Carbon::parse($next->format('Y-m-d H:i:s'))->toIso8601String().'"');

    $event->shows()->where('date', '>', now())->delete();
    $first = $event->shows()->reorder('date', 'asc')->value('date');
    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();
    expect($html)->toContain('"startDate": "'.\Carbon\Carbon::parse((string) $first)->toIso8601String().'"');
});

test('the JSON-LD startDate for an all-upcoming run is its first show, not its latest', function () {
    $event = makeShowableEvent();
    $event->shows()->delete();
    $first = now()->addDays(2)->startOfMinute();
    Show::factory()->create(['event_id' => $event->id, 'date' => $first]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDays(20)]);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('"startDate": "'.\Carbon\Carbon::parse($first->format('Y-m-d H:i:s'))->toIso8601String().'"');
});

test('a description containing a closing script tag cannot break out of the page payload', function () {
    $event = makeShowableEvent(['description' => 'Bad </script><script>alert(1)</script> \'quote\' "dq" <!--']);

    $html = $this->get("/events/{$event->slug}")->assertOk()->getContent();

    // The raw sequence never appears inside the payload script: Js::from
    // hex-escapes <, >, quotes and slashes, so the browser sees the script
    // end where we wrote it and JSON.parse restores the text on the client.
    $payload = substr($html, strpos($html, 'window.Laravel.page = { event:'));
    $payload = substr($payload, 0, strpos($payload, '</script>'));
    expect($payload)->not->toContain('<');
    expect($payload)->not->toContain('"');
    expect($payload)->toContain('u003C');
    expect($payload)->toContain('alert(1)');
});
