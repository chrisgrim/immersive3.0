<?php

use App\Mcp\Tools\AttachEventImage;
use App\Mcp\Tools\CopyCredits;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\CreateOrganizer;
use App\Mcp\Tools\DissolveProject;
use App\Mcp\Tools\GetEvent;
use App\Mcp\Tools\LinkEventToProject;
use App\Mcp\Tools\ListAllEvents;
use App\Mcp\Tools\ListMyEvents;
use App\Mcp\Tools\MergeArtists;
use App\Mcp\Tools\MergeVenues;
use App\Mcp\Tools\RemoveEventImage;
use App\Mcp\Tools\SetCredits;
use App\Mcp\Tools\SetEventVenue;
use App\Mcp\Tools\SubmitEventForReview;
use App\Mcp\Tools\UnlinkEvent;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\UpdateOrganizer;
use App\Mcp\Tools\UpdateProject;
use App\Mcp\Tools\Whoami;
use App\Models\Artist;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

        $poster = $event->images()->create(['large_image_path' => "event-images/{$event->slug}/poster.webp", 'thumb_image_path' => "event-images/{$event->slug}/poster-thumb.webp", 'rank' => 0]);
        viaToken($stranger)->tool(RemoveEventImage::class, ['event_slug' => $event->slug, 'image_id' => $poster->id])->assertHasErrors();

        expect($event->fresh()->name)->not->toBe('Hijacked');
        expect($event->fresh()->images()->count())->toBe(1);
    }

    viaToken($stranger)->tool(SubmitEventForReview::class, ['event_slug' => $draft->slug])->assertHasErrors();
    expect($draft->fresh()->status)->toBe('0');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
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

// ----- shows, venues and credits -----

function tenantRun(Project $project, Organizer $organizer, User $owner, string $instance): Event
{
    $event = tenantEvent($organizer, $owner, 'p');
    $event->update(['project_id' => $project->id, 'instance_slug' => $instance, 'published_at' => now()]);

    return $event->fresh();
}

test('every moderator tool refuses an organizer, and a moderator whose token lacks the scope', function () {
    Mail::fake();
    $owner = tenantUser();
    $organizer = tenantOrganizer($owner);
    $project = Project::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'a-show', 'name' => 'A Show']);
    $run = tenantRun($project, $organizer, $owner, 'rome-2026');
    $other = tenantRun($project, $organizer, $owner, 'paris-2026');
    $plain = tenantEvent($organizer, $owner, 'p');
    $venue = Venue::factory()->create();
    $spare = Venue::factory()->create();
    $artist = Artist::factory()->create();
    $spareArtist = Artist::factory()->create();

    $calls = [
        [LinkEventToProject::class, ['event_slug' => $plain->slug, 'project_slug' => 'a-show']],
        [UnlinkEvent::class, ['event_slug' => $run->slug]],
        [DissolveProject::class, ['project_slug' => 'a-show']],
        [UpdateProject::class, ['project_slug' => 'a-show', 'name' => 'Hijacked']],
        [CopyCredits::class, ['from_event_slug' => $run->slug, 'to_event_slug' => $other->slug]],
        [SetEventVenue::class, ['event_slug' => $run->slug, 'venue_id' => $venue->id]],
        [MergeVenues::class, ['venue_id' => $spare->id, 'into_venue_id' => $venue->id]],
        [MergeArtists::class, ['artist_id' => $spareArtist->id, 'into_artist_id' => $artist->id]],
    ];

    // Its own organizer's owner has no moderator powers either.
    foreach ([[$owner, ['mcp:use']], [tenantUser('m'), ['mcp:use']]] as [$user, $scopes]) {
        foreach ($calls as [$tool, $arguments]) {
            viaToken($user, $scopes)->tool($tool, $arguments)->assertHasErrors()->assertSee('Moderators only');
        }
    }

    expect($project->fresh()->name)->toBe('A Show')
        ->and($run->fresh()->isLinked())->toBeTrue()
        ->and($plain->fresh()->isLinked())->toBeFalse()
        ->and($spare->fresh()->merged_into_id)->toBeNull()
        ->and($spareArtist->fresh()->merged_into_id)->toBeNull();
});

test('set-credits stays on your own side, and reads the same either way', function () {
    $owner = tenantUser();
    $organizer = tenantOrganizer($owner);
    $project = Project::factory()->create(['organizer_id' => $organizer->id, 'slug' => 'a-show']);
    $event = tenantEvent($organizer, $owner, '0');
    $stranger = tenantUser();
    tenantOrganizer($stranger);
    $artist = Artist::factory()->create();
    $row = ['credits' => [['artist_id' => $artist->id, 'department' => 'cast']]];

    $denied = 'No event or show with that slug whose credits you can edit.';
    viaToken($stranger)->tool(SetCredits::class, ['event_slug' => $event->slug] + $row)->assertHasErrors()->assertSee($denied);
    viaToken($stranger)->tool(SetCredits::class, ['event_slug' => 'no-such-'.uniqid()] + $row)->assertHasErrors()->assertSee($denied);
    viaToken($stranger)->tool(SetCredits::class, ['project_slug' => 'a-show', 'credits' => [['artist_id' => $artist->id, 'department' => 'writing']]])->assertHasErrors()->assertSee($denied);
    viaToken($stranger)->tool(SetCredits::class, ['project_slug' => 'no-such-'.uniqid(), 'credits' => [['artist_id' => $artist->id, 'department' => 'writing']]])->assertHasErrors()->assertSee($denied);

    expect(Credit::count())->toBe(0);

    viaToken($owner)->tool(SetCredits::class, ['event_slug' => $event->slug] + $row)->assertOk();
    viaToken($owner)->tool(SetCredits::class, ['project_slug' => 'a-show', 'credits' => [['artist_id' => $artist->id, 'department' => 'writing']]])->assertOk();

    expect($event->credits()->count())->toBe(1)->and($project->writers()->count())->toBe(1);
});

test('a curator or a moderator on a plain token cannot set another team\'s credits', function () {
    $owner = tenantUser();
    $event = tenantEvent(tenantOrganizer($owner), $owner, 'p');
    $row = ['event_slug' => $event->slug, 'credits' => [['artist_id' => Artist::factory()->create()->id, 'department' => 'cast']]];

    viaToken(tenantUser('c'))->tool(SetCredits::class, $row)->assertHasErrors();
    viaToken(tenantUser('m'), ['mcp:use'])->tool(SetCredits::class, $row)->assertHasErrors();

    expect(Credit::count())->toBe(0);
});

test('update-event cannot ask to join a show for someone else\'s draft', function () {
    $owner = tenantUser();
    $draft = tenantEvent(tenantOrganizer($owner), $owner, '0');
    $stranger = tenantUser();
    tenantOrganizer($stranger);

    viaToken($stranger)->tool(UpdateEvent::class, ['event_slug' => $draft->slug, 'project_id' => Project::factory()->create()->id])->assertHasErrors();

    expect(ProjectLinkRequest::count())->toBe(0);
});

test('a read-only key sees no credits or link request on a published listing', function () {
    $owner = tenantUser();
    $event = tenantEvent(tenantOrganizer($owner), $owner, 'p');
    Credit::factory()->for($event, 'creditable')->for(Artist::factory()->state(['name' => 'Jo Unseen']))->create();

    viaToken(tenantUser(), [User::READ_SCOPE])->tool(GetEvent::class, ['event_slug' => $event->slug])->assertOk()->assertDontSee('Jo Unseen');
});

test('a held draft\'s credits are out of reach for its own team', function () {
    $owner = tenantUser();
    $event = tenantEvent(tenantOrganizer($owner), $owner, '0');
    DB::table('events')->where('id', $event->id)->update(['held_for_staff' => true]);

    viaToken($owner)->tool(SetCredits::class, ['event_slug' => $event->slug, 'credits' => []])->assertHasErrors();
});
