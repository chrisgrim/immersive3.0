<?php

use App\Mail\NameChangeNotification;
use App\Models\Admin\ReviewEvent;
use App\Models\Event;
use App\Models\NameChangeRequest;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

/*
|--------------------------------------------------------------------------
| StaffPick model
|--------------------------------------------------------------------------
| The half-built picks controller (AdminPicksController) referenced a
| non-existent PickOfTheWeek model and had no route; it has been removed.
| The live StaffPick model — used for the staff-pick badge on events —
| is exercised directly below.
*/

// StaffPick works against its real schema (event_id/user_id/rank/dates/comments;
// there is no admin_id or featured_until column, which is what the removed
// controller incorrectly tried to write).
test('StaffPick can be created against its real schema', function () {
    $event = Event::factory()->create();
    $pick = App\Models\Admin\StaffPick::create([
        'event_id' => $event->id,
        'user_id' => $this->moderator->id,
        'rank' => 5,
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'comments' => 'great show',
    ]);

    $this->assertDatabaseHas('staff_picks', ['id' => $pick->id, 'event_id' => $event->id]);
    // note: the staff_picks table has no admin_id or featured_until columns (the removed
    // picks controller wrongly assumed those existed).
    expect(\Illuminate\Support\Facades\Schema::hasColumn('staff_picks', 'admin_id'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('staff_picks', 'featured_until'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| AdminReviewController
|--------------------------------------------------------------------------
*/

test('reviews index returns a paginated list with event and user', function () {
    ReviewEvent::factory()->count(3)->create();

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/manage/reviews')
        ->assertOk();

    expect($response->json('total'))->toBe(3);
    expect($response->json('data.0'))->toHaveKeys(['event', 'user']);
});

test('reviews index filters by search term on event name', function () {
    $matching = Event::factory()->create(['name' => 'Haunted Mansion Experience']);
    $other = Event::factory()->create(['name' => 'Quiet Garden Tour']);
    ReviewEvent::factory()->create(['event_id' => $matching->id]);
    ReviewEvent::factory()->create(['event_id' => $other->id]);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/manage/reviews?search=Haunted')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('reviews index is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/manage/reviews')->assertStatus(403);
});

test('reviews store creates a review for an event', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);

    $response = $this->actingAs($this->moderator)
        ->postJson('/api/admin/manage/reviews', [
            'event' => ['id' => $event->id, 'organizer_id' => $organizer->id],
            'reviewername' => 'Jane Critic',
            'url' => 'https://example.com/review',
            'review' => 'A wonderful immersive experience.',
            'rank' => 4,
        ])
        ->assertOk();

    expect($response->json('reviewer_name'))->toBe('Jane Critic');
    $this->assertDatabaseHas('review_events', [
        'event_id' => $event->id,
        'reviewer_name' => 'Jane Critic',
        'rank' => 4,
        'user_id' => $this->moderator->id,
        'organizer_id' => $organizer->id,
    ]);
});

test('reviews store validates the event exists, the url and the rank range', function () {
    $this->actingAs($this->moderator)
        ->postJson('/api/admin/manage/reviews', [
            'event' => ['id' => 99999],
            'reviewername' => 'x',
            'url' => 'not-a-url',
            'review' => 'y',
            'rank' => 9,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['event.id', 'url', 'rank']);
});

test('reviews store is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/admin/manage/reviews', [
            'event' => ['id' => $event->id],
            'reviewername' => 'x',
            'url' => 'https://example.com',
            'review' => 'y',
            'rank' => 3,
        ])
        ->assertStatus(403);
});

test('reviews update modifies a review with valid data', function () {
    $review = ReviewEvent::factory()->create(['rank' => 2]);

    $response = $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/reviews/{$review->id}", [
            'reviewer_name' => 'Updated Name',
            'url' => 'https://example.com/updated',
            'review' => 'Revised opinion.',
            'rank' => 5,
        ])
        ->assertOk();

    expect($response->json('reviewer_name'))->toBe('Updated Name');
    expect($review->fresh()->rank)->toBe(5);
});

test('reviews update validates rank between 1 and 5 and a valid url', function () {
    $review = ReviewEvent::factory()->create();

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/reviews/{$review->id}", [
            'reviewer_name' => 'x',
            'url' => 'nope',
            'review' => 'y',
            'rank' => 6,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url', 'rank']);
});

test('reviews destroy removes the review and returns 204', function () {
    $review = ReviewEvent::factory()->create();

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/reviews/{$review->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('review_events', ['id' => $review->id]);
});

test('reviews destroy is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $review = ReviewEvent::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/admin/manage/reviews/{$review->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('review_events', ['id' => $review->id]);
});

/*
|--------------------------------------------------------------------------
| AdminRequestsController (NameChangeRequest)
|--------------------------------------------------------------------------
*/

test('requests index returns only pending requests with polymorphic type', function () {
    $organizer = Organizer::factory()->create();
    NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create(['status' => 'pending']);
    NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create(['status' => 'approved']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/requests')
        ->assertOk();

    expect($response->json('requests'))->toHaveCount(1);
    expect($response->json('requests.0.type'))->toBe('Organizer');
    expect($response->json('requests.0'))->toHaveKeys(['current_name', 'requested_name', 'reason', 'status', 'user']);
});

test('requests index is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/approve/requests')->assertStatus(403);
});

test('requests approve applies the name change to the model and marks it approved', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'Old Org Name']);
    $request = NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create([
            'status' => 'pending',
            'current_name' => 'Old Org Name',
            'requested_name' => 'Brand New Org',
            'user_id' => $owner->id,
        ]);

    $response = $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/requests/{$request->id}/approve")
        ->assertOk()
        ->assertJsonPath('message', 'Name change request approved successfully');

    expect($request->fresh()->status)->toBe('approved');
    expect($organizer->fresh()->name)->toBe('Brand New Org');
    expect($organizer->fresh()->slug)->toBe('brand-new-org');
    // processAdminDirectChange reports requiresRefresh true when the slug changed.
    expect($response->json('requiresRefresh'))->toBeTrue();
    // The service emails the model owner about the applied change.
    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('requests approve is denied to non-moderators', function () {
    $organizer = Organizer::factory()->create(['name' => 'Keep This']);
    $request = NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create(['status' => 'pending', 'requested_name' => 'Hacked Name']);

    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson("/api/admin/approve/requests/{$request->id}/approve")
        ->assertStatus(403);

    expect($request->fresh()->status)->toBe('pending');
    expect($organizer->fresh()->name)->toBe('Keep This');
});

test('requests reject marks the request rejected, stores the reason and emails the user', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'name' => 'Unchanged Org']);
    $request = NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create(['status' => 'pending', 'user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/requests/{$request->id}/reject", [
            'reason' => 'Name too similar to an existing organizer',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Name change request rejected successfully');

    $fresh = $request->fresh();
    expect($fresh->status)->toBe('rejected');
    expect($fresh->reason)->toBe('Name too similar to an existing organizer');
    // The model's name must NOT have changed on rejection.
    expect($organizer->fresh()->name)->toBe('Unchanged Org');

    Mail::assertSent(NameChangeNotification::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('requests reject is denied to non-moderators', function () {
    $organizer = Organizer::factory()->create();
    $request = NameChangeRequest::factory()
        ->for($organizer, 'requestable')
        ->create(['status' => 'pending']);

    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)
        ->postJson("/api/admin/approve/requests/{$request->id}/reject", ['reason' => 'no'])
        ->assertStatus(403);

    expect($request->fresh()->status)->toBe('pending');
});
