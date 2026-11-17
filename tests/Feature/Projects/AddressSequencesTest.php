<?php

use App\Actions\Projects\DissolveProject;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\UnlinkEvent;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\SlugRedirect;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
    $this->actingAs($this->moderator);
    $this->link = app(LinkEventToProject::class);
});

function redirectRow(string $path): ?SlugRedirect
{
    return SlugRedirect::where('path', $path)->first();
}

// ----- takeover of a plain address, and a later listing moving -----

test('the first listing passes its plain address to the project and moves under it', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');

    expect($first->slug)->toBe('ha-ha-ha')->and($second->slug)->toBe('ha-ha-ha-new-york');

    $this->link->link($second, $first, $this->moderator);

    $project = Project::sole();
    $first->refresh();
    $second->refresh();

    expect($project->slug)->toBe('ha-ha-ha')
        ->and($project->name)->toBe('Ha Ha Ha')
        ->and($project->organizer_id)->toBe($first->organizer_id)
        ->and($first->publicPath())->toBe('/events/ha-ha-ha/chicago-2026')
        ->and($second->publicPath())->toBe('/events/ha-ha-ha/new-york-2026')
        // The internal key does not change: the one permitted collision.
        ->and($first->slug)->toBe('ha-ha-ha')
        ->and($second->slug)->toBe('ha-ha-ha-new-york');

    // No row for the address the project took over; one for the later listing's.
    expect(redirectRow('events/ha-ha-ha'))->toBeNull();
    expect(redirectRow('events/ha-ha-ha-new-york')->redirectable_id)->toBe($second->id);

    $this->get('/events/ha-ha-ha-new-york')->assertRedirect('/events/ha-ha-ha/new-york-2026')->assertStatus(301);
    $this->get('/events/ha-ha-ha/chicago-2026')->assertOk();
    $this->get('/events/ha-ha-ha/new-york-2026')->assertOk();
});

test('a third listing joins the same project and its presenter stays its presenter', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);
    $third = publishedListing('Ha Ha Ha: Holiday Edition', 'Boston', ['edition' => 'Holiday Edition']);
    $presenter = $third->organizer_id;

    $this->link->link($third, Project::sole(), $this->moderator);

    expect($third->refresh()->publicPath())->toBe('/events/ha-ha-ha/boston-2026-holiday-edition')
        ->and($third->organizer_id)->toBe($presenter)
        ->and(Project::count())->toBe(1);
});

test('if the plain name is a different company\'s show the project goes by name plus company', function () {
    publishedListing('Dracula', 'London');
    $maker = App\Models\Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $first = publishedListing('Dracula', 'Chicago', [], $maker);
    $second = publishedListing('Dracula', 'Boston', [], $maker);

    expect($first->slug)->toBe('dracula-chicago');

    $this->link->link($second, $first, $this->moderator);

    $project = Project::sole();

    expect($project->slug)->toBe('dracula-pony-cam');
    // Not a takeover, so the first listing's old address redirects like any other.
    $this->get('/events/dracula-chicago')->assertRedirect('/events/dracula-pony-cam/chicago-2026');
});

test('a moderator can correct the maker and the address, and only a free address', function () {
    $first = publishedListing('Burnout Paradise', 'New York');
    $maker = App\Models\Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $taken = publishedListing('Taken', 'Paris');

    expect(fn () => $this->link->link(publishedListing('Burnout Paradise', 'Austin'), $first, $this->moderator, ['maker' => $maker, 'slug' => $taken->slug]))
        ->toThrow(ValidationException::class);
    expect(Project::count())->toBe(0);

    $this->link->link(publishedListing('Burnout Paradise', 'Boston'), $first, $this->moderator, ['maker' => $maker, 'slug' => 'burnout-paradise-pony-cam']);

    $project = Project::sole();
    expect($project->organizer_id)->toBe($maker->id)
        ->and($project->slug)->toBe('burnout-paradise-pony-cam')
        // The presenter of the first run stays who it was.
        ->and($first->fresh()->organizer_id)->not->toBe($maker->id);
});

test('a linked run keeps its other addresses leading to it', function () {
    $first = publishedListing('Old Name', 'Chicago');
    app(NameChangeRequestService::class)->processAdminDirectChange($first, 'Ha Ha Ha');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first->fresh(), $this->moderator);

    $this->get('/events/old-name')->assertRedirect('/events/ha-ha-ha/chicago-2026')->assertStatus(301);
});

test('linking is refused for a draft, and never publishes one', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $draft = publishedListing('Ha Ha Ha', 'New York', ['status' => 'r', 'published_at' => null]);

    expect(fn () => $this->link->link($draft, $first, $this->moderator))->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBe('r')->and(Project::count())->toBe(0);
});

test('an embargoed listing can be linked and stays embargoed', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $embargoed = publishedListing('Ha Ha Ha', 'New York', ['status' => 'e', 'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s')]);

    $this->link->link($embargoed, $first, $this->moderator);

    expect($embargoed->fresh()->status)->toBe('e');
    // The address under the show confirms nothing until it is published.
    // To the public (staff get a preview).
    auth()->forgetGuards();
    $this->get($embargoed->fresh()->publicPath())->assertNotFound();
    $this->get('/events/ha-ha-ha-new-york')->assertNotFound();
});

test('a listing past the 90-day edit lock can still be linked', function () {
    $old = publishedListing('Ha Ha Ha', 'Chicago', ['closingDate' => now()->subDays(200)], null, '2024-03-01 00:00:00');
    $this->actingAs($this->moderator);
    expect($old->isHistorical())->toBeTrue();

    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $old, $this->moderator);

    expect($old->fresh()->publicPath())->toBe('/events/ha-ha-ha/chicago-2024');
});

test('a held draft stays out of a non-moderator\'s reach', function () {
    $held = publishedListing('Crawled', 'Chicago', ['status' => 'p', 'held_for_staff' => false]);
    Event::whereKey($held->id)->update(['held_for_staff' => true]);
    $first = publishedListing('Crawled', 'Boston');
    $project = Project::factory()->create(['organizer_id' => $first->organizer_id]);

    expect(fn () => $this->link->link($held->fresh(), $project, $first->organizer->owner))
        ->toThrow(ValidationException::class);
});

// ----- unlink -----

test('unlinking a later listing returns it to its plain address and its old nested one follows', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);

    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    $second->refresh();

    expect($second->project_id)->toBeNull()
        ->and($second->instance_slug)->toBeNull()
        ->and($second->publicPath())->toBe('/events/ha-ha-ha-new-york')
        // Its old plain alias is its live address again, so that row is gone.
        ->and(redirectRow('events/ha-ha-ha-new-york'))->toBeNull()
        ->and(redirectRow('events/ha-ha-ha/new-york-2026')->redirectable_id)->toBe($second->id);

    $this->get('/events/ha-ha-ha-new-york')->assertOk();
    $this->get('/events/ha-ha-ha/new-york-2026')->assertRedirect('/events/ha-ha-ha-new-york')->assertStatus(301);
    expect(Project::sole()->slug)->toBe('ha-ha-ha');
});

test('unlinking the first listing gives it a fresh slug and leaves the project its address', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);

    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);

    $first->refresh();

    expect($first->slug)->toBe('ha-ha-ha-chicago')
        ->and(Project::sole()->slug)->toBe('ha-ha-ha');

    $this->get('/events/ha-ha-ha-chicago')->assertOk();
    $this->get('/events/ha-ha-ha/chicago-2026')->assertRedirect('/events/ha-ha-ha-chicago');
    // The show still lives at its address.
    $this->get('/events/ha-ha-ha')->assertOk()->assertViewIs('projects.show');
});

test('unlinking after a project rename never hands the former address to the run', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);
    $project = Project::sole();
    $project->update(['slug' => 'ha-ha-ha-renamed']);

    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);

    expect($first->refresh()->slug)->toBe('ha-ha-ha-chicago')
        ->and(redirectRow('events/ha-ha-ha')->redirectable_id)->toBe($project->id);
});

test('an unlinked run returning to the show retakes its old nested address', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    $this->link->link($second->refresh(), Project::sole(), $this->moderator);

    expect($second->refresh()->publicPath())->toBe('/events/ha-ha-ha/new-york-2026')
        ->and(redirectRow('events/ha-ha-ha/new-york-2026'))->toBeNull();
    $this->get('/events/ha-ha-ha/new-york-2026')->assertOk();
});

test('another run never takes an unlinked run\'s old nested address', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    $third = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($third, Project::sole(), $this->moderator);

    expect($third->refresh()->instance_slug)->toBe('new-york-2026-2');
});

test('unlinking clears the edition but keeps the run type and festival', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'Boston', ['edition' => 'Holiday', 'run_type' => 'workshop', 'festival' => 'SXSW']);
    $this->link->link($second, $first, $this->moderator);

    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    expect($second->refresh()->edition)->toBeNull()
        ->and($second->run_type)->toBe('workshop')
        ->and($second->festival)->toBe('SXSW');
});

// ----- dissolve -----

test('dissolve hands the project\'s address to the last run, whichever it is', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);
    $project = Project::sole();

    app(DissolveProject::class)->handle($project, $this->moderator);

    $second->refresh();

    expect($second->slug)->toBe('ha-ha-ha')
        ->and($second->publicPath())->toBe('/events/ha-ha-ha')
        ->and($second->project_id)->toBeNull()
        ->and(redirectRow('events/ha-ha-ha'))->toBeNull()
        ->and(Project::withTrashed()->find($project->id)->slug)->toBe('deleted--'.$project->id)
        ->and(Project::count())->toBe(0);

    $this->get('/events/ha-ha-ha')->assertOk();
    $this->get('/events/ha-ha-ha/new-york-2026')->assertRedirect('/events/ha-ha-ha')->assertStatus(301);
    $this->get('/events/ha-ha-ha-new-york')->assertRedirect('/events/ha-ha-ha')->assertStatus(301);
});

test('dissolve re-points the project\'s former slugs at the run, and old nested paths under them lead there', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);
    $project = Project::sole();
    $project->update(['slug' => 'ha-ha-ha-2']);

    app(DissolveProject::class)->handle($project, $this->moderator);

    $first->refresh();

    expect($first->slug)->toBe('ha-ha-ha-2')
        ->and(redirectRow('events/ha-ha-ha')->redirectable_id)->toBe($first->id)
        ->and(redirectRow('events/ha-ha-ha')->redirectable_type)->toBe(Event::class);

    $this->get('/events/ha-ha-ha')->assertRedirect('/events/ha-ha-ha-2');
    $this->get('/events/ha-ha-ha/chicago-2026')->assertRedirect('/events/ha-ha-ha-2');
});

test('dissolve is refused with more than one run or none', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    $project = Project::sole();

    expect(fn () => app(DissolveProject::class)->handle($project, $this->moderator))->toThrow(ValidationException::class);

    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    expect(fn () => app(DissolveProject::class)->handle($project, $this->moderator))->toThrow(ValidationException::class);
    expect(Project::sole()->slug)->toBe('ha-ha-ha');
});

test('after a dissolve a second run re-creates the show by the normal takeover', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);
    app(DissolveProject::class)->handle(Project::sole(), $this->moderator);

    $third = publishedListing('Ha Ha Ha', 'Boston');
    $this->link->link($third, $first->refresh(), $this->moderator);

    $project = Project::sole();

    expect($project->slug)->toBe('ha-ha-ha')
        ->and(Project::onlyTrashed()->count())->toBe(1)
        ->and($first->refresh()->publicPath())->toBe('/events/ha-ha-ha/chicago-2026')
        // Its old nested address is its live one again.
        ->and(redirectRow('events/ha-ha-ha/chicago-2026'))->toBeNull()
        ->and(redirectRow('events/ha-ha-ha'))->toBeNull();

    $this->get('/events/ha-ha-ha/chicago-2026')->assertOk();
});

test('a survivor with its own name still hands its slug to the re-created show', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha Holiday', 'Boston');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);
    app(DissolveProject::class)->handle(Project::sole(), $this->moderator);

    $this->link->link(publishedListing('Ha Ha Ha Holiday', 'Paris'), $second->refresh(), $this->moderator);

    expect(Project::sole()->slug)->toBe('ha-ha-ha')
        ->and(redirectRow('events/ha-ha-ha'))->toBeNull();
});

test('a request waiting on a dissolved show now asks the listing it became', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    $project = Project::sole();
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    $waiting = publishedListing('Ha Ha Ha', 'Boston', ['status' => 'e']);
    $request = ProjectLinkRequest::create(['event_id' => $waiting->id, 'project_id' => $project->id]);

    app(DissolveProject::class)->handle($project, $this->moderator);

    expect($request->fresh()->project_id)->toBeNull()
        ->and($request->fresh()->source_event_id)->toBe($first->id);

    $this->link->approve($request->fresh(), $this->moderator);

    expect(Project::sole()->slug)->toBe('ha-ha-ha')
        ->and($waiting->refresh()->isLinked())->toBeTrue();
});

// ----- rename, and rename back -----

test('renaming a listing redirects the old address, and renaming back drops the alias', function () {
    $listing = publishedListing('First Name', 'Chicago');
    $service = app(NameChangeRequestService::class);

    $service->processAdminDirectChange($listing, 'Second Name');

    expect($listing->fresh()->slug)->toBe('second-name');
    expect(redirectRow('events/first-name')->redirectable_id)->toBe($listing->id);
    $this->get('/events/first-name')->assertRedirect('/events/second-name')->assertStatus(301);

    $service->processAdminDirectChange($listing->fresh(), 'First Name');

    expect($listing->fresh()->slug)->toBe('first-name')
        ->and(redirectRow('events/first-name'))->toBeNull()
        ->and(redirectRow('events/second-name')->redirectable_id)->toBe($listing->id);

    // No redirect points at the address it is reached by.
    $this->get('/events/first-name')->assertOk();
    $this->get('/events/second-name')->assertRedirect('/events/first-name');
});

test('renaming a first listing never redirects the address its project owns', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);

    app(NameChangeRequestService::class)->processAdminDirectChange($first->refresh(), 'Ha Ha Ha Chicago Cut');

    expect($first->refresh()->slug)->toBe('ha-ha-ha-chicago-cut')
        ->and(redirectRow('events/ha-ha-ha'))->toBeNull()
        ->and($first->publicPath())->toBe('/events/ha-ha-ha/chicago-2026');
});

test('a draft renamed before it was ever public leaves no redirect behind', function () {
    $draft = Event::factory()->create(['name' => 'Draft', 'slug' => 'new-event-abc123', 'status' => 'd']);
    App\Models\Events\Location::factory()->create(['event_id' => $draft->id]);

    app(NameChangeRequestService::class)->processAdminDirectChange($draft, 'A Real Name');

    expect(SlugRedirect::count())->toBe(0);
});

test('a project rename redirects, runs follow, and renaming back drops the alias', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);
    $project = Project::sole();

    $project->update(['slug' => 'laughter']);

    $this->get('/events/ha-ha-ha')->assertRedirect('/events/laughter')->assertStatus(301);
    $this->get('/events/ha-ha-ha/chicago-2026')->assertRedirect('/events/laughter/chicago-2026')->assertStatus(301);

    // Going back is allowed although the first listing's internal slug is that name.
    expect(Project::slugTaken('ha-ha-ha', null, $project->id))->toBeFalse();
    $project->update(['slug' => 'ha-ha-ha']);

    expect(redirectRow('events/ha-ha-ha'))->toBeNull()
        ->and(redirectRow('events/laughter')->redirectable_id)->toBe($project->id);
    $this->get('/events/ha-ha-ha/chicago-2026')->assertOk();
});

// ----- delete, then restore -----

test('a deleted run releases its address, falls back to the show, and takes it back on restore', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);

    $second->refresh()->delete();

    $trashed = Event::withTrashed()->find($second->id);
    expect($trashed->instance_slug)->toBe('deleted--'.$second->id)
        ->and($trashed->slug)->toBe('deleted--'.$second->id);

    // No redirect for a delete: the nested address is simply a stranger now.
    expect(redirectRow('events/ha-ha-ha/new-york-2026'))->toBeNull();
    $this->get('/events/ha-ha-ha/new-york-2026')->assertRedirect('/events/ha-ha-ha')->assertStatus(302);
    // Its old plain alias still reserves the name, and leads to the show.
    $this->get('/events/ha-ha-ha-new-york')->assertRedirect('/events/ha-ha-ha')->assertStatus(302);

    $trashed->restore();

    $second->refresh();
    expect($second->instance_slug)->toBe('new-york-2026')
        ->and($second->project_id)->toBe(Project::sole()->id);
    $this->get('/events/ha-ha-ha/new-york-2026')->assertOk();
});

test('a restored run whose old address was taken gets a fresh one', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    $second->refresh()->delete();

    $third = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($third, Project::sole(), $this->moderator);
    expect($third->refresh()->instance_slug)->toBe('new-york-2026');

    Event::withTrashed()->find($second->id)->restore();

    expect($second->refresh()->instance_slug)->toBe('new-york-2026-2');
});

test('a restored first listing does not get the project\'s address back as its slug', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);

    $first->refresh()->delete();
    Event::withTrashed()->find($first->id)->restore();

    $first->refresh();

    expect($first->slug)->toBe('ha-ha-ha-chicago')
        ->and($first->publicPath())->toBe('/events/ha-ha-ha/chicago-2026')
        ->and(Project::sole()->slug)->toBe('ha-ha-ha');
});

test('a run whose project is gone restores as a plain listing with the show\'s writers', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    $project = Project::sole();
    App\Models\Credit::factory()->writing()->for($project, 'creditable')->create();

    $second->refresh()->delete();
    app(DissolveProject::class)->handle($project, $this->moderator);

    Event::withTrashed()->find($second->id)->restore();

    $second->refresh();

    expect($second->project_id)->toBeNull()
        ->and($second->instance_slug)->toBeNull()
        ->and($second->publicPath())->toBe('/events/ha-ha-ha-new-york')
        ->and($second->credits()->where('department', 'writing')->count())->toBe(1)
        ->and(redirectRow('events/ha-ha-ha-new-york'))->toBeNull();

    $this->get('/events/ha-ha-ha-new-york')->assertOk();
});

test('a deleted plain listing\'s address is gone and free again, as today', function () {
    $listing = publishedListing('Plain', 'Chicago');
    $listing->delete();

    expect(SlugRedirect::count())->toBe(0);
    $this->get('/events/plain')->assertNotFound();
    expect(publishedListing('Plain', 'Boston')->slug)->toBe('plain');
});

// ----- duplicate, then approval -----

test('a duplicate of a one-off asks to join it, survives a rename, and approval makes the project', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $team = $source->organizer->owner;
    $this->actingAs($team);

    $copy = $source->duplicate();

    $request = ProjectLinkRequest::sole();
    expect($request->event_id)->toBe($copy->id)
        ->and($request->source_event_id)->toBe($source->id)
        ->and($request->project_id)->toBeNull()
        ->and($request->requested_by)->toBe($team->id)
        ->and($request->submitted_at)->toBeNull()
        ->and($copy->project_id)->toBeNull();

    // The organizer fills in the copy and renames it; the request stands.
    $copy->update(['name' => 'Flushment Day Returns', 'status' => 'r']);
    App\Models\Events\Location::where('event_id', $copy->id)->update(['city' => 'Boston']);
    App\Models\Events\Show::factory()->create(['event_id' => $copy->id, 'date' => '2027-02-01 00:00:00']);
    expect($request->fresh()->submitted_at)->toBeNull();

    // The listing goes live, as the queue does it: the clock starts.
    $this->actingAs($this->moderator);
    $copy->refresh();
    $copy->update(['slug' => Event::finalSlug($copy), 'status' => 'p', 'published_at' => now()]);
    expect($request->fresh()->submitted_at)->not->toBeNull();

    $this->link->approve($request->fresh(), $this->moderator);

    $project = Project::sole();
    expect($project->slug)->toBe('flushment-day')
        ->and($source->refresh()->publicPath())->toBe('/events/flushment-day/chicago-2026')
        ->and($source->id)->toBe($source->fresh()->id)
        ->and($copy->refresh()->publicPath())->toBe('/events/flushment-day/boston-2027')
        ->and($request->fresh()->status)->toBe(ProjectLinkRequest::APPROVED)
        ->and($request->fresh()->decided_by)->toBe($this->moderator->id);

    $this->get('/events/flushment-day-returns')->assertRedirect('/events/flushment-day/boston-2027');
});

test('approval while the listing is still a draft is refused, and the request stays open', function () {
    $source = publishedListing('Flushment Day', 'Chicago');
    $copy = $source->duplicate();
    $request = ProjectLinkRequest::sole();

    expect(fn () => $this->link->approve($request, $this->moderator))->toThrow(ValidationException::class);

    expect($request->fresh()->isPending())->toBeTrue()
        ->and($copy->fresh()->status)->toBe('0')
        ->and(Project::count())->toBe(0);
});

test('a duplicate of a linked run asks to join its project and copies no run fields', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago', ['run_type' => 'world_premiere', 'festival' => 'SXSW', 'edition' => 'Premiere', 'special_thanks' => "Alice\nBob"]);
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);

    $copy = $first->refresh()->duplicate();

    expect($copy->project_id)->toBeNull()
        ->and($copy->instance_slug)->toBeNull()
        ->and($copy->edition)->toBeNull()
        ->and($copy->run_type)->toBeNull()
        ->and($copy->festival)->toBeNull()
        ->and($copy->special_thanks)->toBe("Alice\nBob")
        ->and($copy->publicPath())->toBe('/events/'.$copy->slug);

    $request = ProjectLinkRequest::where('event_id', $copy->id)->sole();
    expect($request->project_id)->toBe(Project::sole()->id)
        ->and($request->source_event_id)->toBeNull();
});

test('a duplicate of a draft asks for nothing', function () {
    $draft = publishedListing('Unfinished', 'Chicago', ['status' => 'd', 'published_at' => null]);

    $draft->duplicate();

    expect(ProjectLinkRequest::count())->toBe(0);
});

test('if the source was linked in the meantime its project is used', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    $copy = $source->duplicate();
    $request = ProjectLinkRequest::sole();
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $source, $this->moderator);

    $project = Project::sole();
    expect($request->fresh()->project_id)->toBe($project->id);

    $copy->update(['status' => 'p', 'published_at' => now(), 'slug' => 'ha-ha-ha-copy']);
    App\Models\Events\Location::where('event_id', $copy->id)->update(['city' => 'Boston']);
    $this->link->approve($request->fresh(), $this->moderator);

    expect(Project::count())->toBe(1)
        ->and($copy->refresh()->project_id)->toBe($project->id);
});

test('a request for a source that was linked by a stale screen still lands on its project', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $source, $this->moderator);
    $stale = publishedListing('Ha Ha Ha', 'Boston');

    $this->link->link($stale, $source->fresh(), $this->moderator);

    expect(Project::count())->toBe(1)
        ->and($stale->refresh()->project_id)->toBe(Project::sole()->id);
});

test('a decided request cannot be decided twice', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    $other = publishedListing('Ha Ha Ha', 'New York');
    $request = ProjectLinkRequest::create(['event_id' => $other->id, 'source_event_id' => $source->id]);

    $this->link->reject($request, $this->moderator, 'Not the same show');

    expect($request->status)->toBe(ProjectLinkRequest::REJECTED)
        ->and($request->decided_by)->toBe($this->moderator->id)
        ->and(fn () => $this->link->approve($request, $this->moderator))->toThrow(ValidationException::class);
});

// ----- audit, and atomicity -----

test('every step leaves an audit row naming who did it', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');

    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);
    app(DissolveProject::class)->handle(Project::sole(), $this->moderator);

    expect(AuditLog::orderBy('id')->pluck('action')->all())->toBe([
        'project.created', 'project.linked', 'project.linked', 'project.unlinked', 'project.dissolved',
    ]);
    expect(AuditLog::pluck('user_id')->unique()->all())->toBe([$this->moderator->id]);
    expect(AuditLog::where('action', 'project.unlinked')->sole()->details)->toMatchArray([
        'from' => '/events/ha-ha-ha/new-york-2026',
        'to' => '/events/ha-ha-ha-new-york',
    ]);
});

test('a failure part way through leaves nothing behind', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');

    // The second listing's address cannot be saved: the column is full.
    Event::whereKey($second->id)->update(['edition' => str_repeat('x', 255)]);
    App\Models\Events\Location::where('event_id', $second->id)->update(['city' => str_repeat('y', 240)]);

    expect(fn () => $this->link->link($second->fresh(), $first, $this->moderator))->toThrow(Exception::class);

    expect(Project::count())->toBe(0)
        ->and(SlugRedirect::count())->toBe(0)
        ->and(AuditLog::count())->toBe(0)
        ->and($first->fresh()->project_id)->toBeNull()
        ->and($first->fresh()->instance_slug)->toBeNull();
});

test('a moderator\'s link tells the listing\'s organizer and the page keeper, once each', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');

    $this->link->link($second, $first, $this->moderator);

    Mail::assertQueued(App\Mail\Comments::class, 2);
    expect(App\Models\Messaging\Message::count())->toBe(2);
});

test('a page keeper approving a request tells the presenter only, and may not set the maker', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);
    Mail::fake();
    $keeper = $first->organizer->owner;
    $this->actingAs($keeper);
    $third = publishedListing('Ha Ha Ha', 'Boston');
    $request = ProjectLinkRequest::create(['event_id' => $third->id, 'project_id' => Project::sole()->id]);

    expect(fn () => $this->link->approve($request, $keeper, ['slug' => 'nope']))->toThrow(ValidationException::class);

    $this->link->approve($request, $keeper);

    expect($third->refresh()->isLinked())->toBeTrue();
    Mail::assertQueued(App\Mail\Comments::class, 1);
    Mail::assertQueued(App\Mail\Comments::class, fn ($mail) => $mail->hasTo($third->organizer->owner->email)
        && str_contains($mail->attributes['body'], 'is now listed as part of "Ha Ha Ha"'));
});

test('a project cannot be made by anyone but a moderator, and strangers are refused', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $team = $second->organizer->owner;

    expect(fn () => $this->link->link($second, $first, $team))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(Project::count())->toBe(0);
});

// ----- images, and the approval path -----

test('the project gets its own copy of the first listing\'s picture, never the same path', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $disk = Storage::disk('digitalocean');
    $disk->put('/public/event-images/ha-ha-ha/a.webp', 'large');
    $disk->put('/public/event-images/ha-ha-ha/a-thumb.webp', 'thumb');
    $image = $first->images()->create(['large_image_path' => 'event-images/ha-ha-ha/a.webp', 'thumb_image_path' => 'event-images/ha-ha-ha/a-thumb.webp', 'rank' => 0]);
    $first->update(['largeImagePath' => $image->large_image_path, 'thumbImagePath' => $image->thumb_image_path]);

    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first->refresh(), $this->moderator);

    $project = Project::sole();

    expect($project->largeImagePath)->toStartWith('project-images/ha-ha-ha/')
        ->and($project->largeImagePath)->not->toBe($first->largeImagePath)
        ->and($project->images()->count())->toBe(1);
    $disk->assertExists('/public/'.$project->largeImagePath);

    // Cleanup now knows that folder is in use.
    App\Models\Image::where('imageable_type', Project::class)->delete();
    App\Services\ImageHandler::deleteDirectoryIfUnused('project-images/ha-ha-ha');
    $disk->assertExists('/public/'.$project->largeImagePath);
});

test('a re-approval that moves a listing that has been public redirects its old address', function () {
    $listing = publishedListing('Back Again', 'Chicago');
    Event::whereKey($listing->id)->update(['slug' => 'an-older-address', 'status' => 'r']);

    $this->actingAs(App\Models\User::factory()->create(['type' => 'a']))
        ->postJson('/api/admin/approve/events/an-older-address/approve')->assertOk();

    expect($listing->fresh()->slug)->toBe('back-again')
        ->and(redirectRow('events/an-older-address')->redirectable_id)->toBe($listing->id);
});

test('a first approval leaves no redirect from the placeholder slug', function () {
    $draft = publishedListing('Brand New', 'Chicago', ['status' => 'r', 'published_at' => null]);
    Event::whereKey($draft->id)->update(['slug' => 'new-event-xyz123']);

    $this->actingAs(App\Models\User::factory()->create(['type' => 'a']))
        ->postJson('/api/admin/approve/events/new-event-xyz123/approve')->assertOk();

    expect($draft->fresh()->slug)->toBe('brand-new')
        ->and(SlugRedirect::count())->toBe(0);
});

test('a re-approved first listing gets a fresh slug and writes nothing over the project\'s address', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);
    Event::whereKey($first->id)->update(['status' => 'r']);

    $this->actingAs(App\Models\User::factory()->create(['type' => 'a']))
        ->postJson('/api/admin/approve/events/ha-ha-ha/approve')->assertOk();

    expect($first->refresh()->slug)->toBe('ha-ha-ha-chicago')
        ->and($first->publicPath())->toBe('/events/ha-ha-ha/chicago-2026')
        ->and(redirectRow('events/ha-ha-ha'))->toBeNull();
});

test('special thanks is stored but never serialized to a public page', function () {
    $listing = publishedListing('Thanks', 'Chicago', ['special_thanks' => 'A Kickstarter Backer']);

    expect($listing->fresh()->special_thanks)->toBe('A Kickstarter Backer')
        ->and($listing->fresh()->toArray())->not->toHaveKey('special_thanks');

    $this->get('/events/thanks')->assertOk()->assertDontSee('A Kickstarter Backer');
});

test('a soft-deleted run cannot be unlinked into a released address', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    $second->refresh()->delete();

    expect(fn () => app(UnlinkEvent::class)->handle(Event::withTrashed()->find($second->id), $this->moderator))
        ->toThrow(ValidationException::class);
});
