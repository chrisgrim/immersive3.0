<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\AttachEventImage;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\ListAllEvents;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\UpdateOrganizer;
use App\Mcp\Tools\Whoami;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;

/**
 * The boundary an MCP token can never cross: another organizer's events and
 * organizers. Every tool that reaches an event or organizer is tried from the
 * wrong side, for a draft and for a published event, and for a moderator
 * whose token does and does not carry mcp:moderate.
 */
function tenantUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function tenantOrganizer(User $owner, string $status = 'p', ?string $name = null): Organizer
{
    return Organizer::factory()->create(array_filter(['user_id' => $owner->id, 'status' => $status, 'name' => $name]));
}

function tenantEvent(Organizer $organizer, User $user, string $status = '0'): Event
{
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => $status]);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

/** Act through the api guard with a token carrying exactly these scopes. */
function viaToken(User $user, array $scopes = ['mcp:use'])
{
    Passport::actingAs($user, $scopes);

    return EiServer::actingAs($user, 'api');
}

beforeEach(fn () => Mail::fake());

test('a stranger cannot read, edit, illustrate or submit another organizer\'s events, draft or published', function () {
    $stranger = tenantUser();
    $owner = tenantUser();
    $organizer = tenantOrganizer($owner);
    $draft = tenantEvent($organizer, $owner, '0');
    $live = tenantEvent($organizer, $owner, 'p');

    foreach ([$draft, $live] as $event) {
        viaToken($stranger)->tool(GetEvent::class, ['event_slug' => $event->slug])->assertHasErrors();
        viaToken($stranger)->tool(UpdateEvent::class, ['event_slug' => $event->slug, 'name' => 'Hijacked'])->assertHasErrors();
        viaToken($stranger)->tool(AttachEventImage::class, ['event_slug' => $event->slug, 'image_url' => 'https://example.com/poster.jpg', 'rank' => 0])->assertHasErrors();

        expect($event->fresh()->name)->not->toBe('Hijacked');
        expect($event->fresh()->images()->count())->toBe(0);
    }

    viaToken($stranger)->tool(SubmitEventForReview::class, ['event_slug' => $draft->slug])->assertHasErrors();
    expect($draft->fresh()->status)->toBe('0');
    Mail::assertNothingSent();
});

test('a denial reads the same whether the slug is unknown or someone else\'s', function () {
    // Two different messages were an existence oracle for every draft on
    // the platform: list-all-events hands out published slugs, and a guess
    // at a draft slug used to be confirmed by "no permission" vs "not found".
    $stranger = tenantUser();
    $event = tenantEvent(tenantOrganizer(tenantUser()), tenantUser(), '0');

    $message = 'No event with that slug that you can view.';
    viaToken($stranger)->tool(GetEvent::class, ['event_slug' => $event->slug])->assertHasErrors()->assertSee($message);
    viaToken($stranger)->tool(GetEvent::class, ['event_slug' => 'no-such-event-'.uniqid()])->assertHasErrors()->assertSee($message);

    $organizer = tenantOrganizer(tenantUser());
    $message = 'No organizer with that slug that you can edit.';
    viaToken($stranger)->tool(UpdateOrganizer::class, ['organizer_slug' => $organizer->slug, 'description' => 'x'])->assertHasErrors()->assertSee($message);
    viaToken($stranger)->tool(UpdateOrganizer::class, ['organizer_slug' => 'no-such-org-'.uniqid(), 'description' => 'x'])->assertHasErrors()->assertSee($message);
});

test('refusing to create an event under someone else\'s organizer does not name it', function () {
    // Organizer ids are guessable, and this denial used to resolve any of
    // them — including drafts and in-review submissions — to a name.
    $stranger = tenantUser();
    tenantOrganizer($stranger); // so `host` passes and the membership check is what refuses
    $organizer = tenantOrganizer(tenantUser(), 'r', 'Very Secret Collective');

    viaToken($stranger)->tool(CreateEventDraft::class, ['organizer_id' => $organizer->id, 'name' => 'Nope'])
        ->assertHasErrors()
        ->assertDontSee('Very Secret Collective');

    viaToken($stranger)->tool(ListMyEvents::class, ['organizer_id' => $organizer->id])
        ->assertHasErrors()
        ->assertDontSee('Very Secret Collective');
});

test('duplicate-name suggestions show published organizers only, never another user\'s submission', function () {
    $other = tenantUser();
    $public = tenantOrganizer($other, 'p', 'Twin Peaks Immersive');
    $private = tenantOrganizer($other, 'r', 'Twin Peaks Immersive');

    viaToken(tenantUser())->tool(CreateOrganizer::class, ['name' => 'Twin Peaks Immersive', 'description' => 'A collective.'])
        ->assertSee($public->slug)
        ->assertDontSee($private->slug);
});

test('an organizer the user owns without a team row is theirs to list, as it is theirs to edit', function () {
    $owner = tenantUser();
    $organizer = tenantOrganizer($owner);
    $event = tenantEvent($organizer, $owner, '0');
    // Creating an organizer also seats its owner at the team table; older
    // rows do not all have that seat, which is the case this pins.
    DB::table('organizer_user')->where('organizer_id', $organizer->id)->delete();

    expect($owner->teams()->count())->toBe(0);

    viaToken($owner)->tool(ListMyEvents::class)->assertOk()->assertSee($event->slug);
    viaToken($owner)->tool(GetEvent::class, ['event_slug' => $event->slug])->assertOk();
});

test('an event whose organizer row is gone belongs to nobody but a moderator', function () {
    $owner = tenantUser();
    $event = tenantEvent(tenantOrganizer($owner), $owner, '0');
    DB::table('events')->where('id', $event->id)->update(['organizer_id' => null]);
    $event = $event->fresh();

    expect($event->organizer)->toBeNull();
    expect($owner->can('manage', $event))->toBeFalse();
    expect(tenantUser('m')->can('manage', $event))->toBeTrue();
});

test('a moderator\'s assistant is confined to their own organizers; a key with moderator powers is not', function () {
    $moderator = tenantUser('m');
    $owner = tenantUser();
    $draft = tenantEvent(tenantOrganizer($owner), $owner, '0');

    // The OAuth grant an assistant gets: mcp:use only.
    viaToken($moderator, ['mcp:use'])->tool(GetEvent::class, ['event_slug' => $draft->slug])->assertHasErrors();
    viaToken($moderator, ['mcp:use'])->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'name' => 'Moderated'])->assertHasErrors();
    viaToken($moderator, ['mcp:use'])->tool(ListAllEvents::class, ['status' => 'draft'])->assertOk()->assertDontSee($draft->slug);
    viaToken($moderator, ['mcp:use'])->tool(Whoami::class)->assertOk()->assertSee('carries the mcp:use scope only');
    expect($draft->fresh()->name)->not->toBe('Moderated');

    // A key minted with moderator powers on purpose.
    viaToken($moderator, ['mcp:use', User::MODERATE_SCOPE])->tool(GetEvent::class, ['event_slug' => $draft->slug])->assertOk();
    viaToken($moderator, ['mcp:use', User::MODERATE_SCOPE])->tool(ListAllEvents::class, ['status' => 'draft'])->assertOk()->assertSee($draft->slug);
    viaToken($moderator, ['mcp:use', User::MODERATE_SCOPE])->tool(Whoami::class)->assertOk()->assertSee('NOT limited to the organizers');
});
