<?php

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
