<?php

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Events\Location;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

/** An event in the queue at $venue, its location secret when asked. */
function queuedAt(Venue $venue, bool $secret = false): Event
{
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);
    $event = Event::factory()->inReview()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    Location::updateOrCreate(['event_id' => $event->id], ['venue_id' => $venue->id, 'city' => 'Boston', 'hiddenLocationToggle' => $secret]);

    return $event->fresh();
}

test('approving an event approves the venue waiting with it', function () {
    $venue = Venue::factory()->create(['status' => Venue::PENDING]);
    $event = queuedAt($venue);

    $this->actingAs($this->moderator)->postJson("/api/admin/approve/events/{$event->slug}/approve")->assertOk();

    expect($venue->fresh()->status)->toBe(Venue::APPROVED);
    expect(AuditLog::where('action', 'venue.approved')->where('subject_id', $venue->id)->exists())->toBeTrue();
});

test('a draft, merged or secret-location venue is left as it is', function (string $status, bool $merged, bool $secret) {
    $venue = Venue::factory()->create(['status' => $status]);
    if ($merged) {
        $venue->update(['merged_into_id' => Venue::factory()->create()->id]);
    }
    $event = queuedAt($venue, $secret);

    $this->actingAs($this->moderator)->postJson("/api/admin/approve/events/{$event->slug}/approve")->assertOk();

    expect($venue->fresh()->status)->toBe($status);
})->with([
    'draft' => [Venue::DRAFT, false, false],
    'merged' => [Venue::PENDING, true, false],
    'secret location' => [Venue::PENDING, false, true],
]);

test('the review page shows the venue and says when it is new', function () {
    $venue = Venue::factory()->create(['status' => Venue::PENDING, 'name' => 'The Old Mill']);
    $event = queuedAt($venue);

    $this->actingAs($this->moderator)->getJson("/api/admin/events/{$event->slug}")
        ->assertOk()
        ->assertJsonPath('reviewVenue.name', 'The Old Mill')
        ->assertJsonPath('reviewVenue.new', true)
        ->assertJsonPath('reviewVenue.secret', false);
});
