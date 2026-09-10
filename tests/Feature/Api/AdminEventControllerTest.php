<?php

use App\Mail\Comments;
use App\Models\Event;
use App\Models\Messaging\Message;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- getPending() -----

test('getPending returns only events with status r', function () {
    Event::factory()->count(3)->inReview()->create();
    Event::factory()->count(2)->create(['status' => 'd']);
    Event::factory()->count(2)->published()->create();

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/events')
        ->assertOk();

    expect($response->json('total'))->toBe(3);
    expect($response->json('data'))->toHaveCount(3);
});

test('getPending requires moderator', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/approve/events')->assertStatus(403);
});

// ----- approve() -----

test('approve flips an in-review event to published and notifies the owner', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'r']);
    $event = Event::factory()->inReview()->create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'p');

    $event->refresh();
    expect($event->status)->toBe('p');
    expect($event->published_at)->not->toBeNull();
    expect($event->organizer->fresh()->status)->toBe('p');
    expect($event->curatedCheck()->exists())->toBeTrue();

    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
    expect(Message::where('conversation_id', '!=', null)->where('user_id', $this->moderator->id)->exists())->toBeTrue();
});

test('approve uses embargo status when embargo_date is in the future', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->inReview()->create([
        'user_id' => $owner->id,
        'embargo_date' => now()->addDays(7),
    ]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'e');

    expect($event->fresh()->status)->toBe('e');
});

test('approve does not email when moderator approves their own event', function () {
    $event = Event::factory()->inReview()->create(['user_id' => $this->moderator->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertOk();

    Mail::assertNothingSent();
});

test('approve is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->inReview()->create();

    $this->actingAs($user)
        ->postJson("/api/admin/approve/events/{$event->slug}/approve")
        ->assertStatus(403);

    expect($event->fresh()->status)->toBe('r');
});

// ----- reject() -----

test('reject sets status to n and emails the owner with the reason', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->inReview()->create(['user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/reject", [
            'reason' => 'Title is misleading',
        ])
        ->assertOk();

    expect($event->fresh()->status)->toBe('n');
    // Note: `rejection_reason` is not an events column nor in $fillable, so
    // the controller's $event->update(['rejection_reason' => ...]) silently
    // drops it. The reason lives in the email + in-app message only.

    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('reject requires a reason', function () {
    $event = Event::factory()->inReview()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    expect($event->fresh()->status)->toBe('r');
});

test('reject reason has a 1000 character cap', function () {
    $event = Event::factory()->inReview()->create();

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/reject", [
            'reason' => str_repeat('x', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('reject does not email when moderator rejects their own event', function () {
    $event = Event::factory()->inReview()->create(['user_id' => $this->moderator->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/events/{$event->slug}/reject", [
            'reason' => 'changed my mind',
        ])
        ->assertOk();

    Mail::assertNothingSent();
});

test('reject is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->inReview()->create();

    $this->actingAs($user)
        ->postJson("/api/admin/approve/events/{$event->slug}/reject", ['reason' => 'no'])
        ->assertStatus(403);
});

// ----- show() -----

test('show includes the account that created the event, name and email only', function () {
    $creator = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $event = Event::factory()->inReview()->create(['user_id' => $creator->id]);

    $response = $this->actingAs($this->moderator)
        ->getJson("/api/admin/events/{$event->slug}")
        ->assertOk();

    expect($response->json('user.id'))->toBe($creator->id);
    expect($response->json('user.name'))->toBe('Ada Lovelace');
    expect($response->json('user.email'))->toBe('ada@example.com');
    expect($response->json('user'))->not->toHaveKey('password');
    expect($response->json('user'))->not->toHaveKey('phone');
});

test('show still works when the creating account is gone', function () {
    // users has no FK from events and no soft deletes, so a deleted account
    // leaves a dangling user_id behind.
    $event = Event::factory()->inReview()->create(['user_id' => 999999]);

    $response = $this->actingAs($this->moderator)
        ->getJson("/api/admin/events/{$event->slug}")
        ->assertOk();

    expect($response->json('user'))->toBeNull();
});
