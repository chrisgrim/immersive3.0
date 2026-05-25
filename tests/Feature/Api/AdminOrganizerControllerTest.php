<?php

use App\Mail\Comments;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- getPending() -----

test('getPending returns only organizers with status r', function () {
    Organizer::factory()->count(2)->create(['status' => 'r']);
    Organizer::factory()->count(3)->create(['status' => 'p']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/organizers')
        ->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('getPending is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/approve/organizers')->assertStatus(403);
});

// ----- approve() -----

test('approve flips an in-review organizer to published and emails the owner', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertOk();

    expect($organizer->fresh()->status)->toBe('p');
    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('approve does not email when moderator approves their own organizer', function () {
    $organizer = Organizer::factory()->create(['user_id' => $this->moderator->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertOk();

    Mail::assertNothingSent();
});

test('approve is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/approve")
        ->assertStatus(403);

    expect($organizer->fresh()->status)->toBe('r');
});

// ----- reject() -----

test('reject sets status to n and emails the owner with the reason', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [
            'reason' => 'Name conflicts with an existing org',
        ])
        ->assertOk()
        ->assertJsonPath('organizer.status', 'n');

    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('reject requires a reason', function () {
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('reject does not email when moderator rejects their own organizer', function () {
    $organizer = Organizer::factory()->create(['user_id' => $this->moderator->id, 'status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", [
            'reason' => 'changed my mind',
        ])
        ->assertOk();

    Mail::assertNothingSent();
});

test('reject is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/organizers/{$organizer->slug}/reject", ['reason' => 'no'])
        ->assertStatus(403);
});
