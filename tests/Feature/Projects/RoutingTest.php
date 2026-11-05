<?php

use App\Actions\Projects\LinkEventToProject;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Models\Venue;
use App\Services\NameChangeRequestService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    Mail::fake();
    $this->moderator = staffModerator();
    $this->first = publishedListing('Ha Ha Ha', 'Chicago');
    $this->second = publishedListing('Ha Ha Ha', 'New York');
    $this->actingAs($this->moderator);
    app(LinkEventToProject::class)->link($this->second, $this->first, $this->moderator);
    $this->first->refresh();
    $this->second->refresh();
    auth()->logout();
});

test('an unlinked listing behaves exactly as today', function () {
    $plain = publishedListing('Plain', 'Paris');

    $this->get('/events/plain')->assertOk()->assertViewIs('events.show');
    $this->get('/events/plain/anything')->assertNotFound();
    $this->get('/events/nothing-here')->assertNotFound();
    expect($plain->fresh()->publicPath())->toBe('/events/plain');
});

test('an unpublished plain listing 404s by slug, as today', function () {
    foreach (['d', '0', 'r', 'n', 'e'] as $status) {
        $event = publishedListing('Hidden '.$status, 'Paris', ['status' => $status]);
        $this->get('/events/'.$event->slug)->assertNotFound();
    }
});

test('a project with a published run answers at its address', function () {
    // The project page comes in a later stage; until then the address leads to a run.
    $this->get('/events/ha-ha-ha')->assertRedirect()->assertStatus(302);
});

test('a project with no published run is a 404', function () {
    Event::whereIn('id', [$this->first->id, $this->second->id])->update(['status' => 'n']);

    $this->get('/events/ha-ha-ha')->assertNotFound();
});

test('a project is found before an event with the same slug', function () {
    expect($this->first->slug)->toBe('ha-ha-ha');

    $this->get('/events/ha-ha-ha')->assertStatus(302);
});

test('a linked run reached by its own slug moves to its address under the show', function () {
    SlugRedirect::where('path', 'events/ha-ha-ha-new-york')->delete();

    $this->get('/events/ha-ha-ha-new-york')->assertRedirect('/events/ha-ha-ha/new-york-2026')->assertStatus(301);
});

test('a linked run that is not published 404s at its slug and tells nothing about its address', function () {
    foreach (['e', 'r', 'n', 'd'] as $status) {
        Event::whereKey($this->second->id)->update(['status' => $status]);

        $this->get('/events/ha-ha-ha-new-york')->assertNotFound();
        $this->get('/events/ha-ha-ha/new-york-2026')->assertNotFound();
    }
});

test('with the row gone an unpublished run still tells nothing', function () {
    SlugRedirect::where('path', 'events/ha-ha-ha-new-york')->delete();
    Event::whereKey($this->second->id)->update(['status' => 'e']);

    $this->get('/events/ha-ha-ha-new-york')->assertNotFound();
});

test('a published run renders the same page as a listing, with the address under the show as canonical', function () {
    $response = $this->get('/events/ha-ha-ha/new-york-2026')->assertOk()->assertViewIs('events.show');

    expect($response->viewData('event')->id)->toBe($this->second->id);
    $response->assertSee('<link rel="canonical" href="'.url('/events/ha-ha-ha/new-york-2026').'"', false);
});

test('a plain listing\'s canonical does not change', function () {
    publishedListing('Plain', 'Paris');

    $this->get('/events/plain')->assertSee('<link rel="canonical" href="'.url('/events/plain').'"', false);
});

test('a mistyped run address goes to the show', function () {
    $this->get('/events/ha-ha-ha/new-yrok-2026')->assertRedirect('/events/ha-ha-ha')->assertStatus(302);
});

test('a two-segment address under nothing is a 404', function () {
    $this->get('/events/no-such-show/chicago-2026')->assertNotFound();
});

test('a redirect keeps the query string', function () {
    $this->get('/events/ha-ha-ha-new-york?utm_source=mail')->assertRedirect('/events/ha-ha-ha/new-york-2026?utm_source=mail');
});

test('a released address never resolves', function () {
    $this->get('/events/deleted--1')->assertNotFound();
    $this->second->delete();
    $this->get('/events/deleted--'.$this->second->id)->assertNotFound();
});

test('a redirect whose record is gone falls through to a 404', function () {
    SlugRedirect::create(['path' => 'events/ghost', 'redirectable_type' => Event::class, 'redirectable_id' => 999999]);

    $this->get('/events/ghost')->assertNotFound();
});

test('no redirect row chain can loop: each row ends at a live page after one hop', function () {
    app(NameChangeRequestService::class)->processAdminDirectChange(publishedListing('One', 'Paris'), 'Two');
    app(NameChangeRequestService::class)->processAdminDirectChange(Event::where('slug', 'two')->first(), 'Three');

    $this->get('/events/one')->assertRedirect('/events/three');
    $this->get('/events/two')->assertRedirect('/events/three');
    $this->get('/events/three')->assertOk();
});

// ----- organizers -----

test('a renamed organizer\'s old address redirects, and an unknown one still 404s', function () {
    $organizer = Organizer::factory()->create(['name' => 'Old Company', 'status' => 'p']);
    $old = $organizer->slug;

    $organizer->update(['name' => 'New Company']);

    expect($organizer->slug)->toBe('new-company');
    $this->get('/organizers/'.$old)->assertRedirect('/organizers/new-company')->assertStatus(301);
    $this->get('/organizers/new-company')->assertOk();
    $this->get('/organizers/never-was')->assertNotFound();
});

test('an organizer renamed back loses the alias', function () {
    $organizer = Organizer::factory()->create(['name' => 'First Company', 'status' => 'p']);

    $organizer->update(['name' => 'Second Company']);
    $organizer->update(['name' => 'First Company']);

    expect($organizer->slug)->toBe('first-company')
        ->and(SlugRedirect::where('path', 'organizers/first-company')->exists())->toBeFalse()
        ->and(SlugRedirect::where('path', 'organizers/second-company')->exists())->toBeTrue();
    $this->get('/organizers/first-company')->assertOk();
});

test('an event path and an organizer path of the same name do not interfere', function () {
    $organizer = Organizer::factory()->create(['name' => 'Same Name', 'status' => 'p']);
    $organizer->update(['name' => 'Other Name']);

    $this->get('/events/same-name')->assertNotFound();
});

// ----- venues -----

test('a renamed venue keeps its old address reserved', function () {
    $venue = Venue::factory()->create(['name' => 'The Old Hall']);
    $this->actingAs($this->moderator);

    $venue->update(['slug' => Venue::uniqueSlug('The New Hall', $venue->id)]);

    expect(SlugRedirect::where('path', 'venues/the-old-hall')->sole()->destination())->toBe(['/venues/the-new-hall', 301])
        ->and(Venue::uniqueSlug('The Old Hall'))->toBe('the-old-hall-2')
        ->and(Venue::uniqueSlug('The Old Hall', $venue->id))->toBe('the-old-hall');
});

test('a project whose every run is gone stays out of sight until one is linked', function () {
    $project = Project::sole();
    $this->first->delete();
    $this->second->delete();

    $this->get('/events/ha-ha-ha')->assertNotFound();
    expect(Project::find($project->id))->not->toBeNull();
});
