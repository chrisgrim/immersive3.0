<?php

use App\Actions\Projects\RequestLink;
use App\Actions\Projects\UnlinkEvent;
use App\Models\Artist;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

function teamMember(Organizer $organizer): User
{
    $user = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    return $user;
}

beforeEach(function () {
    $this->maker = Organizer::factory()->create();
    $this->keeper = Organizer::factory()->create();
    $this->project = Project::factory()->create(['organizer_id' => $this->maker->id]);
});

// ----- ProjectPolicy -----

test('the maker keeps the page until a moderator hands it on', function () {
    $team = teamMember($this->maker);
    $other = teamMember($this->keeper);

    expect($team->can('edit', $this->project))->toBeTrue()
        ->and($team->can('decideLink', $this->project))->toBeTrue()
        ->and($other->can('edit', $this->project))->toBeFalse();

    $this->project->update(['editor_organizer_id' => $this->keeper->id]);
    $project = $this->project->fresh();

    expect($team->can('edit', $project))->toBeFalse()
        ->and($team->can('decideLink', $project))->toBeFalse()
        ->and($other->can('edit', $project))->toBeTrue()
        ->and($other->can('decideLink', $project))->toBeTrue();
});

test('moderators may do everything to a project, others may not dissolve or create one', function () {
    $moderator = staffModerator();
    $team = teamMember($this->maker);
    $curator = User::factory()->create(['type' => 'c']);

    expect($moderator->can('edit', $this->project))->toBeTrue()
        ->and($moderator->can('dissolve', $this->project))->toBeTrue()
        ->and($moderator->can('create', Project::class))->toBeTrue()
        ->and($team->can('dissolve', $this->project))->toBeFalse()
        ->and($team->can('create', Project::class))->toBeFalse()
        ->and($curator->can('edit', $this->project))->toBeFalse();
});

test('a moderator\'s API token without the moderate scope is not a moderator here', function () {
    $moderator = staffModerator();
    $curator = User::factory()->create(['type' => 'c']);
    $run = publishedListing('Run', 'Paris');

    Passport::actingAs($moderator, ['mcp:use']);

    expect($moderator->can('dissolve', $this->project))->toBeFalse()
        ->and($moderator->can('create', Project::class))->toBeFalse()
        ->and($moderator->can('edit', $this->project))->toBeFalse()
        ->and($moderator->can('editOnRun', [Credit::class, $run, 'cast']))->toBeFalse();

    Passport::actingAs($curator, ['mcp:use']);

    expect($curator->can('editOnRun', [Credit::class, $run, 'cast']))->toBeFalse();

    Passport::actingAs($moderator, ['mcp:use', 'mcp:moderate']);

    expect($moderator->can('editOnRun', [Credit::class, $run, 'cast']))->toBeTrue();
});

test('keeping the page lets a company take a run off, and strangers not', function () {
    $run = publishedListing('Run', 'Paris');
    $run->update(['project_id' => $this->project->id, 'instance_slug' => 'paris-2026']);

    expect(fn () => app(UnlinkEvent::class)->handle($run->fresh(), teamMember($this->keeper)))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($this->maker->owner);
    app(UnlinkEvent::class)->handle($run->fresh(), $this->maker->owner);

    expect($run->fresh()->isLinked())->toBeFalse();
});

// ----- VenuePolicy / ArtistPolicy -----

test('any team member can propose a venue or an artist; only moderators approve or merge', function () {
    $team = teamMember($this->maker);
    $loner = User::factory()->create(['type' => 'u']);
    $moderator = staffModerator();
    $venue = Venue::factory()->create();
    $artist = Artist::factory()->create();

    foreach ([[Venue::class, $venue], [Artist::class, $artist]] as [$class, $model]) {
        expect($team->can('propose', $class))->toBeTrue()
            ->and($loner->can('propose', $class))->toBeFalse()
            ->and($team->can('approve', $model))->toBeFalse()
            ->and($team->can('merge', $class))->toBeFalse()
            ->and($team->can('update', $model))->toBeFalse()
            ->and($moderator->can('approve', $model))->toBeTrue()
            ->and($moderator->can('merge', $class))->toBeTrue();
    }
});

// ----- CreditPolicy -----

test('a listing\'s team edits its credits under today\'s rules', function () {
    $run = publishedListing('Run', 'Paris');
    $team = teamMember($run->organizer);
    $stranger = User::factory()->create(['type' => 'u']);

    expect($team->can('editOnRun', [Credit::class, $run, 'cast']))->toBeTrue()
        ->and($team->can('editOnRun', [Credit::class, $run, 'production']))->toBeTrue()
        ->and($stranger->can('editOnRun', [Credit::class, $run, 'cast']))->toBeFalse();

    $inReview = publishedListing('Run 2', 'Paris', ['status' => 'r'], $run->organizer);
    $locked = publishedListing('Run 3', 'Paris', ['closingDate' => now()->subDays(100)], $run->organizer);

    expect($team->can('editOnRun', [Credit::class, $inReview, 'cast']))->toBeFalse()
        ->and($team->can('editOnRun', [Credit::class, $locked, 'cast']))->toBeFalse()
        ->and(staffModerator()->can('editOnRun', [Credit::class, $locked, 'cast']))->toBeTrue();
});

test('curators may edit cast on any run, even a locked one, and nothing else', function () {
    $curator = User::factory()->create(['type' => 'c']);
    $locked = publishedListing('Run', 'Paris', ['closingDate' => now()->subDays(100)]);

    expect($curator->can('editOnRun', [Credit::class, $locked, 'cast']))->toBeTrue()
        ->and($curator->can('editOnRun', [Credit::class, $locked, 'creative']))->toBeFalse()
        ->and($curator->can('editOnRun', [Credit::class, $locked, 'writing']))->toBeFalse()
        ->and($curator->can('editOnProject', [Credit::class, $this->project]))->toBeFalse()
        ->and($curator->can('viewOnRun', [Credit::class, $locked]))->toBeTrue()
        // Curators do not pass manage, which is why credits sit outside it.
        ->and($curator->can('manage', $locked))->toBeFalse();
});

test('a held draft is out of a curator\'s reach too', function () {
    $curator = User::factory()->create(['type' => 'c']);
    $held = publishedListing('Crawled', 'Paris', ['status' => 'd']);
    Event::whereKey($held->id)->update(['held_for_staff' => true]);

    expect($curator->can('editOnRun', [Credit::class, $held->fresh(), 'cast']))->toBeFalse()
        ->and($curator->can('viewOnRun', [Credit::class, $held->fresh()]))->toBeFalse();
});

test('a linked run takes no writers from anyone; the page keeper edits them on the show', function () {
    $run = publishedListing('Run', 'Paris');
    $run->update(['project_id' => $this->project->id, 'instance_slug' => 'paris-2026']);
    $run = $run->fresh();

    expect(staffModerator()->can('editOnRun', [Credit::class, $run, 'writing']))->toBeFalse()
        ->and(teamMember($run->organizer)->can('editOnRun', [Credit::class, $run, 'writing']))->toBeFalse()
        ->and(teamMember($this->maker)->can('editOnProject', [Credit::class, $this->project]))->toBeTrue()
        ->and(teamMember($run->organizer)->can('editOnProject', [Credit::class, $this->project]))->toBeFalse();
});

test('a standalone listing\'s team may hold writers', function () {
    $run = publishedListing('Run', 'Paris');

    expect(teamMember($run->organizer)->can('editOnRun', [Credit::class, $run, 'writing']))->toBeTrue();
});

test('keeping the project page confers nothing over another presenter\'s run', function () {
    $run = publishedListing('Run', 'Paris');
    $run->update(['project_id' => $this->project->id, 'instance_slug' => 'paris-2026']);
    $keeperTeam = teamMember($this->maker);

    expect($keeperTeam->can('copyOnto', [Credit::class, $run->fresh()]))->toBeFalse()
        ->and($keeperTeam->can('editOnRun', [Credit::class, $run->fresh(), 'cast']))->toBeFalse()
        ->and(teamMember($run->organizer)->can('copyOnto', [Credit::class, $run->fresh()]))->toBeTrue()
        ->and(staffModerator()->can('copyOnto', [Credit::class, $run->fresh()]))->toBeTrue();
});

// ----- EventPolicy::requestLink and RequestLink -----

test('a listing\'s team can ask to join a show; strangers and held drafts cannot', function () {
    $listing = publishedListing('Listing', 'Paris');
    $team = teamMember($listing->organizer);
    $stranger = User::factory()->create(['type' => 'u']);

    expect($team->can('requestLink', $listing))->toBeTrue()
        ->and($stranger->can('requestLink', $listing))->toBeFalse()
        ->and($stranger->can('requestLink', $listing))->toBeFalse();

    Event::whereKey($listing->id)->update(['status' => 'd', 'held_for_staff' => true]);

    expect($team->can('requestLink', $listing->fresh()))->toBeFalse()
        ->and(staffModerator()->can('requestLink', $listing->fresh()))->toBeTrue();
});

test('a listing past its edit lock can still ask', function () {
    $old = publishedListing('Old', 'Paris', ['closingDate' => now()->subDays(200)]);

    expect(teamMember($old->organizer)->can('requestLink', $old))->toBeTrue();
});

test('asking again replaces the pending request', function () {
    $listing = publishedListing('Listing', 'Paris');
    $team = teamMember($listing->organizer);
    $action = app(RequestLink::class);
    publishedListing('A Run', 'Lima')->update(['project_id' => $this->project->id, 'instance_slug' => 'lima-2026']);

    $first = $action->handle($listing, $this->project, $team);
    $second = $action->handle($listing, publishedListing('Other', 'Rome'), $team, 'This one');

    expect($first->fresh()->status)->toBe(ProjectLinkRequest::WITHDRAWN)
        ->and($second->isPending())->toBeTrue()
        ->and($second->note)->toBe('This one')
        ->and(ProjectLinkRequest::pending()->where('event_id', $listing->id)->count())->toBe(1);
});

test('a linked source means its project', function () {
    $source = publishedListing('Source', 'Rome');
    $source->update(['project_id' => $this->project->id, 'instance_slug' => 'rome-2026']);
    $listing = publishedListing('Listing', 'Paris');

    $request = app(RequestLink::class)->handle($listing, $source->fresh(), teamMember($listing->organizer));

    expect($request->project_id)->toBe($this->project->id)
        ->and($request->source_event_id)->toBeNull();
});

test('a stranger cannot ask, and a linked or self-targeted listing is refused', function () {
    $listing = publishedListing('Listing', 'Paris');
    $team = teamMember($listing->organizer);
    $action = app(RequestLink::class);

    expect(fn () => $action->handle($listing, $this->project, User::factory()->create(['type' => 'u'])))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->handle($listing, $listing, $team))->toThrow(ValidationException::class);

    $listing->update(['project_id' => $this->project->id, 'instance_slug' => 'paris-2026']);

    expect(fn () => $action->handle($listing->fresh(), Project::factory()->create(), $team))->toThrow(ValidationException::class);
});

test('a draft\'s team can take back the request its duplicate made', function () {
    $source = publishedListing('Source', 'Rome');
    $team = teamMember($source->organizer);
    $this->actingAs($team);
    $copy = $source->duplicate();

    app(RequestLink::class)->withdraw($copy, $team);

    expect(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::WITHDRAWN);
});

test('the duplicate route makes the request for an organizer, end to end', function () {
    $source = publishedListing('Source', 'Rome');
    $owner = $source->organizer->owner;

    $this->actingAs($owner)->postJson("/api/events/{$source->slug}/duplicate")->assertCreated();

    expect(ProjectLinkRequest::sole()->source_event_id)->toBe($source->id)
        ->and(ProjectLinkRequest::sole()->requested_by)->toBe($owner->id);
});

test('a copy that is its own event, not a new run, asks to join nothing', function () {
    $source = publishedListing('Source', 'Rome');
    $owner = $source->organizer->owner;

    $copy = $this->actingAs($owner)->postJson("/api/events/{$source->slug}/duplicate", ['as_run' => false])
        ->assertCreated()->json('event');

    expect($copy['name'])->toBe('Source (Copy)')
        ->and(ProjectLinkRequest::count())->toBe(0);
});
