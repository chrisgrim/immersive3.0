<?php

use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\RequestLink;
use App\Mail\Comments;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Messaging\Message;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeSearchEngine;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
});

/** A duplicate of $source filled in and sitting in the queue. */
function copyInQueue(Event $source, string $city = 'Boston', string $name = 'Flushment Day'): Event
{
    test()->actingAs($source->organizer->owner);
    $copy = $source->duplicate();
    $copy->update(['name' => $name, 'status' => 'r', 'hasLocation' => true]);
    Location::where('event_id', $copy->id)->update(['city' => $city]);
    App\Models\Events\Show::factory()->create(['event_id' => $copy->id, 'date' => '2027-02-01 00:00:00']);

    return $copy->fresh();
}

function approveInQueue(Event $event, ?array $link = null)
{
    return test()->actingAs(test()->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve", $link ? ['link' => $link] : []);
}

// ----- the queue's approval goes through the link -----

test('confirm approves the duplicate and makes the project, in one go', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);

    approveInQueue($copy, ['action' => 'confirm'])->assertOk();

    $project = Project::sole();

    expect($copy->fresh()->status)->toBe('p')
        ->and($project->slug)->toBe('flushment-day')
        ->and($source->fresh()->publicPath())->toBe('/events/flushment-day/chicago-2026')
        ->and($copy->fresh()->publicPath())->toBe('/events/flushment-day/boston-2027')
        ->and(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::APPROVED)
        ->and(ProjectLinkRequest::sole()->decided_by)->toBe($this->moderator->id);
});

test('confirm with a maker correction: the maker changes, the presenter stays', function () {
    $source = publishedListing('Burnout Paradise', 'New York');
    $maker = Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $copy = copyInQueue($source, 'Boston', 'Burnout Paradise');

    approveInQueue($copy, ['action' => 'confirm', 'organizer_id' => $maker->id])->assertOk();

    expect(Project::sole()->organizer_id)->toBe($maker->id)
        ->and($source->fresh()->organizer_id)->not->toBe($maker->id);
});

test('confirm can correct the maker of a show that already exists', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    app(LinkEventToProject::class)->link(publishedListing('Ha Ha Ha', 'Paris'), $source, tap($this->moderator, fn ($m) => $this->actingAs($m)));
    $copy = copyInQueue($source->fresh(), 'Boston', 'Ha Ha Ha');
    $maker = Organizer::factory()->create(['status' => 'p']);

    approveInQueue($copy, ['action' => 'confirm', 'organizer_id' => $maker->id])->assertOk();

    expect(Project::sole()->organizer_id)->toBe($maker->id)
        ->and($copy->fresh()->isLinked())->toBeTrue();
});

test('the listing alone: approved, and the request is declined', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);

    approveInQueue($copy, ['action' => 'reject', 'note' => 'Not the same show'])->assertOk();

    expect($copy->fresh()->status)->toBe('p')
        ->and($copy->fresh()->isLinked())->toBeFalse()
        ->and(Project::count())->toBe(0)
        ->and(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::REJECTED)
        ->and(ProjectLinkRequest::sole()->note)->toBe('Not the same show');
});

test('change sends it to a different show than it asked for', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $other = publishedListing('The Right Show', 'Rome');
    $copy = copyInQueue($source);

    approveInQueue($copy, ['action' => 'change', 'source_event_id' => $other->id])->assertOk();

    expect(Project::sole()->slug)->toBe('the-right-show')
        ->and($source->fresh()->isLinked())->toBeFalse()
        ->and(ProjectLinkRequest::sole()->project_id)->toBe(Project::sole()->id)
        ->and(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::APPROVED);
});

test('change with nothing chosen is refused and approves nothing', function () {
    $copy = copyInQueue(publishedListing('Flushment Day', 'Chicago'));

    approveInQueue($copy, ['action' => 'change'])->assertStatus(422);

    expect($copy->fresh()->status)->toBe('r');
});

test('a change to a listing that has since gone is refused, never read as confirm', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $gone = publishedListing('Gone', 'Rome');
    $copy = copyInQueue($source);
    $gone->delete();

    approveInQueue($copy, ['action' => 'change', 'source_event_id' => $gone->id])->assertStatus(422);

    expect($copy->fresh()->status)->toBe('r')
        ->and(Project::count())->toBe(0)
        ->and(ProjectLinkRequest::sole()->isPending())->toBeTrue();
});

test('a listing with no request can be assigned from the queue', function () {
    $listing = publishedListing('Ha Ha Ha', 'Boston', ['status' => 'r', 'published_at' => null]);
    $other = publishedListing('Ha Ha Ha', 'Rome');

    approveInQueue($listing, ['action' => 'change', 'source_event_id' => $other->id])->assertOk();

    expect($listing->fresh()->isLinked())->toBeTrue();
});

test('no link decision leaves the request pending and old clients working', function () {
    $copy = copyInQueue(publishedListing('Flushment Day', 'Chicago'));

    approveInQueue($copy)->assertOk();

    expect($copy->fresh()->status)->toBe('p')
        ->and(ProjectLinkRequest::sole()->isPending())->toBeTrue()
        ->and(Project::count())->toBe(0);
});

test('a confirm with nothing pending is a plain approval', function () {
    $listing = publishedListing('Plain', 'Boston', ['status' => 'r', 'published_at' => null]);

    approveInQueue($listing, ['action' => 'confirm'])->assertOk();

    expect($listing->fresh()->status)->toBe('p')->and(Project::count())->toBe(0);
});

test('a refused link undoes the whole approval', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);
    $slug = $copy->slug;
    $source->delete();

    approveInQueue($copy, ['action' => 'confirm'])->assertStatus(422);

    $copy->refresh();

    expect($copy->status)->toBe('r')
        ->and($copy->published_at)->toBeNull()
        ->and($copy->slug)->toBe($slug)
        ->and($copy->curatedCheck()->exists())->toBeFalse()
        ->and(ProjectLinkRequest::sole()->isPending())->toBeTrue()
        ->and(Project::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('a taken address refuses the approval with the reason', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $taken = publishedListing('Taken', 'Rome');
    $copy = copyInQueue($source);

    approveInQueue($copy, ['action' => 'confirm', 'slug' => $taken->slug])
        ->assertStatus(422)
        ->assertJsonValidationErrors('project');

    expect($copy->fresh()->status)->toBe('r');
});

test('a released address is not a valid choice', function () {
    $copy = copyInQueue(publishedListing('Flushment Day', 'Chicago'));

    approveInQueue($copy, ['action' => 'confirm', 'slug' => 'deleted--4'])->assertStatus(422);
});

test('an embargoed approval links, and the run stays out of sight until it lifts', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);
    $copy->update(['embargo_date' => now()->addMonth()->format('Y-m-d H:i:s')]);

    approveInQueue($copy->fresh(), ['action' => 'confirm'])->assertOk();

    expect($copy->fresh()->status)->toBe('e')->and($copy->fresh()->isLinked())->toBeTrue();
    $this->get($copy->fresh()->publicPath())->assertNotFound();
});

test('approval copies credits and writes the run\'s redirect, as a by-hand link does', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    Credit::factory()->for($source, 'creditable')->create(['role' => 'Lead']);
    $copy = copyInQueue($source, 'Boston', 'Flushment Day Returns');

    approveInQueue($copy, ['action' => 'confirm'])->assertOk();

    expect($copy->credits()->pluck('role')->all())->toBe(['Lead'])
        ->and(SlugRedirect::where('path', 'events/flushment-day-returns')->exists())->toBeTrue();
});

test('the moderator link tells the organizer and the keeper', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);
    Mail::fake();

    approveInQueue($copy, ['action' => 'confirm'])->assertOk();

    // The approval itself, and the link to the one company that is both.
    Mail::assertQueued(Comments::class, 2);
});

test('moderators only', function () {
    $copy = copyInQueue(publishedListing('Flushment Day', 'Chicago'));

    $this->actingAs($copy->organizer->owner)
        ->postJson("/api/admin/approve/events/{$copy->slug}/approve", ['link' => ['action' => 'confirm']])
        ->assertForbidden();

    expect($copy->fresh()->status)->toBe('r');
});

// ----- what the review screen sees -----

test('show returns the pending request, and the source it asked to join', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = copyInQueue($source);

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$copy->slug}")
        ->assertOk()
        ->assertJsonPath('link_request.source_event.id', $source->id)
        ->assertJsonPath('link_request.source_event.organizer.id', $source->organizer_id)
        ->assertJsonPath('link_request.project', null)
        ->assertJsonPath('link_request.requested_by.id', $source->organizer->user_id);
});

test('getPending returns the same, without a query per listing for the requests', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $first = copyInQueue($source);
    $second = copyInQueue($source, 'Austin', 'Flushment Day Two');
    $third = copyInQueue($source, 'Denver', 'Flushment Day Three');

    $this->actingAs($this->moderator);
    $queries = collect();
    Illuminate\Support\Facades\DB::listen(fn ($q) => $queries->push($q->sql));

    $response = $this->getJson('/api/admin/approve/events')->assertOk();

    expect(collect($response->json('data'))->pluck('link_request.source_event.id')->unique()->all())->toBe([$source->id]);
    expect($queries->filter(fn ($sql) => str_contains($sql, 'from `project_link_requests`'))->count())->toBe(1);

    config(['ei.interleave_review_queue' => false]);

    $this->getJson('/api/admin/approve/events')->assertOk()->assertJsonPath('data.0.link_request.source_event.id', $source->id);
});

test('a project with a similar name matches both ways, and shows who made it', function () {
    $project = Project::factory()->create(['name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha']);
    $longer = publishedListing('Ha Ha Ha in Chicago', 'Chicago', ['status' => 'r']);
    $shorter = publishedListing('Ha', 'Chicago', ['status' => 'r']);
    Project::factory()->create(['name' => '100%', 'slug' => 'percent']);

    $this->actingAs($this->moderator);

    $this->getJson("/api/admin/events/{$longer->slug}")
        ->assertJsonPath('name_matches.0.type', 'project')
        ->assertJsonPath('name_matches.0.id', $project->id)
        ->assertJsonPath('name_matches.0.organizer.id', $project->organizer_id);

    $this->getJson("/api/admin/events/{$shorter->slug}")->assertJsonPath('name_matches.0.id', $project->id);
});

test('standalone published listings match through the search, and runs, drafts and itself do not', function () {
    $standalone = publishedListing('Ha Ha Ha', 'Chicago');
    $run = publishedListing('Ha Ha Ha', 'Boston');
    $run->update(['project_id' => Project::factory()->create(['name' => 'Something Else'])->id, 'instance_slug' => 'boston-2026']);
    $draft = publishedListing('Ha Ha Ha', 'Paris', ['status' => 'n']);
    $asking = publishedListing('Ha Ha Ha', 'Rome', ['status' => 'r']);

    $engine = FakeSearchEngine::install([$standalone->id, $run->id, $draft->id, $asking->id]);

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$asking->slug}")->assertOk()
        ->assertJsonCount(1, 'name_matches')
        ->assertJsonPath('name_matches.0.type', 'listing')
        ->assertJsonPath('name_matches.0.id', $standalone->id)
        ->assertJsonPath('name_matches.0.organizer.id', $standalone->organizer_id);

    expect($engine->searches)->toHaveCount(1);
});

test('with the search cluster down the review screen still opens', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome', ['status' => 'r']);
    Project::factory()->create(['name' => 'Ha Ha Ha']);
    $this->mock(App\Actions\Search\SearchActions::class)
        ->shouldReceive('nameSearch')
        ->andThrow(new Elastic\Transport\Exception\NoNodeAvailableException('down'));
    Illuminate\Support\Facades\Exceptions::fake();

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$listing->slug}")
        ->assertOk()
        ->assertJsonCount(1, 'name_matches');
});

test('the old exact-name duplicate warning is untouched', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $listing = publishedListing('Ha Ha Ha', 'Rome', ['status' => 'r']);

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$listing->slug}")
        ->assertJsonPath('duplicateEvents.0.id', $first->id);
});

// ----- telling the keeper -----

test('the keeper is told when a live listing asks to join', function () {
    $maker = Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $project = Project::factory()->create(['organizer_id' => $maker->id, 'name' => 'Burnout', 'slug' => 'burnout']);
    $listing = publishedListing('Burnout', 'Rome');
    $this->actingAs($listing->organizer->owner);

    app(RequestLink::class)->handle($listing, $project, $listing->organizer->owner);

    Mail::assertQueued(Comments::class, fn ($mail) => $mail->hasTo($maker->owner->email)
        && str_contains($mail->attributes['body'], url('/hosting/projects/burnout/edit')));
    expect(Message::where('message', 'like', '%asked to join "Burnout"%')->count())->toBe(1);
});

test('a named editor is told, not the maker', function () {
    $keeper = Organizer::factory()->create(['status' => 'p']);
    $project = Project::factory()->create(['editor_organizer_id' => $keeper->id]);
    $listing = publishedListing('Burnout', 'Rome');
    $this->actingAs($listing->organizer->owner);

    app(RequestLink::class)->handle($listing, $project, $listing->organizer->owner);

    Mail::assertQueued(Comments::class, 1);
    Mail::assertQueued(Comments::class, fn ($mail) => $mail->hasTo($keeper->owner->email));
});

test('a draft\'s keeper hears when it is submitted, when the clock starts, not before', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    $project = Project::factory()->create();
    $source->update(['project_id' => $project->id, 'instance_slug' => 'chicago-2026']);
    $team = $source->organizer->owner;
    $this->actingAs($team);

    $copy = $source->fresh()->duplicate();

    Mail::assertNothingQueued();

    $this->postJson("/hosting/event/{$copy->slug}/link-request/confirm")->assertOk();
    $this->postJson("/hosting/event/{$copy->slug}/submit")->assertOk();

    Mail::assertQueued(Comments::class, fn ($mail) => $mail->hasTo($project->organizer->owner->email));
});

test('a request for a standalone listing has no company to tell yet', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $listing = publishedListing('Flushment Day', 'Rome');
    $this->actingAs($listing->organizer->owner);

    app(RequestLink::class)->handle($listing, $source, $listing->organizer->owner);

    Mail::assertNothingQueued();
});

test('the keeper is told about requests that land on its show when the project is made', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $waiting = publishedListing('Flushment Day', 'Rome');
    ProjectLinkRequest::create(['event_id' => $waiting->id, 'source_event_id' => $source->id, 'submitted_at' => now()]);
    $this->actingAs($this->moderator);

    app(LinkEventToProject::class)->link(publishedListing('Flushment Day', 'Paris'), $source, $this->moderator);

    expect(Message::where('message', 'like', '%asked to join%')->count())->toBe(1);
});

test('a held draft tells nobody', function () {
    $project = Project::factory()->create();
    $held = publishedListing('Crawled', 'Rome', ['status' => 'd']);
    ProjectLinkRequest::create(['event_id' => $held->id, 'project_id' => $project->id]);
    Event::whereKey($held->id)->update(['held_for_staff' => true]);
    $this->actingAs($this->moderator);

    $held->fresh()->update(['status' => 'r']);

    Mail::assertNothingQueued();
    expect(ProjectLinkRequest::sole()->submitted_at)->not->toBeNull();
});

test('a rollback tells nobody', function () {
    $project = Project::factory()->create();
    $listing = publishedListing('Burnout', 'Rome');
    $this->actingAs($listing->organizer->owner);

    try {
        Illuminate\Support\Facades\DB::transaction(function () use ($listing, $project) {
            app(RequestLink::class)->handle($listing, $project, $listing->organizer->owner);

            throw new RuntimeException('later failure');
        });
    } catch (RuntimeException) {
    }

    Mail::assertNothingQueued();
    expect(ProjectLinkRequest::count())->toBe(0);
});
