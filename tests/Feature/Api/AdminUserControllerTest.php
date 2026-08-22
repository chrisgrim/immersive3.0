<?php

use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * Regression coverage for CR2: moderators must not be able to escalate their
 * own (or anyone else's) `type` to admin via PATCH /admin/manage/users/{user}.
 * Admins are allowed to change others' roles but never their own.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['type' => 'a']);
    $this->moderator = User::factory()->create(['type' => 'm']);
    $this->user = User::factory()->create(['type' => 'u']);
});

test('moderator cannot promote another user to admin (CR2)', function () {
    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/users/{$this->user->id}", ['type' => 'a'])
        ->assertStatus(403);

    expect($this->user->fresh()->type)->toBe('u');
});

test('moderator cannot promote themselves to admin (CR2)', function () {
    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/users/{$this->moderator->id}", ['type' => 'a'])
        ->assertStatus(403);

    expect($this->moderator->fresh()->type)->toBe('m');
});

test('moderator can still update non-type fields on other users', function () {
    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/users/{$this->user->id}", ['name' => 'Renamed User'])
        ->assertOk();

    expect($this->user->fresh()->name)->toBe('Renamed User');
});

test('admin can promote another user to moderator', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/admin/manage/users/{$this->user->id}", ['type' => 'm'])
        ->assertOk();

    expect($this->user->fresh()->type)->toBe('m');
});

test('admin cannot change their own type (prevent lockout)', function () {
    // 'm' is a valid demotion target per the validation rule. The self-edit
    // guard refuses even valid demotions of the caller's own role.
    $this->actingAs($this->admin)
        ->patchJson("/api/admin/manage/users/{$this->admin->id}", ['type' => 'm'])
        ->assertStatus(403);

    expect($this->admin->fresh()->type)->toBe('a');
});

test('moderator can mark a user as verified', function () {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($this->moderator)
        ->patchJson("/api/admin/manage/users/{$unverified->id}", ['verified' => true])
        ->assertOk();

    expect($unverified->fresh()->email_verified_at)->not->toBeNull();
});

/**
 * Regression coverage for a real bug found tonight: DELETE /admin/manage/users/{user}
 * used to only detach conversation_user before a raw delete(), with NO check
 * for Organizer/Event/Community ownership — a moderator deleting a user who
 * owned any of those would silently orphan it (no DB constraint stops this).
 * Now shares UserDeletionService's ownership guard with self-service deletion.
 */
test('a moderator cannot delete a user who owns an organizer, and the organizer survives', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Organizer::find($organizer->id))->not->toBeNull();
});

test('a moderator cannot delete a user who has created an event, and the event survives', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Event::find($event->id))->not->toBeNull();
});

test('a moderator cannot delete a user who owns a community, and the community survives', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Community::find($community->id))->not->toBeNull();
});

test('a moderator can still delete a plain user with no owned organizer, event, or community', function () {
    $plain = User::factory()->create(['type' => 'u']);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$plain->id}")
        ->assertOk();

    expect(User::find($plain->id))->toBeNull();
});

test('a moderator still cannot delete themselves or an admin, independent of the ownership guard', function () {
    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$this->moderator->id}")
        ->assertStatus(403);

    $this->actingAs($this->moderator)
        ->deleteJson("/api/admin/manage/users/{$this->admin->id}")
        ->assertStatus(403);

    expect(User::find($this->moderator->id))->not->toBeNull();
    expect(User::find($this->admin->id))->not->toBeNull();
});
