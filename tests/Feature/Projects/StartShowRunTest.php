<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * Duplicate: "a new run of the same show" from a live listing in no show run
 * yet makes the show run at once (that listing its first run, kept by its own
 * company); the copy asks to join it, and EI's review of the copy confirms it.
 */

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->source = publishedListing('Nona Supper', 'Rohnert Park');
    $this->owner = $this->source->organizer->owner;
});

test('a new run of a live listing starts its show run straight away', function () {
    $response = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])
        ->assertCreated();

    $project = Project::sole();
    expect($response->json('project.slug'))->toBe($project->slug)
        ->and($project->name)->toBe('Nona Supper')
        ->and($project->keeper()->id)->toBe($this->source->organizer_id)
        ->and($this->source->fresh()->project_id)->toBe($project->id);

    // The copy is a draft asking to join it: nothing is published by this.
    $copy = Event::find($response->json('event.id'));
    expect($copy->project_id)->toBeNull()
        ->and($copy->status)->not->toBe('p')
        ->and(ProjectLinkRequest::pending()->sole()->only(['event_id', 'project_id']))->toBe(['event_id' => $copy->id, 'project_id' => $project->id]);
});

test('a duplicate that does not say (the older callers) still only asks to join the listing', function () {
    $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate")->assertCreated();

    expect(Project::count())->toBe(0)
        ->and(ProjectLinkRequest::sole()->source_event_id)->toBe($this->source->id);
});

test('starting it settles the requests around the listing, as any link does', function () {
    // An earlier copy asking to join the listing itself, and the listing asking to join another show.
    $earlier = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate")->json('event.id');
    $theirs = publishedListing('Their Show', 'Rome');
    app(App\Actions\Projects\RequestLink::class)->handle($this->source, $theirs, $this->owner);

    $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->assertCreated();
    $project = Project::sole();

    expect(ProjectLinkRequest::where('event_id', $earlier)->sole()->only(['status', 'project_id', 'source_event_id']))
        ->toBe(['status' => ProjectLinkRequest::PENDING, 'project_id' => $project->id, 'source_event_id' => null])
        ->and(ProjectLinkRequest::pending()->where('event_id', $this->source->id)->exists())->toBeFalse();
});

test('just a copy starts nothing', function () {
    $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => false])->assertCreated();

    expect(Project::count())->toBe(0)->and(ProjectLinkRequest::count())->toBe(0)
        ->and($this->source->fresh()->project_id)->toBeNull();
});

test('a draft starts no show run', function () {
    $this->source->update(['status' => '5']);

    $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->assertCreated();

    expect(Project::count())->toBe(0);
});

test('only the listing\'s own company starts its show run', function () {
    $stranger = User::factory()->create(['type' => 'u']);

    expect(fn () => app(App\Actions\Projects\LinkEventToProject::class)->startFrom($this->source, $stranger))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(Project::count())->toBe(0);
});

test('deleting the copy before it joins undoes the show run it started', function () {
    $copy = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.slug');
    $address = Project::sole()->slug;

    $this->deleteJson("/hosting/event/{$copy}")->assertOk();

    expect(Project::count())->toBe(0)
        ->and($this->source->fresh()->project_id)->toBeNull()
        ->and($this->source->fresh()->slug)->toBe($address)
        ->and(ProjectLinkRequest::pending()->exists())->toBeFalse();
});

test('taking the copy back undoes it too, but a show run with another run stays', function () {
    $copy = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.slug');
    $this->deleteJson("/hosting/event/{$copy}/link-request")->assertOk();
    expect(Project::count())->toBe(0)->and($this->source->fresh()->project_id)->toBeNull();

    // A show run with two runs is never undone by a copy leaving.
    $this->postJson("/api/events/{$this->source->fresh()->slug}/duplicate", ['as_run' => true])->assertCreated();
    $project = Project::sole();
    $second = publishedListing('Nona Supper', 'Napa', [], $this->source->organizer);
    $second->forceFill(['project_id' => $project->id, 'instance_slug' => 'napa'])->save();
    $another = Event::where('name', 'like', '%(Copy)%')->whereNull('deleted_at')->latest('id')->first();
    $this->deleteJson("/hosting/event/{$another->slug}")->assertOk();

    expect(Project::count())->toBe(1);
});

test('a copy turned down undoes the show run it started', function () {
    $copy = Event::find($this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.id'));
    $request = ProjectLinkRequest::pending()->sole();

    app(App\Actions\Projects\LinkEventToProject::class)->reject($request, staffModerator(), 'Not the same show');

    expect(Project::count())->toBe(0)->and($this->source->fresh()->project_id)->toBeNull();
});

test('the original deleted while its copy waits: the show run stays for the copy, and goes once nothing is left', function () {
    $copy = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.slug');
    $project = Project::sole();

    $this->deleteJson('/hosting/event/'.$this->source->fresh()->slug)->assertOk();
    expect(Project::find($project->id))->not->toBeNull();

    $this->deleteJson("/hosting/event/{$copy}")->assertOk();
    expect(Project::find($project->id))->toBeNull();
});

test('a show run that goes away cancels a new name waiting for EI', function () {
    $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true]);
    $project = Project::sole();
    $request = $project->nameChangeRequests()->create(['current_name' => $project->name, 'requested_name' => 'Spam', 'user_id' => $this->owner->id, 'status' => 'pending']);

    $project->delete();

    expect($request->fresh()->status)->toBe('withdrawn');
});

test('a show run the copy has joined is never undone by someone else withdrawing later', function () {
    $copyId = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.id');
    $project = Project::sole();
    // The copy is approved and joins, then is taken out again: one run left.
    ProjectLinkRequest::pending()->where('event_id', $copyId)->update(['status' => ProjectLinkRequest::APPROVED]);

    $theirs = publishedListing('Nona Supper', 'Napa');
    $request = app(App\Actions\Projects\RequestLink::class)->handle($theirs, $project, $theirs->organizer->owner);
    $this->actingAs($theirs->organizer->owner)->deleteJson("/hosting/event/{$theirs->slug}/link-request")->assertOk();

    expect(Project::find($project->id))->not->toBeNull()
        ->and($this->source->fresh()->project_id)->toBe($project->id);
});

test('switching the copy to another show run undoes the start it left', function () {
    $copy = Event::find($this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.id'));
    $started = Project::sole();
    $other = publishedListing('Other Show', 'Rome', [], $this->source->organizer);
    $otherRun = app(App\Actions\Projects\LinkEventToProject::class)->startFrom($other, $this->owner);

    $this->postJson("/hosting/event/{$copy->slug}/link-request", ['project_id' => $otherRun->id])->assertSuccessful();

    expect(Project::find($started->id))->toBeNull()->and($this->source->fresh()->project_id)->toBeNull();
});

test('the original deleted, then the copy withdrawn: the empty show run goes', function () {
    $copy = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate", ['as_run' => true])->json('event.slug');
    $project = Project::sole();
    $this->deleteJson('/hosting/event/'.$this->source->fresh()->slug)->assertOk();

    $this->deleteJson("/hosting/event/{$copy}/link-request")->assertOk();

    expect(Project::find($project->id))->toBeNull();
});

test('deleting a listing withdraws requests asking to join that listing itself', function () {
    $copyId = $this->actingAs($this->owner)->postJson("/api/events/{$this->source->slug}/duplicate")->json('event.id');
    expect(ProjectLinkRequest::pending()->sole()->source_event_id)->toBe($this->source->id);

    $this->deleteJson("/hosting/event/{$this->source->slug}")->assertOk();

    expect(ProjectLinkRequest::pending()->exists())->toBeFalse();
});
