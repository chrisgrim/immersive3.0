<?php

use App\Actions\Projects\LinkEventToProject;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
});

test('a new listing cannot take an address that redirects to another one', function () {
    $listing = publishedListing('First Name', 'Chicago');
    app(NameChangeRequestService::class)->processAdminDirectChange($listing, 'Second Name');

    $newcomer = publishedListing('First Name', 'Boston');

    expect($newcomer->slug)->toBe('first-name-boston');
});

test('a new listing cannot take a project\'s live address', function () {
    $moderator = staffModerator();
    $this->actingAs($moderator);
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    app(LinkEventToProject::class)->link(publishedListing('Ha Ha Ha', 'New York'), $first, $moderator);
    $first->refresh()->delete();

    // The first listing's slug is released, but the project still owns the address.
    expect(publishedListing('Ha Ha Ha', 'Boston')->slug)->toBe('ha-ha-ha-boston');
});

test('a project cannot be given an address an event or another project has', function () {
    $listing = publishedListing('Taken', 'Paris');
    $project = Project::factory()->create(['slug' => 'other-show']);

    expect(Project::slugTaken('taken'))->toBeTrue()
        ->and(Project::slugTaken('other-show'))->toBeTrue()
        ->and(Project::slugTaken('taken', $listing))->toBeFalse()
        ->and(Project::slugTaken('free-one'))->toBeFalse()
        ->and(Project::slugTaken('other-show', null, $project->id))->toBeFalse();
});

test('a project cannot be given an address reserved for something else', function () {
    SlugRedirect::remember('events/old-address', publishedListing('Someone', 'Paris'));

    expect(Project::slugTaken('old-address'))->toBeTrue();
});

test('a deleted listing\'s released address blocks nothing, and a deleted project\'s neither', function () {
    $project = Project::factory()->create(['slug' => 'the-show']);
    $project->delete();

    expect($project->fresh() ?? Project::withTrashed()->find($project->id))->not->toBeNull()
        ->and(Project::withTrashed()->find($project->id)->slug)->toBe('deleted--'.$project->id)
        ->and(publishedListing('The Show', 'Paris')->slug)->toBe('the-show');
});

test('a new organizer cannot take an address that redirects to another', function () {
    $organizer = Organizer::factory()->create(['name' => 'Old Company']);
    $organizer->update(['name' => 'New Company']);

    $newcomer = Organizer::factory()->create(['name' => 'Old Company']);

    expect($newcomer->slug)->toBe('old-company-1');
});

test('an organizer slug move that is not a rename writes no row', function () {
    $organizer = Organizer::factory()->create(['name' => 'Company']);

    $organizer->update(['slug' => 'company-old']);

    expect(SlugRedirect::count())->toBe(0);
});

test('the admin slug swap keeps working: source address to the destination, no row for it', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $source = Organizer::factory()->create(['name' => 'Clean Name', 'status' => 'p']);
    $destination = Organizer::factory()->create(['name' => 'Destination Co', 'status' => 'p']);
    $project = Project::factory()->create(['organizer_id' => $source->id, 'editor_organizer_id' => $source->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/manage/organizers/{$source->slug}/move-events", [
            'destination_organizer_id' => $destination->id,
            'swap_slug' => true,
        ])->assertOk();

    expect($destination->fresh()->slug)->toBe('clean-name')
        ->and($source->fresh()->slug)->toBe('clean-name-old')
        ->and(SlugRedirect::where('path', 'organizers/clean-name')->exists())->toBeFalse()
        ->and(SlugRedirect::where('path', 'organizers/destination-co')->sole()->redirectable_id)->toBe($destination->id)
        // Its projects go with its listings.
        ->and($project->fresh()->organizer_id)->toBe($destination->id)
        ->and($project->fresh()->editor_organizer_id)->toBe($destination->id);

    $this->get('/organizers/destination-co')->assertRedirect('/organizers/clean-name');
});

test('an organizer that makes or keeps a live project cannot be deleted', function () {
    $maker = Organizer::factory()->create();
    $keeper = Organizer::factory()->create();
    $project = Project::factory()->create(['organizer_id' => $maker->id, 'editor_organizer_id' => $keeper->id]);

    expect(fn () => $maker->deleteOrganizer($maker))->toThrow(ValidationException::class);
    expect(fn () => $keeper->deleteOrganizer($keeper))->toThrow(ValidationException::class);
    expect(Organizer::whereKey([$maker->id, $keeper->id])->count())->toBe(2);

    // A dissolved project is a record, and holds nobody back.
    $project->delete();
    $maker->deleteOrganizer($maker);
    expect(Organizer::find($maker->id))->toBeNull();
});

test('deleting an organizer never touches another presenter\'s run of its show', function () {
    $maker = Organizer::factory()->create();
    $project = Project::factory()->create(['organizer_id' => $maker->id]);
    $run = publishedListing('Run', 'Paris');
    $run->update(['project_id' => $project->id, 'instance_slug' => 'paris-2026']);

    expect(fn () => $maker->deleteOrganizer($maker))->toThrow(ValidationException::class);
    expect($run->fresh())->not->toBeNull();
});

test('a show can take the plain address one of its own runs went by before joining', function () {
    // Handle With Care: the show started from the London listing, then the
    // standalone listing at /events/handle-with-care joined it, leaving that
    // address as a redirect to its run. The show must be able to take it.
    $moderator = staffModerator();
    $this->actingAs($moderator);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $london = publishedListing('Care Show London', 'London', [], $organizer);
    app(LinkEventToProject::class)->link(publishedListing('Care Show Edinburgh', 'Edinburgh', [], $organizer), $london, $moderator);
    $project = $london->refresh()->project;

    $plain = publishedListing('Care Show', 'Battersea', [], $organizer);
    expect($plain->slug)->toBe('care-show');
    app(LinkEventToProject::class)->link($plain, $project, $moderator);
    // Renamed after joining, as Kathryn did: the run moves off the plain
    // address, which stays behind as a redirect to it.
    app(NameChangeRequestService::class)->processAdminDirectChange($plain->fresh(), 'Care Show (Battersea)');
    expect($plain->fresh()->slug)->not->toBe('care-show')
        ->and(SlugRedirect::where('path', 'events/care-show')->exists())->toBeTrue();

    // Something else's old address still blocks it.
    SlugRedirect::remember('events/someone-else', publishedListing('Someone Else', 'Paris'));
    expect(Project::slugTaken('someone-else', null, $project->id))->toBeTrue()
        ->and(Project::slugTaken('care-show', null, $project->id))->toBeFalse();

    app(App\Actions\Projects\UpdateProject::class)->handle($project, ['slug' => 'care-show'], $moderator);

    // The old link now opens the show itself.
    expect(SlugRedirect::where('path', 'events/care-show')->exists())->toBeFalse();
    $this->withoutVite()->get('/events/care-show')->assertOk()->assertSee('Care Show');
});

test('linking a listing and giving the show its address happens in one decision', function () {
    $moderator = staffModerator();
    $this->actingAs($moderator);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $london = publishedListing('Duo London', 'London', [], $organizer);
    app(LinkEventToProject::class)->link(publishedListing('Duo Paris', 'Paris', [], $organizer), $london, $moderator);
    $project = $london->refresh()->project;
    $plain = publishedListing('Duo', 'Berlin', [], $organizer);

    app(LinkEventToProject::class)->decide($plain, null, $project, $moderator, null, 'duo');

    expect($project->refresh()->slug)->toBe('duo')
        ->and($plain->refresh()->project_id)->toBe($project->id);
});

test('a new show takes the plain name of the listing joining it', function () {
    $moderator = staffModerator();
    $this->actingAs($moderator);
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $plain = publishedListing('Trio', 'Berlin', [], $organizer);
    $chicago = publishedListing('Trio', 'Chicago', [], $organizer);
    expect($chicago->slug)->toBe('trio-chicago');

    // Chicago starts the show; the listing at /events/trio joins it.
    app(LinkEventToProject::class)->link($plain, $chicago, $moderator);

    expect($chicago->refresh()->project->slug)->toBe('trio');
    $this->withoutVite()->get('/events/trio')->assertOk();
});

test('an old address of a deleted listing reserves nothing', function () {
    $listing = publishedListing('Gone Show', 'Rome');
    app(NameChangeRequestService::class)->processAdminDirectChange($listing, 'Renamed Show');
    expect(SlugRedirect::where('path', 'events/gone-show')->exists())->toBeTrue();
    $listing->fresh()->delete();

    $newcomer = publishedListing('Gone Show', 'Oslo');
    expect($newcomer->slug)->toBe('gone-show')
        ->and(Project::slugTaken('gone-show'))->toBeTrue() // the new listing holds it now
        ->and(SlugRedirect::where('path', 'events/gone-show')->exists())->toBeFalse();

    // Restoring the old one does not take the address back.
    $listing->fresh()->restore();
    $this->withoutVite()->get('/events/gone-show')->assertOk()->assertSee('Gone Show');
    expect(SlugRedirect::where('path', 'events/gone-show')->exists())->toBeFalse();
});

test('a venue can take the name of a venue merged into it', function () {
    $staff = staffModerator();
    $winner = App\Models\Venue::factory()->create(['name' => 'Vaults Theatre', 'slug' => 'vaults-theatre']);
    $loser = App\Models\Venue::factory()->create(['name' => 'The Vaults', 'slug' => 'the-vaults']);
    app(App\Actions\Projects\MergeVenues::class)->handle($loser, $winner, $staff);

    expect(App\Models\Venue::uniqueSlug('The Vaults', $winner->id))->toBe('the-vaults');
    $winner->refresh()->update(['name' => 'The Vaults', 'slug' => 'the-vaults']);

    expect($loser->refresh()->slug)->toBe('merged--'.$loser->id)
        ->and(SlugRedirect::where('path', 'venues/the-vaults')->exists())->toBeFalse();
    $this->withoutVite()->get('/venues/the-vaults')->assertOk();
    // Someone else still cannot have it.
    expect(App\Models\Venue::uniqueSlug('The Vaults'))->toBe('the-vaults-2');
});
