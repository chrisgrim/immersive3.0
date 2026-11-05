<?php

use App\Actions\Projects\DissolveProject;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\UnlinkEvent;
use App\Enums\CreditDepartment;
use App\Models\Artist;
use App\Models\Credit;
use App\Models\Project;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
    $this->actingAs($this->moderator);
    $this->link = app(LinkEventToProject::class);
});

function creditOn($creditable, array $attributes = []): Credit
{
    return Credit::factory()->for($creditable, 'creditable')->create($attributes);
}

function writerOn($creditable, array $attributes = []): Credit
{
    return Credit::factory()->writing()->for($creditable, 'creditable')->create($attributes);
}

test('project creation moves the first listing\'s writers up; its other rows stay', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $writer = writerOn($first);
    $actor = creditOn($first);

    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);

    $project = Project::sole();

    expect($project->writers()->pluck('artist_id')->all())->toBe([$writer->artist_id])
        ->and($first->credits()->pluck('id')->all())->toBe([$actor->id]);
});

test('a joining listing merges its writers: an equivalent credit drops, a different role stays', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $artist = Artist::factory()->create();
    writerOn($first, ['artist_id' => $artist->id, 'role' => 'Book']);
    $this->link->link(publishedListing('Ha Ha Ha', 'New York'), $first, $this->moderator);

    $third = publishedListing('Ha Ha Ha', 'Boston');
    writerOn($third, ['artist_id' => $artist->id, 'role' => 'Book']);
    writerOn($third, ['artist_id' => $artist->id, 'role' => 'Lyrics']);
    writerOn($third, ['artist_id' => $artist->id, 'role' => 'Book', 'starts_on' => '2026-01-01']);
    writerOn($third, ['artist_id' => $artist->id, 'role' => 'Book', 'credited_as' => 'A. Pen Name']);

    $this->link->link($third, Project::sole(), $this->moderator);

    expect(Project::sole()->writers()->count())->toBe(4)
        ->and($third->credits()->where('department', 'writing')->count())->toBe(0);
});

test('a newly linked run copies the show\'s most recent run, by first date', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago', [], null, '2024-01-01 00:00:00');
    creditOn($first, ['role' => 'Older']);
    $second = publishedListing('Ha Ha Ha', 'New York', [], null, '2026-01-01 00:00:00');
    $this->link->link($second, $first, $this->moderator);
    // The second run started as a copy; give it its own list.
    $second->credits()->delete();
    $newest = creditOn($second, ['role' => 'Newer']);

    $third = publishedListing('Ha Ha Ha', 'Boston', [], null, '2026-06-01 00:00:00');
    $this->link->link($third, Project::sole(), $this->moderator);

    expect($third->credits()->pluck('role')->all())->toBe(['Newer'])
        ->and($third->credits()->first()->artist_id)->toBe($newest->artist_id)
        ->and(Artist::count())->toBe(2);
});

test('the presenter\'s own last run wins over the show\'s most recent', function () {
    $presenter = App\Models\Organizer::factory()->create(['status' => 'p']);
    $first = publishedListing('Ha Ha Ha', 'Chicago', [], $presenter, '2024-01-01 00:00:00');
    creditOn($first, ['role' => 'Mine']);
    $second = publishedListing('Ha Ha Ha', 'New York', [], null, '2026-01-01 00:00:00');
    $this->link->link($second, $first, $this->moderator);
    $second->credits()->delete();
    creditOn($second, ['role' => 'Someone else\'s']);

    $third = publishedListing('Ha Ha Ha', 'Boston', [], $presenter, '2026-06-01 00:00:00');
    $this->link->link($third, Project::sole(), $this->moderator);

    expect($third->credits()->pluck('role')->all())->toBe(['Mine']);
});

test('a listing that already has credits keeps them and receives no copy', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    creditOn($first, ['role' => 'Theirs']);
    $second = publishedListing('Ha Ha Ha', 'New York');
    creditOn($second, ['role' => 'Mine']);

    $this->link->link($second, $first, $this->moderator);

    expect($second->credits()->pluck('role')->all())->toBe(['Mine']);
});

test('a pending self-added credit never travels', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    creditOn($first, ['status' => Credit::PENDING]);
    $second = publishedListing('Ha Ha Ha', 'New York');

    $this->link->link($second, $first, $this->moderator);

    expect($second->credits()->count())->toBe(0);
});

test('a copy keeps every field and points at the same artist', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    $row = creditOn($first, [
        'role' => 'Dr. Mirth', 'character' => 'Mirth', 'kind' => 'understudy', 'covers' => 'Lead',
        'credited_as' => 'J. Doe', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01', 'note' => 'Note', 'rank' => 4,
    ]);
    $second = publishedListing('Ha Ha Ha', 'New York');

    $this->link->link($second, $first, $this->moderator);

    $copy = $second->credits()->sole();

    expect($copy->only(['artist_id', 'department', 'role', 'character', 'kind', 'covers', 'credited_as', 'note', 'rank']))
        ->toBe($row->fresh()->only(['artist_id', 'department', 'role', 'character', 'kind', 'covers', 'credited_as', 'note', 'rank']))
        ->and($copy->id)->not->toBe($row->id);
});

test('on unlink the run gets a copy of the writers and the project keeps its own', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    writerOn($first);
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);

    app(UnlinkEvent::class)->handle($second->refresh(), $this->moderator);

    expect($second->credits()->where('department', 'writing')->count())->toBe(1)
        ->and(Project::sole()->writers()->count())->toBe(1);
});

test('on dissolve the run gets a copy of the writers', function () {
    $first = publishedListing('Ha Ha Ha', 'Chicago');
    writerOn($first);
    $second = publishedListing('Ha Ha Ha', 'New York');
    $this->link->link($second, $first, $this->moderator);
    app(UnlinkEvent::class)->handle($first->refresh(), $this->moderator);

    app(DissolveProject::class)->handle(Project::sole(), $this->moderator);

    expect($second->credits()->where('department', 'writing')->count())->toBe(1);
});

test('a duplicate copies no credit rows, and approval copies them', function () {
    $source = publishedListing('Ha Ha Ha', 'Chicago');
    creditOn($source);

    $copy = $source->duplicate();

    expect($copy->credits()->count())->toBe(0);

    $copy->update(['status' => 'p', 'published_at' => now(), 'slug' => 'ha-ha-ha-copy']);
    $this->link->approve(App\Models\ProjectLinkRequest::sole(), $this->moderator);

    expect($copy->credits()->count())->toBe(1);
});

test('writing is pinned: a project holds only writers and a linked run none', function () {
    $project = Project::factory()->create();
    $run = publishedListing('Run', 'Paris');
    $run->update(['project_id' => $project->id, 'instance_slug' => 'paris-2026']);

    expect(fn () => creditOn($project, ['department' => 'cast']))->toThrow(LogicException::class);
    expect(fn () => writerOn($run->fresh()))->toThrow(LogicException::class);

    expect(writerOn($project)->exists)->toBeTrue()
        ->and(creditOn($run->fresh())->exists)->toBeTrue()
        ->and(writerOn(publishedListing('Standalone', 'Paris'))->exists)->toBeTrue();
});

test('departments live in one place and a new one needs no schema change', function () {
    expect(CreditDepartment::values())->toBe(['writing', 'creative', 'cast', 'production']);

    $row = creditOn(publishedListing('Run', 'Paris'), ['department' => 'sound-of-the-future']);

    expect($row->fresh()->department)->toBe('sound-of-the-future');
});

test('one person can hold several rows on a run, and is one artist everywhere', function () {
    $run = publishedListing('Run', 'Paris');
    $artist = Artist::factory()->create();
    creditOn($run, ['artist_id' => $artist->id, 'role' => 'Director', 'department' => 'creative']);
    creditOn($run, ['artist_id' => $artist->id, 'role' => 'Performer']);

    expect($artist->credits()->count())->toBe(2)
        ->and(Artist::count())->toBe(1);
});
