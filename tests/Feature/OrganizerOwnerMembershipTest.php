<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * The owner (organizers.user_id) is always also a member (organizer_user,
 * role owner). Most access checks read membership only, so an owner missing
 * from it could edit the organizer but not host, switch or see it in team
 * lists, and a "removed" owner kept access. Organizer 1494 was in exactly that
 * state on production (2026-09-24).
 */
test('a new organizer has its owner as an owner member', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id]);

    expect($organizer->users()->whereKey($owner->id)->first()?->membership->role)->toBe('owner');
});

test('changing user_id directly makes the new owner an owner member', function () {
    $organizer = Organizer::factory()->create();
    $newOwner = User::factory()->create(['type' => 'g']);
    $organizer->users()->attach($newOwner->id, ['role' => 'moderator']);

    $organizer->update(['user_id' => $newOwner->id]);

    expect($organizer->users()->whereKey($newOwner->id)->first()->membership->role)->toBe('owner')
        ->and($organizer->users()->where('users.id', $newOwner->id)->count())->toBe(1);
});

test('a new owner can host and sees the organizer in their team list', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    $newOwner = User::factory()->create(['type' => 'u']);

    $organizer->update(['user_id' => $newOwner->id]);

    expect($newOwner->fresh()->can('host', Event::class))->toBeTrue();

    $this->actingAs($newOwner)
        ->getJson('/api/teams/search')
        ->assertOk()
        ->assertJsonPath('teams.data.0.id', $organizer->id)
        ->assertJsonPath('teams.data.0.role', 'owner');
});

test('saving an organizer without changing its owner leaves the team alone', function () {
    $organizer = Organizer::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer->users()->attach($member->id, ['role' => 'moderator']);

    $organizer->update(['description' => 'New bio']);

    expect($organizer->users()->count())->toBe(2)
        ->and($organizer->users()->whereKey($member->id)->first()->membership->role)->toBe('moderator');
});
