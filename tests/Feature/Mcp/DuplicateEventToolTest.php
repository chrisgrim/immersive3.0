<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\DuplicateEvent;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * duplicate-event: one call per city for a touring show. The copy is a draft
 * with the details and pictures; as_run makes it a new run of the same show.
 */

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
});

test('by default the copy only asks to join the live event: nothing live changes', function () {
    $source = publishedListing('Magic Table', 'Hong Kong');
    $address = $source->slug;

    EiServer::actingAs($source->organizer->owner)->tool(DuplicateEvent::class, ['event_slug' => $source->slug])->assertOk();

    expect(Project::count())->toBe(0)
        ->and($source->fresh()->slug)->toBe($address)
        ->and(ProjectLinkRequest::pending()->sole()->source_event_id)->toBe($source->id);
});

test('with start_show_run the owner copies a live event as a new run, starting its show run', function () {
    $source = publishedListing('Magic Table', 'Hong Kong');
    $owner = $source->organizer->owner;

    EiServer::actingAs($owner)->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'name' => 'Magic Table (Jakarta)', 'start_show_run' => true])
        ->assertOk()->assertSee('Draft copied.')->assertSee('Magic Table (Jakarta)');

    $copy = Event::where('name', 'Magic Table (Jakarta)')->sole();
    expect($copy->status)->not->toBe('p')
        ->and($copy->organizer_id)->toBe($source->organizer_id)
        ->and(Project::sole()->id)->toBe($source->fresh()->project_id)
        ->and(ProjectLinkRequest::pending()->where('event_id', $copy->id)->value('project_id'))->toBe($source->fresh()->project_id);
});

test('as_run false makes just a copy, with no show run', function () {
    $source = publishedListing('Faulty Towers', 'Buderim');

    EiServer::actingAs($source->organizer->owner)->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'as_run' => false])->assertOk();

    expect(Project::count())->toBe(0)->and(ProjectLinkRequest::count())->toBe(0);
});

test('nobody copies another company event, and only staff copy to another organizer', function () {
    $source = publishedListing('Faulty Towers', 'Buderim');
    $stranger = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $other = Organizer::factory()->create(['status' => 'p']);

    EiServer::actingAs($stranger)->tool(DuplicateEvent::class, ['event_slug' => $source->slug])->assertHasErrors();
    EiServer::actingAs($source->organizer->owner)->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'organizer_id' => $other->id])->assertHasErrors();

    $staff = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    EiServer::actingAs($staff)->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'organizer_id' => $other->id, 'name' => 'Faulty Towers (Seven Hills)'])->assertOk();
    expect(Event::where('name', 'Faulty Towers (Seven Hills)')->value('organizer_id'))->toBe($other->id);
});

test('the AI crawler\'s copies wait for staff and never start a show run', function () {
    $crawler = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    config(['ei.crawler_user_id' => $crawler->id]);
    $source = publishedListing('Faulty Towers', 'Buderim');

    EiServer::actingAs($crawler)->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'start_show_run' => true])->assertOk();

    expect(Event::where('id', '!=', $source->id)->sole()->held_for_staff)->toBeTrue()
        ->and(Project::count())->toBe(0);
});

test('through a token: strangers are refused like an unknown slug, and moderator powers need the moderator scope', function () {
    $source = publishedListing('Faulty Towers', 'Buderim');
    $other = Organizer::factory()->create(['status' => 'p']);
    $stranger = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);

    viaToken($stranger)->tool(DuplicateEvent::class, ['event_slug' => $source->slug])->assertHasErrors()->assertSee('No event with that slug');
    viaToken($stranger)->tool(DuplicateEvent::class, ['event_slug' => 'no-such-event'])->assertHasErrors()->assertSee('No event with that slug');

    $moderator = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    viaToken($moderator)->tool(DuplicateEvent::class, ['event_slug' => $source->slug])->assertHasErrors();
    viaToken($moderator, ['mcp:use', User::MODERATE_SCOPE])->tool(DuplicateEvent::class, ['event_slug' => $source->slug, 'organizer_id' => $other->id])->assertOk();

    expect(Event::count())->toBe(2)->and(Project::count())->toBe(0);
});

test('a copy of an event already in a show run asks to join it, leaves out the old go-live date, and says what still needs setting', function () {
    $source = publishedListing('Magic Table', 'Hong Kong');
    $project = app(App\Actions\Projects\LinkEventToProject::class)->startFrom($source, $source->organizer->owner);
    $source->refresh()->forceFill(['embargo_date' => now()->addDays(3)->format('Y-m-d H:i:s')])->save();

    EiServer::actingAs($source->organizer->owner)->tool(DuplicateEvent::class, ['event_slug' => $source->slug])
        ->assertOk()->assertSee('rename it before submitting');

    $copy = Event::where('id', '!=', $source->id)->sole();
    expect(Project::count())->toBe(1)
        ->and(ProjectLinkRequest::pending()->sole()->project_id)->toBe($project->id)
        ->and($copy->embargo_date)->toBeNull()
        ->and($copy->closingDate)->toBeNull()
        ->and($copy->ticketUrl)->toBeNull();
});

test('a copy of a draft says it could not be linked yet', function () {
    $organizer = Organizer::factory()->create();
    $owner = $organizer->owner;
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0']);

    EiServer::actingAs($owner)->tool(DuplicateEvent::class, ['event_slug' => $draft->slug])
        ->assertOk()->assertSee('Not linked');
});
