<?php

use App\Actions\Projects\LinkEventToProject;
use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
    $this->actingAs($this->moderator);
});

/** A show of two runs, Chicago first. */
function twoRunShow(string $name = 'Ha Ha Ha', string $second = 'New York'): Project
{
    $first = publishedListing($name, 'Chicago');
    app(LinkEventToProject::class)->link(publishedListing($name, $second), $first, test()->moderator);

    return Project::where('name', $name)->sole();
}

test('every admin route refuses a non-moderator', function (string $method, string $uri) {
    $project = Project::factory()->create(['slug' => 'a-show']);
    Venue::factory()->create(['name' => 'A Venue']);
    Artist::factory()->create(['name' => 'An Artist']);
    publishedListing('A Listing', 'Rome');
    ProjectLinkRequest::create(['event_id' => Event::first()->id, 'project_id' => $project->id]);

    foreach ([User::factory()->create(['type' => 'u']), User::factory()->create(['type' => 'c'])] as $user) {
        $this->actingAs($user)->json($method, '/api/admin/'.str_replace('{request}', (string) ProjectLinkRequest::first()->id, $uri))->assertForbidden();
    }
})->with([
    ['GET', 'projects'],
    ['POST', 'events/a-listing/project'],
    ['PATCH', 'projects/a-show'],
    ['POST', 'projects/a-show/merge'],
    ['POST', 'projects/a-show/dissolve'],
    ['GET', 'link-requests'],
    ['POST', 'link-requests/{request}/approve'],
    ['POST', 'link-requests/{request}/reject'],
    ['GET', 'venues'],
    ['PATCH', 'venues/a-venue'],
    ['POST', 'venues/a-venue/approve'],
    ['POST', 'venues/a-venue/merge'],
    ['GET', 'artists'],
    ['POST', 'artists'],
    ['GET', 'artists/an-artist'],
    ['PATCH', 'artists/an-artist'],
    ['DELETE', 'artists/an-artist'],
    ['POST', 'artists/an-artist/approve'],
    ['POST', 'artists/an-artist/merge'],
    ['POST', 'events/a-listing/credits/copy'],
    ['POST', 'organizers/bare'],
]);

// ----- assign -----

test('a moderator assigns a listing to a standalone listing, which makes the project', function () {
    $first = publishedListing('Adelaide Show', 'New York');
    $listing = publishedListing('Adelaide Show', 'Adelaide');
    $maker = Organizer::factory()->create(['status' => 'p']);

    $this->postJson("/api/admin/events/{$listing->slug}/project", ['source_event_id' => $first->id, 'organizer_id' => $maker->id])
        ->assertOk()
        ->assertJsonPath('event.public_url', '/events/adelaide-show/adelaide-2026');

    expect(Project::sole()->organizer_id)->toBe($maker->id);
});

test('then links the rest to the project, and old listings past the lock too', function () {
    $project = twoRunShow();
    $old = publishedListing('Ha Ha Ha', 'Rome', ['closingDate' => now()->subDays(200)]);

    $this->postJson("/api/admin/events/{$old->slug}/project", ['project_id' => $project->id])->assertOk();

    expect($old->fresh()->project_id)->toBe($project->id);
});

test('assigning a draft is refused, and so is asking for nothing', function () {
    $draft = publishedListing('Draft', 'Rome', ['status' => 'r']);
    $project = Project::factory()->create();

    $this->postJson("/api/admin/events/{$draft->slug}/project", ['project_id' => $project->id])->assertStatus(422);
    $this->postJson("/api/admin/events/{$draft->slug}/project", [])->assertStatus(422);
});

test('assigning settles the listing\'s own pending request', function () {
    $project = twoRunShow();
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $project->id]);

    $this->postJson("/api/admin/events/{$listing->slug}/project", ['project_id' => $project->id])->assertOk();

    expect($request->fresh()->isPending())->toBeFalse();
});

// ----- PATCH project -----

test('PATCH changes maker, keeper and address, and logs before and after', function () {
    $project = twoRunShow();
    $maker = Organizer::factory()->create();
    $keeper = Organizer::factory()->create();

    $this->patchJson('/api/admin/projects/ha-ha-ha', [
        'organizer_id' => $maker->id,
        'editor_organizer_id' => $keeper->id,
        'slug' => 'laughter',
    ])->assertOk()->assertJsonPath('project.slug', 'laughter');

    $project->refresh();

    expect($project->organizer_id)->toBe($maker->id)
        ->and($project->keeper()->id)->toBe($keeper->id);

    $log = AuditLog::where('action', 'project.updated')->sole();
    expect($log->details['before']['slug'])->toBe('ha-ha-ha')
        ->and($log->details['after']['slug'])->toBe('laughter');

    $this->get('/events/ha-ha-ha/chicago-2026')->assertRedirect('/events/laughter/chicago-2026');
});

test('PATCH can hand the page back to the maker', function () {
    $keeper = Organizer::factory()->create();
    $project = Project::factory()->create(['editor_organizer_id' => $keeper->id, 'slug' => 'a-show']);

    $this->patchJson('/api/admin/projects/a-show', ['editor_organizer_id' => null])->assertOk();

    expect($project->fresh()->keeper()->id)->toBe($project->organizer_id);
});

test('PATCH renames a show and leaves its address', function () {
    $project = twoRunShow();

    $this->patchJson('/api/admin/projects/ha-ha-ha', ['name' => 'Ha Ha Ha!'])->assertOk()->assertJsonPath('project.name', 'Ha Ha Ha!');

    expect($project->fresh()->slug)->toBe('ha-ha-ha');
    $this->patchJson('/api/admin/projects/ha-ha-ha', ['name' => '...'])->assertStatus(422);
});

test('PATCH refuses a taken, reserved or released address', function () {
    Project::factory()->create(['slug' => 'a-show']);
    $listing = publishedListing('Taken', 'Rome');
    SlugRedirect::remember('events/reserved', $listing);

    foreach (['taken', 'reserved', 'deleted--1', 'Not A Slug'] as $slug) {
        $this->patchJson('/api/admin/projects/a-show', ['slug' => $slug])->assertStatus(422);
    }

    expect(Project::sole()->slug)->toBe('a-show');
});

// ----- merge projects -----

test('merging moves every run, and every old address leads on', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    expect(Project::sole()->id)->toBe($winner->id)
        ->and($winner->runs()->count())->toBe(4);

    $loser = Project::withTrashed()->find($loser->id);
    expect($loser->slug)->toBe('deleted--'.$loser->id);

    $this->get('/events/hahaha')->assertRedirect('/events/ha-ha-ha')->assertStatus(301);
    // Chicago 2026 was taken under the winner, so that run got a number.
    $this->get('/events/hahaha/chicago-2026')->assertRedirect('/events/ha-ha-ha/chicago-2026-2')->assertStatus(301);
    $this->get('/events/hahaha/boston-2026')->assertRedirect('/events/ha-ha-ha/boston-2026')->assertStatus(301);
    $this->get('/events/ha-ha-ha/chicago-2026-2')->assertOk();
    $this->get('/events/ha-ha-ha/boston-2026')->assertOk();
});

test('the loser\'s former addresses go to the winner too, nested ones to the run', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');
    $loser->update(['slug' => 'hahaha-renamed']);

    $this->postJson('/api/admin/projects/hahaha-renamed/merge', ['into_project_id' => $winner->id])->assertOk();

    $this->get('/events/hahaha')->assertRedirect('/events/ha-ha-ha');
    $this->get('/events/hahaha/chicago-2026')->assertRedirect('/events/ha-ha-ha/chicago-2026-2');
});

test('the log says what was linked to each, so a merge can be undone', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    $log = AuditLog::where('action', 'project.merged')->sole();

    expect($log->subject_id)->toBe($loser->id)
        ->and($log->details['into_project_id'])->toBe($winner->id)
        ->and($log->details['address'])->toBe('hahaha')
        ->and(collect($log->details['runs'])->pluck('from')->all())->toContain('/events/hahaha/boston-2026');
});

test('the winner gains the loser\'s writers without doubling any; the loser keeps its own', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');
    $shared = Artist::factory()->create();
    Credit::factory()->writing()->for($winner, 'creditable')->create(['artist_id' => $shared->id]);
    Credit::factory()->writing()->for($loser, 'creditable')->create(['artist_id' => $shared->id]);
    Credit::factory()->writing()->for($loser, 'creditable')->create();

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    expect($winner->writers()->count())->toBe(2)
        ->and(Project::withTrashed()->find($loser->id)->writers()->count())->toBe(2);
});

test('waiting requests move to the winner and its keeper is told', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');
    $request = ProjectLinkRequest::create(['event_id' => publishedListing('Hahaha', 'Rome')->id, 'project_id' => $loser->id, 'submitted_at' => now()]);
    Mail::fake();

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    expect($request->fresh()->project_id)->toBe($winner->id);
    Mail::assertQueued(App\Mail\Comments::class, fn ($mail) => $mail->hasTo($winner->organizer->owner->email));
});

test('a deleted run of the loser restores under the winner', function () {
    $winner = twoRunShow('Ha Ha Ha');
    $loser = twoRunShow('Hahaha', 'Boston');
    $run = $loser->runs()->where('instance_slug', 'boston-2026')->sole();
    $run->delete();

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    Event::withTrashed()->find($run->id)->restore();

    expect($run->fresh()->project_id)->toBe($winner->id)
        ->and($run->fresh()->publicPath())->toBe('/events/ha-ha-ha/boston-2026');
});

test('a run off the merged show gets a fresh slug, never the address the winner now owns', function () {
    $winner = twoRunShow('Ha Ha Ha');
    twoRunShow('Hahaha', 'Boston');
    $first = Event::where('slug', 'hahaha')->sole();

    $this->postJson('/api/admin/projects/hahaha/merge', ['into_project_id' => $winner->id])->assertOk();

    $this->deleteJson("/hosting/show-runs/ha-ha-ha/instances/{$first->slug}")->assertOk();

    expect($first->fresh()->slug)->toBe('hahaha-chicago')
        ->and(SlugRedirect::where('path', 'events/hahaha')->sole()->redirectable_id)->toBe($winner->id);
});

test('a show cannot merge into itself, or into one that is gone', function () {
    $project = twoRunShow();

    $this->postJson('/api/admin/projects/ha-ha-ha/merge', ['into_project_id' => $project->id])->assertStatus(422);

    $gone = Project::factory()->create();
    $gone->delete();

    $this->postJson('/api/admin/projects/ha-ha-ha/merge', ['into_project_id' => $gone->id])->assertStatus(404);
    expect(Project::sole()->id)->toBe($project->id);
});

// ----- dissolve -----

test('dissolve over the API, and refused while two runs are left', function () {
    $project = twoRunShow();

    $this->postJson('/api/admin/projects/ha-ha-ha/dissolve')->assertStatus(422);

    $this->deleteJson('/hosting/show-runs/ha-ha-ha/instances/ha-ha-ha-new-york')->assertOk();

    $this->postJson('/api/admin/projects/ha-ha-ha/dissolve')->assertOk()->assertJsonPath('event.public_url', '/events/ha-ha-ha');
    expect(Project::find($project->id))->toBeNull();
});

// ----- link requests -----

test('the list puts escalated requests first and filters to them', function () {
    $project = Project::factory()->create();
    ProjectLinkRequest::create(['event_id' => publishedListing('Calm', 'Rome')->id, 'project_id' => $project->id, 'submitted_at' => now()]);
    $late = ProjectLinkRequest::create(['event_id' => publishedListing('Late', 'Rome')->id, 'project_id' => $project->id, 'submitted_at' => now()->subDays(4), 'escalated_at' => now()]);

    $this->getJson('/api/admin/link-requests')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $late->id);
    $this->getJson('/api/admin/link-requests?escalated=1')->assertJsonCount(1, 'data');
});

test('an escalated request is approved here, and may be sent elsewhere', function () {
    $project = twoRunShow();
    $other = Project::factory()->create();
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $other->id]);

    $this->postJson("/api/admin/link-requests/{$request->id}/approve", ['project_id' => $project->id])->assertOk();

    expect($listing->fresh()->project_id)->toBe($project->id)
        ->and($request->fresh()->project_id)->toBe($project->id);
});

test('sending a request to a show that has gone is refused, not read as what it asked for', function () {
    $asked = publishedListing('Asked', 'Rome');
    $gone = publishedListing('Gone', 'Rome');
    $listing = publishedListing('Listing', 'Paris');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'source_event_id' => $asked->id]);
    $gone->delete();

    $this->postJson("/api/admin/link-requests/{$request->id}/approve", ['source_event_id' => $gone->id])->assertStatus(422);

    expect(Project::count())->toBe(0)->and($request->fresh()->isPending())->toBeTrue();
});

test('a listing still in the queue is refused here, and a decline is recorded', function () {
    $project = Project::factory()->create();
    $listing = publishedListing('Queue', 'Rome', ['status' => 'r']);
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $project->id]);

    $this->postJson("/api/admin/link-requests/{$request->id}/approve")->assertStatus(422);

    $this->postJson("/api/admin/link-requests/{$request->id}/reject", ['note' => 'No'])->assertOk();

    expect($request->fresh()->status)->toBe(ProjectLinkRequest::REJECTED)
        ->and($request->fresh()->note)->toBe('No');
});

// ----- venues -----

test('approve and directions', function () {
    $venue = Venue::factory()->create(['name' => 'The Hall', 'status' => Venue::PENDING]);

    $this->postJson('/api/admin/venues/the-hall/approve')->assertOk();
    $this->patchJson('/api/admin/venues/the-hall', ['directions' => 'Parking on 5th'])->assertOk();

    expect($venue->fresh()->status)->toBe(Venue::APPROVED)
        ->and($venue->fresh()->directions)->toBe('Parking on 5th')
        ->and($venue->fresh()->slug)->toBe('the-hall');
});

test('a renamed venue redirects from its old address', function () {
    Venue::factory()->create(['name' => 'The Hall']);

    $this->patchJson('/api/admin/venues/the-hall', ['name' => 'The Big Hall'])
        ->assertOk()->assertJsonPath('venue.slug', 'the-big-hall');

    expect(SlugRedirect::where('path', 'venues/the-hall')->sole()->destination())->toBe(['/venues/the-big-hall', 301]);
});

test('merging a venue moves its listings and redirects, keeps the loser, and logs them', function () {
    $winner = Venue::factory()->create(['name' => 'Right']);
    $loser = Venue::factory()->create(['name' => 'Wrong']);
    $earlier = Venue::factory()->create(['name' => 'Earlier', 'merged_into_id' => $loser->id]);
    $loser->update(['slug' => 'wrong-now']);
    $location = Location::where('event_id', publishedListing('A', 'Rome')->id)->first();
    $location->update(['venue_id' => $loser->id, 'street' => '1 Own Street']);

    $this->postJson('/api/admin/venues/wrong-now/merge', ['into_venue_id' => $winner->id])->assertOk();

    expect($loser->fresh()->merged_into_id)->toBe($winner->id)
        ->and($earlier->fresh()->merged_into_id)->toBe($winner->id)
        ->and($location->fresh()->venue_id)->toBe($winner->id)
        // The listing keeps its own copied address.
        ->and($location->fresh()->street)->toBe('1 Own Street');

    foreach (['venues/wrong', 'venues/wrong-now'] as $path) {
        expect(SlugRedirect::where('path', $path)->sole()->destination())->toBe(['/venues/right', 301]);
    }

    expect(AuditLog::where('action', 'venue.merged')->sole()->details['location_ids'])->toBe([$location->id]);
});

test('a merged venue is off the list and cannot merge again', function () {
    $winner = Venue::factory()->create(['name' => 'Right']);
    $loser = Venue::factory()->create(['name' => 'Wrong', 'merged_into_id' => $winner->id]);

    $this->getJson('/api/admin/venues')->assertJsonCount(1, 'data');
    $this->postJson("/api/admin/venues/{$winner->slug}/merge", ['into_venue_id' => $winner->id])->assertStatus(422);
    $this->postJson("/api/admin/venues/{$loser->slug}/merge", ['into_venue_id' => $winner->id])->assertStatus(422);
    $this->postJson("/api/admin/venues/{$winner->slug}/merge", ['into_venue_id' => $loser->id])->assertStatus(422);
});

// ----- artists -----

test('artist CRUD', function () {
    $response = $this->postJson('/api/admin/artists', ['name' => 'Jo Doe', 'bio' => 'Director'])->assertCreated();
    $slug = $response->json('artist.slug');

    expect($response->json('artist.status'))->toBe(Artist::APPROVED);

    $this->getJson("/api/admin/artists/{$slug}")->assertOk()->assertJsonPath('artist.credits_count', 0);
    $this->patchJson("/api/admin/artists/{$slug}", ['name' => 'Jo Doe Jr'])->assertOk()->assertJsonPath('artist.slug', $slug);
    $this->getJson('/api/admin/artists?keywords=Jr')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/admin/artists/{$slug}")->assertOk();

    expect(Artist::count())->toBe(0);
});

test('an artist with credits cannot be deleted', function () {
    $artist = Artist::factory()->create();
    Credit::factory()->for(publishedListing('A', 'Rome'), 'creditable')->create(['artist_id' => $artist->id]);

    $this->deleteJson("/api/admin/artists/{$artist->slug}")->assertStatus(422);
});

test('a proposed artist is approved', function () {
    $artist = Artist::factory()->create(['status' => Artist::PROPOSED]);

    $this->postJson("/api/admin/artists/{$artist->slug}/approve")->assertOk();

    expect($artist->fresh()->status)->toBe(Artist::APPROVED);
});

test('merging an artist moves credits, bills the old name, keeps the loser and logs', function () {
    $winner = Artist::factory()->create(['name' => 'Jo Doe']);
    $loser = Artist::factory()->create(['name' => 'Joanna Doe']);
    $earlier = Artist::factory()->create(['merged_into_id' => $loser->id]);
    $run = publishedListing('A', 'Rome');
    $billed = Credit::factory()->for($run, 'creditable')->create(['artist_id' => $loser->id]);
    $named = Credit::factory()->for($run, 'creditable')->create(['artist_id' => $loser->id, 'credited_as' => 'JD']);

    $this->postJson("/api/admin/artists/{$loser->slug}/merge", ['into_artist_id' => $winner->id])->assertOk();

    expect($billed->fresh()->artist_id)->toBe($winner->id)
        ->and($billed->fresh()->credited_as)->toBe('Joanna Doe')
        ->and($named->fresh()->credited_as)->toBe('JD')
        ->and($loser->fresh()->merged_into_id)->toBe($winner->id)
        ->and($earlier->fresh()->merged_into_id)->toBe($winner->id)
        ->and(AuditLog::where('action', 'artist.merged')->sole()->details['credit_ids'])->toBe([$billed->id, $named->id])
        ->and(SlugRedirect::where('path', 'artists/'.$loser->slug)->sole()->destination())->toBe(['/artists/'.$winner->slug, 301]);
});

test('same names leave credited_as alone', function () {
    $winner = Artist::factory()->create(['name' => 'Jo Doe']);
    $loser = Artist::factory()->create(['name' => 'Jo Doe']);
    $credit = Credit::factory()->for(publishedListing('A', 'Rome'), 'creditable')->create(['artist_id' => $loser->id]);

    $this->postJson("/api/admin/artists/{$loser->slug}/merge", ['into_artist_id' => $winner->id])->assertOk();

    expect($credit->fresh()->credited_as)->toBeNull();
});

// ----- credits copy -----

test('copying adds to the destination, skips equivalents and never replaces', function () {
    $from = publishedListing('From', 'Rome');
    $to = publishedListing('To', 'Paris');
    $shared = Artist::factory()->create();
    Credit::factory()->for($from, 'creditable')->create(['artist_id' => $shared->id, 'role' => 'Lead']);
    Credit::factory()->for($from, 'creditable')->create(['role' => 'Swing']);
    Credit::factory()->for($to, 'creditable')->create(['artist_id' => $shared->id, 'role' => 'Lead']);
    $kept = Credit::factory()->for($to, 'creditable')->create(['role' => 'Only here']);

    $this->postJson("/api/admin/events/{$to->slug}/credits/copy", ['from_event_id' => $from->id])
        ->assertOk()->assertJsonPath('added', 1);

    expect($to->credits()->pluck('role')->sort()->values()->all())->toBe(['Lead', 'Only here', 'Swing'])
        ->and($kept->fresh())->not->toBeNull();

    $this->postJson("/api/admin/events/{$to->slug}/credits/copy", ['from_event_id' => $from->id])
        ->assertJsonPath('added', 0);
});

test('a linked destination takes no writers from the copy', function () {
    $from = publishedListing('From', 'Rome');
    Credit::factory()->writing()->for($from, 'creditable')->create();
    $to = publishedListing('To', 'Paris');
    $to->update(['project_id' => Project::factory()->create()->id, 'instance_slug' => 'paris-2026']);

    $this->postJson("/api/admin/events/{$to->slug}/credits/copy", ['from_event_id' => $from->id])
        ->assertOk()->assertJsonPath('added', 0);
});

test('a list cannot be copied onto itself', function () {
    $run = publishedListing('From', 'Rome');

    $this->postJson("/api/admin/events/{$run->slug}/credits/copy", ['from_event_id' => $run->id])->assertStatus(422);
});

// ----- bare organizers -----

test('a bare organizer is public at once, staff-held, claimable, and bothers nobody', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $team = $this->moderator->current_team_id;

    $response = $this->postJson('/api/admin/organizers/bare', ['name' => 'Pony Cam'])
        ->assertCreated()->assertJsonPath('organizer.status', 'p');

    $organizer = Organizer::find($response->json('organizer.id'));

    expect($organizer->description)->toBe('')
        ->and($organizer->user_id)->toBe($this->moderator->id)
        ->and($organizer->isClaimable())->toBeTrue()
        ->and($this->moderator->fresh()->current_team_id)->toBe($team);

    Mail::assertNothingQueued();
    $this->get('/organizers/pony-cam')->assertOk();
});

test('a bare organizer needs a real name', function () {
    $this->postJson('/api/admin/organizers/bare', ['name' => '...'])->assertStatus(422);
    $this->postJson('/api/admin/organizers/bare', [])->assertStatus(422);
});

test('a bare organizer can be the maker straight away', function () {
    $first = publishedListing('Burnout', 'Rome');
    $listing = publishedListing('Burnout', 'Paris');
    $id = $this->postJson('/api/admin/organizers/bare', ['name' => 'Pony Cam'])->json('organizer.id');

    $this->postJson("/api/admin/events/{$listing->slug}/project", ['source_event_id' => $first->id, 'organizer_id' => $id])->assertOk();

    expect(Project::sole()->organizer->name)->toBe('Pony Cam');
});

test('a draft venue is not in the review list and cannot be approved or merged', function () {
    $draft = Venue::factory()->create(['name' => 'Half Added', 'slug' => 'half-added', 'status' => Venue::DRAFT]);
    $live = Venue::factory()->create(['name' => 'Whole', 'slug' => 'whole']);

    expect(collect($this->getJson('/api/admin/venues')->assertOk()->json('data'))->pluck('id'))->not->toContain($draft->id);
    $this->postJson('/api/admin/venues/half-added/approve')->assertStatus(422);
    $this->postJson('/api/admin/venues/half-added/merge', ['into_venue_id' => $live->id])->assertUnprocessable();

    expect($draft->fresh()->status)->toBe(Venue::DRAFT);
});
