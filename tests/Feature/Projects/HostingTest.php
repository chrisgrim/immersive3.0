<?php

use App\Actions\Projects\LinkEventToProject;
use App\Models\Artist;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function member(Organizer $organizer): User
{
    $user = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    return $user;
}

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->maker = Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $this->project = Project::factory()->create(['organizer_id' => $this->maker->id, 'name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha']);
    $this->run = publishedListing('Ha Ha Ha', 'Chicago');
    $this->run->update(['project_id' => $this->project->id, 'instance_slug' => 'chicago-2026']);
    $this->run = $this->run->fresh();
    $this->keeper = member($this->maker);
    $this->presenter = member($this->run->organizer);
    $this->stranger = member(Organizer::factory()->create());
});

// ----- project page -----

test('the keeper opens the page, and strangers and presenters do not', function () {
    $this->actingAs($this->keeper)->get('/hosting/show-runs/ha-ha-ha/edit')
        ->assertOk()
        ->assertViewIs('projects.edit');

    $this->actingAs($this->stranger)->get('/hosting/show-runs/ha-ha-ha/edit')->assertForbidden();
    $this->actingAs($this->presenter)->get('/hosting/show-runs/ha-ha-ha/edit')->assertForbidden();
    $this->actingAs(staffModerator())->get('/hosting/show-runs/ha-ha-ha/edit')->assertOk();
});

test('a show\'s text cannot reach Vue as a template', function () {
    $this->project->update(['name' => '{{ constructor.constructor("alert(1)")() }}']);

    $html = $this->actingAs($this->keeper)->get('/hosting/show-runs/ha-ha-ha/edit')->getContent();
    $body = preg_replace('#<script\b.*?</script>#s', '', Str::after($html, '<body id="app">'));

    expect($body)->not->toContain('constructor.constructor');
});

test('the keeper edits the page, but not its maker or address', function () {
    $other = Organizer::factory()->create();

    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', [
        'name' => 'Ha Ha Ha!',
        'description' => 'About the show',
        'organizer_id' => $other->id,
        'slug' => 'stolen',
    ])->assertOk()->assertJsonPath('name', 'Ha Ha Ha')->assertJsonPath('pending_name', 'Ha Ha Ha!');

    $project = $this->project->fresh();

    expect($project->description)->toBe('About the show')
        ->and($project->organizer_id)->toBe($this->maker->id)
        ->and($project->slug)->toBe('ha-ha-ha');
});

test('a new main image lands in the project\'s own folder', function () {
    $this->actingAs($this->keeper)->post('/hosting/show-runs/ha-ha-ha/photos', [
        'images' => [UploadedFile::fake()->image('show.jpg', 900, 1300)], 'ranks' => [0],
    ], ['Accept' => 'application/json'])->assertOk();

    expect($this->project->fresh()->largeImagePath)->toStartWith('project-images/ha-ha-ha/')
        ->and($this->project->images()->count())->toBe(1);
});

test('the keeper sets the writers: added, edited, removed', function () {
    $staying = Credit::factory()->writing()->for($this->project, 'creditable')->create(['role' => 'Book']);
    $going = Credit::factory()->writing()->for($this->project, 'creditable')->create();
    $new = Artist::factory()->create();

    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', [
        'writers' => [
            ['artist_id' => $new->id, 'role' => 'Lyrics'],
            ['id' => $staying->id, 'artist_id' => $staying->artist_id, 'role' => 'Book and Lyrics'],
        ],
    ])->assertOk()->assertJsonCount(2, 'writers')->assertJsonPath('writers.0.artist_id', $new->id);

    expect($staying->fresh()->role)->toBe('Book and Lyrics')
        ->and($going->fresh())->toBeNull()
        ->and($this->project->writers()->where('artist_id', $new->id)->sole()->department)->toBe('writing');
});

test('a writer row from somewhere else cannot be pulled in', function () {
    $elsewhere = Credit::factory()->for($this->run, 'creditable')->create();

    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', [
        'writers' => [['id' => $elsewhere->id, 'artist_id' => $elsewhere->artist_id]],
    ])->assertStatus(422);

    expect($elsewhere->fresh()->creditable_id)->toBe($this->run->id);
});

test('leaving writers out of a save leaves them alone', function () {
    $writer = Credit::factory()->writing()->for($this->project, 'creditable')->create();

    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', ['description' => 'x'])->assertOk();

    expect($writer->fresh())->not->toBeNull();
});

test('a merged artist cannot be credited', function () {
    $merged = Artist::factory()->create(['merged_into_id' => Artist::factory()->create()->id]);

    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', ['writers' => [['artist_id' => $merged->id]]])->assertStatus(422);
});

test('the aggregate adds everyone up across runs, derived', function () {
    $artist = Artist::factory()->create(['name' => 'Jo Doe']);
    Credit::factory()->for($this->run, 'creditable')->create(['artist_id' => $artist->id]);
    Credit::factory()->for($this->run, 'creditable')->create(['artist_id' => $artist->id, 'role' => 'Second role']);
    $later = publishedListing('Ha Ha Ha', 'Boston', [], null, '2028-01-01 00:00:00');
    $later->update(['project_id' => $this->project->id, 'instance_slug' => 'boston-2028']);
    Credit::factory()->for($later, 'creditable')->create(['artist_id' => $artist->id]);

    $page = $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', [])->assertOk();

    expect($page->json('aggregate.0'))->toMatchArray(['artist' => 'Jo Doe', 'runs' => 2, 'first_year' => '2026', 'last_year' => '2028']);
});

// ----- link requests -----

test('the keeper sees and approves a request, and the run takes its address', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $this->project->id, 'submitted_at' => now()]);

    $this->actingAs($this->keeper)->getJson('/hosting/show-runs/ha-ha-ha/link-requests')
        ->assertOk()->assertJsonPath('0.event.id', $listing->id);

    $this->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/approve")
        ->assertOk()->assertJsonCount(0, 'link_requests')->assertJsonCount(2, 'runs');

    expect($listing->fresh()->publicPath())->toBe('/events/ha-ha-ha/rome-2026');
});

test('an unsubmitted draft is not on the keeper\'s list', function () {
    $draft = publishedListing('Ha Ha Ha', 'Rome', ['status' => '1']);
    ProjectLinkRequest::create(['event_id' => $draft->id, 'project_id' => $this->project->id]);

    $this->actingAs($this->keeper)->getJson('/hosting/show-runs/ha-ha-ha/link-requests')->assertOk()->assertJsonCount(0);
});

test('the keeper declines a request', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $this->project->id]);

    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/reject", ['note' => 'Not ours'])->assertOk();

    expect($request->fresh()->status)->toBe(ProjectLinkRequest::REJECTED)
        ->and($request->fresh()->decided_by)->toBe($this->keeper->id)
        ->and($listing->fresh()->isLinked())->toBeFalse();
});

test('the presenter hears when a keeper declines, with the reason', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $this->project->id]);

    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/reject", ['note' => 'Not ours'])->assertOk();

    Mail::assertQueued(App\Mail\Comments::class, 1);
    Mail::assertQueued(App\Mail\Comments::class, fn ($mail) => $mail->hasTo($listing->organizer->owner->email)
        && str_contains($mail->attributes['body'], 'will not be listed as part of "Ha Ha Ha"')
        && str_contains($mail->attributes['body'], 'Reason: Not ours'));
    expect(App\Models\Messaging\Message::where('message', 'like', '%will not be listed%')->count())->toBe(1);
});

test('the presenter hears when a keeper approves', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $this->project->id]);

    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/approve")->assertOk();

    Mail::assertQueued(App\Mail\Comments::class, 1);
    Mail::assertQueued(App\Mail\Comments::class, fn ($mail) => $mail->hasTo($listing->organizer->owner->email));
});

test('a moderator\'s decline tells the presenter too', function () {
    $listing = publishedListing('Ha Ha Ha', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => $listing->id, 'project_id' => $this->project->id]);

    $this->actingAs(staffModerator())->postJson("/api/admin/link-requests/{$request->id}/reject", ['note' => 'Different show'])->assertOk();

    Mail::assertQueued(App\Mail\Comments::class, fn ($mail) => $mail->hasTo($listing->organizer->owner->email)
        && str_contains($mail->attributes['body'], 'Reason: Different show'));
});

test('a keeper cannot approve a listing that is not live, nor publish one by approving', function () {
    $draft = publishedListing('Ha Ha Ha', 'Rome', ['status' => 'r']);
    $request = ProjectLinkRequest::create(['event_id' => $draft->id, 'project_id' => $this->project->id]);

    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/approve")->assertStatus(422);

    expect($draft->fresh()->status)->toBe('r')->and($request->fresh()->isPending())->toBeTrue();
});

test('a keeper cannot reach another show\'s request, or anything of a stranger\'s', function () {
    $other = Project::factory()->create(['slug' => 'other-show']);
    $request = ProjectLinkRequest::create(['event_id' => publishedListing('X', 'Rome')->id, 'project_id' => $other->id]);

    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/ha-ha-ha/link-requests/{$request->id}/approve")->assertNotFound();
    $this->actingAs($this->keeper)->postJson("/hosting/show-runs/other-show/link-requests/{$request->id}/approve")->assertForbidden();
    $this->actingAs($this->stranger)->getJson('/hosting/show-runs/ha-ha-ha/link-requests')->assertForbidden();
});

test('a request for a standalone listing is the moderators\', never a team\'s', function () {
    $source = publishedListing('Flushment', 'Rome');
    $request = ProjectLinkRequest::create(['event_id' => publishedListing('Flushment', 'Paris')->id, 'source_event_id' => $source->id]);
    $team = member($source->organizer);

    $this->actingAs($team);

    expect(fn () => app(LinkEventToProject::class)->approve($request, $team))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

// ----- unlink -----

test('the keeper takes a run off the show', function () {
    $this->actingAs($this->keeper)->deleteJson("/hosting/show-runs/ha-ha-ha/instances/{$this->run->slug}")
        ->assertOk()->assertJsonCount(0, 'runs');

    expect($this->run->fresh()->isLinked())->toBeFalse();
});

test('a run of another show answers 404, and a presenter cannot do it', function () {
    $other = publishedListing('Other', 'Rome');

    $this->actingAs($this->keeper)->deleteJson("/hosting/show-runs/ha-ha-ha/instances/{$other->slug}")->assertNotFound();
    $this->actingAs($this->presenter)->deleteJson("/hosting/show-runs/ha-ha-ha/instances/{$this->run->slug}")->assertForbidden();
});

// ----- a listing's own link request -----

test('a team asks to join a show, and takes it back', function () {
    $listing = publishedListing('Rome Run', 'Rome');
    $team = member($listing->organizer);

    $this->actingAs($team)->postJson("/hosting/event/{$listing->slug}/link-request", ['project_id' => $this->project->id, 'note' => 'We are a run of this'])
        ->assertCreated();

    expect(ProjectLinkRequest::sole()->requested_by)->toBe($team->id);

    $this->deleteJson("/hosting/event/{$listing->slug}/link-request")->assertOk();

    expect(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::WITHDRAWN);
});

test('a team can ask to join a standalone published listing, not a hidden one', function () {
    $listing = publishedListing('Rome Run', 'Rome');
    $team = member($listing->organizer);
    $source = publishedListing('Source', 'Paris');
    $hidden = publishedListing('Hidden', 'Paris', ['status' => 'd']);

    $this->actingAs($team)->postJson("/hosting/event/{$listing->slug}/link-request", ['source_event_id' => $hidden->id])->assertStatus(422);
    $this->postJson("/hosting/event/{$listing->slug}/link-request", ['source_event_id' => $source->id])->assertCreated();
});

test('a draft\'s team removes the request its duplicate made', function () {
    $source = publishedListing('Source', 'Rome');
    $team = member($source->organizer);
    $this->actingAs($team);
    $copy = $source->duplicate();

    $this->deleteJson("/hosting/event/{$copy->slug}/link-request")->assertOk();

    expect(ProjectLinkRequest::sole()->status)->toBe(ProjectLinkRequest::WITHDRAWN);
});

test('strangers and held drafts are refused', function () {
    $listing = publishedListing('Rome Run', 'Rome');

    $this->actingAs($this->stranger)->postJson("/hosting/event/{$listing->slug}/link-request", ['project_id' => $this->project->id])->assertForbidden();

    Event::whereKey($listing->id)->update(['status' => 'd', 'held_for_staff' => true]);

    $this->actingAs(member($listing->organizer))->postJson("/hosting/event/{$listing->slug}/link-request", ['project_id' => $this->project->id])->assertForbidden();
});

// ----- a run's credits -----

test('the team reads the panel: its rows, the show\'s writers marked, and pending rows ignored', function () {
    Credit::factory()->for($this->run, 'creditable')->create(['role' => 'Lead']);
    Credit::factory()->for($this->run, 'creditable')->create(['role' => 'Hidden', 'status' => Credit::PENDING]);
    Credit::factory()->writing()->for($this->project, 'creditable')->create(['role' => 'Book']);

    $this->actingAs($this->presenter)->getJson("/hosting/event/{$this->run->slug}/credits")
        ->assertOk()
        ->assertJsonCount(1, 'credits')
        ->assertJsonPath('credits.0.source', 'run')
        ->assertJsonPath('show_writers.0.source', 'show')
        ->assertJsonPath('show_writers.0.role', 'Book');
});

test('the page opens as a page too', function () {
    $this->actingAs($this->presenter)->get("/hosting/event/{$this->run->slug}/credits")
        ->assertOk()->assertViewIs('projects.credits');
});

test('the team adds and edits a row, and a stranger cannot', function () {
    $artist = Artist::factory()->create();

    $response = $this->actingAs($this->presenter)->postJson("/hosting/event/{$this->run->slug}/credits", [
        'artist_id' => $artist->id, 'department' => 'cast', 'role' => 'Lead', 'kind' => 'understudy',
        'character' => 'Mirth', 'covers' => 'Dr. Laugh', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01',
    ])->assertOk()->assertJsonPath('credits.0.kind', 'understudy');

    $id = $response->json('credits.0.id');

    $this->postJson("/hosting/event/{$this->run->slug}/credits", ['id' => $id, 'artist_id' => $artist->id, 'department' => 'cast', 'role' => 'Lead Two'])
        ->assertOk()->assertJsonPath('credits.0.role', 'Lead Two');

    expect($this->run->credits()->count())->toBe(1);

    $this->actingAs($this->stranger)->postJson("/hosting/event/{$this->run->slug}/credits", ['artist_id' => $artist->id, 'department' => 'cast'])->assertForbidden();
});

test('a row cannot be forged as pending, or reach another run\'s row', function () {
    $artist = Artist::factory()->create();
    $elsewhere = Credit::factory()->for(publishedListing('Else', 'Rome'), 'creditable')->create();

    $this->actingAs($this->presenter)->postJson("/hosting/event/{$this->run->slug}/credits", ['artist_id' => $artist->id, 'department' => 'cast', 'status' => 'pending'])->assertOk();
    expect($this->run->credits()->sole()->status)->toBe(Credit::APPROVED);

    $this->postJson("/hosting/event/{$this->run->slug}/credits", ['id' => $elsewhere->id, 'artist_id' => $artist->id, 'department' => 'cast'])->assertStatus(422);
    $this->deleteJson("/hosting/event/{$this->run->slug}/credits/{$elsewhere->id}")->assertStatus(422);
});

test('a linked run takes no writers, and an unknown department is refused', function () {
    $artist = Artist::factory()->create();

    $this->actingAs($this->presenter)->postJson("/hosting/event/{$this->run->slug}/credits", ['artist_id' => $artist->id, 'department' => 'writing'])->assertForbidden();
    $this->postJson("/hosting/event/{$this->run->slug}/credits", ['artist_id' => $artist->id, 'department' => 'catering'])->assertStatus(422);
});

test('a listing in review or past its lock is closed to its team, as today', function () {
    $artist = Artist::factory()->create();
    $inReview = publishedListing('Review', 'Rome', ['status' => 'r']);
    $locked = publishedListing('Locked', 'Rome', ['closingDate' => now()->subDays(200)]);

    foreach ([$inReview, $locked] as $listing) {
        $this->actingAs(member($listing->organizer))
            ->postJson("/hosting/event/{$listing->slug}/credits", ['artist_id' => $artist->id, 'department' => 'cast'])
            ->assertForbidden();
    }

    $this->actingAs(staffModerator())->postJson("/hosting/event/{$locked->slug}/credits", ['artist_id' => $artist->id, 'department' => 'cast'])->assertOk();
});

test('a curator, on no team, adds cast to any run and nothing else', function () {
    $curator = User::factory()->create(['type' => 'c']);
    $artist = Artist::factory()->create();
    $locked = publishedListing('Locked', 'Rome', ['closingDate' => now()->subDays(200)]);
    $crew = Credit::factory()->for($locked, 'creditable')->create(['department' => 'production']);

    $this->actingAs($curator);

    $this->getJson("/hosting/event/{$locked->slug}/credits")->assertOk()
        ->assertJsonPath('editable_departments', ['cast'])
        ->assertJsonPath('special_thanks', null)
        ->assertJsonPath('can_edit_thanks', false);

    $this->postJson("/hosting/event/{$locked->slug}/credits", ['artist_id' => $artist->id, 'department' => 'cast'])->assertOk();
    $this->postJson("/hosting/event/{$locked->slug}/credits", ['artist_id' => $artist->id, 'department' => 'creative'])->assertForbidden();

    // Not by moving a crew row to cast, and not by removing one.
    $this->postJson("/hosting/event/{$locked->slug}/credits", ['id' => $crew->id, 'artist_id' => $artist->id, 'department' => 'cast'])->assertForbidden();
    $this->deleteJson("/hosting/event/{$locked->slug}/credits/{$crew->id}")->assertForbidden();
    $this->postJson("/hosting/event/{$locked->slug}/credits", ['special_thanks' => 'Me'])->assertForbidden();

    expect($crew->fresh()->department)->toBe('production');
});

test('the team removes a row', function () {
    $credit = Credit::factory()->for($this->run, 'creditable')->create();

    $this->actingAs($this->presenter)->deleteJson("/hosting/event/{$this->run->slug}/credits/{$credit->id}")->assertOk()->assertJsonCount(0, 'credits');
});

test('Special Thanks is the team\'s to keep, and stays out of the public page', function () {
    $this->actingAs($this->presenter)->postJson("/hosting/event/{$this->run->slug}/credits", ['special_thanks' => "A Backer\nA Friend"])
        ->assertOk()->assertJsonPath('special_thanks', "A Backer\nA Friend");

    $this->get($this->run->publicPath())->assertOk()->assertDontSee('A Backer');
});

test('a team copies from another run of the same show, never from a stranger\'s list', function () {
    $other = publishedListing('Ha Ha Ha', 'Boston');
    $other->update(['project_id' => $this->project->id, 'instance_slug' => 'boston-2026']);
    Credit::factory()->for($other, 'creditable')->create(['role' => 'Lead']);
    $stranger = publishedListing('Else', 'Rome');
    Credit::factory()->for($stranger, 'creditable')->create();

    $this->actingAs($this->presenter);

    $this->postJson("/hosting/event/{$this->run->slug}/credits/copy", ['from_event_id' => $stranger->id])->assertStatus(422);
    $this->postJson("/hosting/event/{$this->run->slug}/credits/copy", ['from_event_id' => $other->id])
        ->assertOk()->assertJsonPath('added', 1);
});

test('keeping the page does not let a company copy onto someone else\'s run', function () {
    $other = publishedListing('Ha Ha Ha', 'Boston');
    $other->update(['project_id' => $this->project->id, 'instance_slug' => 'boston-2026']);

    $this->actingAs($this->keeper)->postJson("/hosting/event/{$this->run->slug}/credits/copy", ['from_event_id' => $other->id])->assertForbidden();
});

// ----- the picker -----

test('a team member searches and proposes artists; merged ones are not offered', function () {
    Artist::factory()->create(['name' => 'Jo Doe']);
    // Named: a random winner can be a Jordan or a Major, and "Jo" finds it.
    Artist::factory()->create(['name' => 'Jo Gone', 'merged_into_id' => Artist::factory()->create(['name' => 'Someone Else'])->id]);

    $this->actingAs($this->presenter)->getJson('/api/artists/search?keywords=Jo')
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Jo Doe');

    $this->postJson('/api/artists', ['name' => 'New Person'])->assertCreated()->assertJsonPath('artist.status', Artist::PROPOSED);
});

test('someone on no team cannot, a curator can', function () {
    $this->actingAs(User::factory()->create(['type' => 'u']))->getJson('/api/artists/search?keywords=Jo')->assertForbidden();
    $this->actingAs(User::factory()->create(['type' => 'u']))->postJson('/api/artists', ['name' => 'X'])->assertForbidden();
    $this->actingAs(User::factory()->create(['type' => 'c']))->postJson('/api/artists', ['name' => 'X'])->assertCreated();
});

test('the keeper sees the edit button on the show page, others do not', function () {
    $this->actingAs($this->keeper)->get('/events/ha-ha-ha')->assertSee('Edit this show run');
    $this->actingAs($this->stranger)->get('/events/ha-ha-ha')->assertDontSee('Edit this show run');
    auth()->logout();
    $this->get('/events/ha-ha-ha')->assertDontSee('Edit this show run');
});

test('the keeper keeps one photo, which leads the page', function () {
    $file = fn () => UploadedFile::fake()->image('show.jpg', 900, 1200);

    $this->actingAs($this->keeper)->post('/hosting/show-runs/ha-ha-ha/photos', [
        'images' => [$file()], 'ranks' => [0],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'photos');

    $photo = $this->project->images()->sole();
    expect($this->project->fresh()->largeImagePath)->toBe($photo->large_image_path);

    // A second one is refused while the first is kept.
    $this->actingAs($this->keeper)->post('/hosting/show-runs/ha-ha-ha/photos', [
        'images' => [$file()], 'ranks' => [0], 'currentImages' => json_encode([['id' => $photo->id, 'rank' => 0]]),
    ], ['Accept' => 'application/json'])->assertUnprocessable();

    $this->actingAs($this->stranger)->post('/hosting/show-runs/ha-ha-ha/photos', ['deletedImages' => '[]'], ['Accept' => 'application/json'])->assertForbidden();
});

test('an organizer page opens its own listings: a member looks and may switch by POST, staff look without joining', function () {
    $other = \App\Models\Organizer::factory()->create(['status' => 'p', 'name' => 'Other Co']);
    $member = User::factory()->create(['type' => 'u']);
    $home = \App\Models\Organizer::factory()->create(['status' => 'p']);
    $home->users()->attach($member->id, ['role' => 'member']);
    $other->users()->attach($member->id, ['role' => 'member']);
    $member->forceFill(['current_team_id' => $home->id])->save();

    // Looking changes nothing; the switch is its own POST.
    $this->actingAs($member)->get("/hosting/events?organizer={$other->slug}")->assertOk()
        ->assertSee('It is not your active organization')
        ->assertViewHas('organizer', fn ($organizer) => $organizer->id === $other->id);
    expect($member->fresh()->current_team_id)->toBe($home->id);
    $this->actingAs($member)->get("/hosting/events?organizer={$home->slug}")->assertRedirect(route('hosting.dashboard'));
    $this->actingAs($member)->post("/teams/switch/{$other->slug}")->assertRedirect();
    expect($member->fresh()->current_team_id)->toBe($other->id);

    $moderator = User::factory()->create(['type' => 'm']);
    $this->actingAs($moderator)->get("/hosting/events?organizer={$other->slug}")->assertOk()
        ->assertSee('Viewing Other Co as staff')
        ->assertViewHas('organizer', fn ($organizer) => $organizer->id === $other->id);
    expect($moderator->fresh()->current_team_id)->toBeNull()
        ->and($other->users()->whereKey($moderator->id)->exists())->toBeFalse();

    $stranger = User::factory()->create(['type' => 'u']);
    $home->users()->attach($stranger->id, ['role' => 'member']);
    $this->actingAs($stranger)->get("/hosting/events?organizer={$other->slug}")->assertForbidden();
});

test('an older event with only its picture path gets that picture as its main image when edited', function () {
    $event = \App\Models\Event::factory()->create(['largeImagePath' => 'event-images/old/old.webp', 'thumbImagePath' => 'event-images/old/old-thumb.webp']);
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)->get("/hosting/event/{$event->slug}/edit")->assertOk();
    $this->actingAs($moderator)->get("/hosting/event/{$event->slug}/edit")->assertOk();

    $image = $event->images()->sole();
    expect($image->large_image_path)->toBe('event-images/old/old.webp')
        ->and($image->rank)->toBe(0);

    // An old event with no thumb borrows the large picture rather than failing.
    $thumbless = \App\Models\Event::factory()->create(['largeImagePath' => 'event-images/older/older.webp', 'thumbImagePath' => null]);
    $this->actingAs($moderator)->get("/hosting/event/{$thumbless->slug}/edit")->assertOk();
    expect($thumbless->images()->sole()->thumb_image_path)->toBe('event-images/older/older.webp');
});

test('the event editor knows its show run, and whether this user may take it out (Settings)', function () {
    $runOf = fn (User $user) => $this->actingAs($user)->get("/hosting/event/{$this->run->slug}/edit")->assertOk()->viewData('event')->run_of;

    // The run's own company: part of it, but the show is someone else's to change.
    expect($runOf($this->presenter))->toMatchArray(['id' => $this->project->id, 'name' => 'Ha Ha Ha', 'slug' => 'ha-ha-ha', 'kept' => false]);

    // Staff may take it out.
    expect($runOf(staffModerator())['kept'])->toBeTrue();

    // Not in a show run: nothing.
    $this->run->update(['project_id' => null]);
    expect($runOf($this->presenter))->toBeNull();
});

it('the old show run address still opens the page', function () {
    $this->actingAs($this->keeper)->get('/hosting/projects/ha-ha-ha/edit')->assertRedirect('/hosting/show-runs/ha-ha-ha/edit');
});

test('a new name from the keeper waits for EI; approved, it keeps its address', function () {
    Illuminate\Support\Facades\Mail::fake();
    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', ['name' => 'Spam Spam'])->assertOk();
    // Changed again before EI looks: still one request, with the newest name.
    $this->postJson('/hosting/show-runs/ha-ha-ha', ['name' => 'Ha Ha Ha Again'])->assertOk()->assertJsonPath('pending_name', 'Ha Ha Ha Again');

    expect($this->project->fresh()->name)->toBe('Ha Ha Ha');
    $request = App\Models\NameChangeRequest::sole();
    expect($request->requested_name)->toBe('Ha Ha Ha Again');

    $this->actingAs(staffModerator())->postJson("/api/admin/approve/requests/{$request->id}/approve");
    $project = $this->project->fresh();
    expect($project->name)->toBe('Ha Ha Ha Again')->and($project->slug)->toBe('ha-ha-ha');
});

test('EI staff rename a show run straight away', function () {
    $this->actingAs(staffModerator())->postJson('/hosting/show-runs/ha-ha-ha', ['name' => 'Staff Name'])->assertOk()->assertJsonPath('name', 'Staff Name');
    expect(App\Models\NameChangeRequest::count())->toBe(0);
});

test('staff saving other fields leave a company rename waiting', function () {
    Illuminate\Support\Facades\Mail::fake();
    $this->actingAs($this->keeper)->postJson('/hosting/show-runs/ha-ha-ha', ['name' => 'Ha Ha Ha 2027'])->assertOk();

    $this->actingAs(staffModerator())->postJson('/hosting/show-runs/ha-ha-ha', ['name' => 'Ha Ha Ha', 'description' => 'Typo fixed'])->assertOk();

    expect(App\Models\NameChangeRequest::sole()->status)->toBe('pending');
});
