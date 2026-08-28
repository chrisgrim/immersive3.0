<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListEventAttributes;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\Whoami;
use App\Models\Category;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function mcpUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function organizerFor(User $user): Organizer
{
    return Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
}

// ── whoami ─────────────────────────────────────────────────────────────

test('whoami returns the user and their organizers', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);

    $response = EiServer::actingAs($user)->tool(Whoami::class);

    $response->assertOk()
        ->assertSee($user->email)
        ->assertSee($organizer->name)
        ->assertSee('owner');
});

test('whoami hints when the user has no organizer', function () {
    $response = EiServer::actingAs(mcpUser())->tool(Whoami::class);

    $response->assertOk()->assertSee('create-organizer');
});

test('whoami query count stays flat as the number of organizers grows (EI-LARAVEL-W)', function () {
    $user = mcpUser();
    $organizers = collect(range(1, 5))->map(fn () => organizerFor($user));

    // Non-zero, non-uniform counts on two organizers to prove the batched
    // query actually attributes rows to the right organizer_id.
    Event::factory()->count(2)->create(['organizer_id' => $organizers[0]->id, 'status' => 'd']);
    Event::factory()->create(['organizer_id' => $organizers[1]->id, 'status' => 'd']);

    DB::enableQueryLog();
    $response = EiServer::actingAs($user)->tool(Whoami::class)->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Was one count query per organizer plus the teams query — 6 with 5
    // organizers. The batched version is flat at 2 (teams + one grouped
    // count) no matter how many organizers there are. Threshold sits
    // between the two, confirmed against the actual old code, not guessed.
    expect($queryCount)->toBeLessThan(5);

    $response->assertSee(['"unpublished_events":2', '"unpublished_events":1', '"unpublished_events":0']);
});

// ── list-event-attributes ──────────────────────────────────────────────

test('list-event-attributes returns categories', function () {
    Category::factory()->create(['name' => 'Immersive Theatre']);

    $response = EiServer::actingAs(mcpUser())
        ->tool(ListEventAttributes::class, ['type' => 'categories']);

    $response->assertOk()->assertSee('Immersive Theatre');
});

test('list-event-attributes rejects an unknown type', function () {
    $response = EiServer::actingAs(mcpUser())
        ->tool(ListEventAttributes::class, ['type' => 'nonsense']);

    $response->assertHasErrors();
});

// ── list-my-events ─────────────────────────────────────────────────────

test('list-my-events returns drafts for my organizer only', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);
    $mine = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0', 'name' => 'My Draft Event']);

    $stranger = mcpUser();
    $otherOrg = organizerFor($stranger);
    Event::factory()->create(['organizer_id' => $otherOrg->id, 'status' => '0', 'name' => 'Someone Elses Event']);

    $response = EiServer::actingAs($user)->tool(ListMyEvents::class);

    $response->assertOk()
        ->assertSee('My Draft Event')
        ->assertDontSee('Someone Elses Event');
});

test('list-my-events rejects filtering by an organizer the user does not belong to', function () {
    $user = mcpUser();
    organizerFor($user);
    $otherOrg = organizerFor(mcpUser());

    $response = EiServer::actingAs($user)
        ->tool(ListMyEvents::class, ['organizer_id' => $otherOrg->id]);

    $response->assertHasErrors();
});

// ── get-event ──────────────────────────────────────────────────────────

test('get-event returns state and readiness for an owned draft', function () {
    $user = mcpUser();
    $organizer = organizerFor($user);
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'status' => '0',
        'name' => 'Readiness Probe',
        'description' => null,
    ]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    $response = EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertOk()
        ->assertSee('Readiness Probe')
        ->assertSee('readiness')
        ->assertSee('missing');
});

test('get-event denies events the user cannot manage', function () {
    $stranger = mcpUser();
    $otherOrg = organizerFor(mcpUser());
    $event = Event::factory()->create(['organizer_id' => $otherOrg->id, 'status' => '0']);

    $response = EiServer::actingAs($stranger)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertHasErrors();
});

test('get-event lets a moderator view any event', function () {
    $moderator = mcpUser('m');
    $event = Event::factory()->create(['organizer_id' => organizerFor(mcpUser())->id, 'status' => '0']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    $response = EiServer::actingAs($moderator)->tool(GetEvent::class, ['event_slug' => $event->slug]);

    $response->assertOk();
});

// ── summary mode ───────────────────────────────────────────────────────
//
// show_dates is the biggest thing in this response by a wide margin: one
// recurring escape room carries 2,282 dates (49 KB of timestamps), and 371
// events hold more than 100. A tag cleanup had to delegate events to
// subagents purely to keep that array out of its context.

function eventWithShows(User $user, int $count): Event
{
    $event = Event::factory()->create(['organizer_id' => organizerFor($user)->id, 'status' => '0']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    for ($i = 0; $i < $count; $i++) {
        $event->shows()->create(['date' => now()->addDays($i)->format('Y-m-d H:i:s')]);
    }

    return $event;
}

test('get-event lists every date by default', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 3);

    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()
        ->assertSee('show_dates')
        ->assertDontSee('shows_count');
});

test('summary mode collapses the dates to a count and the two endpoints', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 5);

    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug, 'summary' => true])
        ->assertOk()
        ->assertSee(['shows_count', 'first_show_date', 'last_show_date'])
        // The array itself must be gone — leaving it alongside the summary
        // would make the flag cost context rather than save it.
        ->assertDontSee('show_dates');
});

test('summary mode reports the real endpoints, not the first and second date', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 4);

    // Sorted here rather than with orderByDesc: Show carries a DateScope
    // global scope that pins 'date asc', so an orderByDesc() on the query is
    // silently a no-op and would hand back the FIRST date twice.
    // Show::date is an uncast string, so this (like the tool's own min()/max())
    // compares lexicographically — exactly right for 'Y-m-d H:i:s'.
    $dates = $event->shows()->pluck('date')->map(fn ($d) => (string) $d)->sort()->values();
    $first = $dates->first();
    $last = $dates->last();

    expect($first)->not->toBe($last);

    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug, 'summary' => true])
        ->assertOk()
        ->assertSee(['"shows_count":4', $first, $last]);
});

test('summary mode keeps the content fields a metadata pass actually reads', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 3);
    $event->update(['tag_line' => 'A Tagline Worth Keeping', 'description' => 'A description worth keeping.']);
    $event->genres()->attach(Genre::factory()->create(['name' => 'Puzzle Horror'])->id);

    // The point of the flag is to drop the dates, not to strip the response
    // down to a stub — a tag cleanup still needs everything below.
    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug, 'summary' => true])
        ->assertOk()
        ->assertSee(['A Tagline Worth Keeping', 'A description worth keeping.', 'Puzzle Horror', 'readiness']);
});

test('the individual dates are genuinely absent, not merely unlabelled', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 400);

    // A date from the middle of the run: present in full mode, and the whole
    // point of the flag is that it is nowhere in the summary response.
    $middle = (string) $event->shows()->pluck('date')->map(fn ($d) => (string) $d)->sort()->values()->get(200);

    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()->assertSee($middle);

    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug, 'summary' => true])
        ->assertOk()->assertDontSee($middle);
});

test('an event with no dates at all still answers in summary mode', function () {
    $user = mcpUser();
    $event = eventWithShows($user, 0);

    // A draft before the schedule step is the normal state, not an edge case:
    // min/max over an empty collection must not blow up the whole response.
    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug, 'summary' => true])
        ->assertOk()
        ->assertSee('"shows_count":0');
});

test('get-event exposes hiddenLocation so a client can see what clears secret_location_explained', function () {
    $user = mcpUser();
    $event = Event::factory()->create(['organizer_id' => organizerFor($user)->id, 'status' => '0']);
    $event->location()->create(['hiddenLocationToggle' => true, 'hiddenLocation' => null]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    // The flag is unmet and the field that satisfies it must be visible in the
    // same payload — without it clients guessed at remote_description instead.
    EiServer::actingAs($user)->tool(GetEvent::class, ['event_slug' => $event->slug])
        ->assertOk()
        ->assertSee('secret_location_explained')
        ->assertSee('hiddenLocation');
});

test('whoami tells a moderator they can use any organizer id', function () {
    $moderator = mcpUser('m');
    organizerFor($moderator);

    // is_moderator alone did not tell clients that the membership check in
    // create-event-draft exempts them, so they stopped at an unclaimable org.
    EiServer::actingAs($moderator)->tool(Whoami::class)
        ->assertOk()
        ->assertSee('NOT limited to the organizers listed here');

    EiServer::actingAs(mcpUser())->tool(Whoami::class)
        ->assertOk()
        ->assertDontSee('NOT limited to the organizers listed here');
});
