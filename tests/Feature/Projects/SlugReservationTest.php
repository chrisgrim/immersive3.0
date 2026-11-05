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
