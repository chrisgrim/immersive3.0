<?php

use App\Models\Curated\Community;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * AdminUserController had NO test coverage at all — not the destructive
 * delete, not the privilege-escalation guards on role changes. It is
 * moderator-gated and can delete any account on the site.
 *
 * That matters here specifically: this controller previously reached a raw
 * delete() with no Organizer/Event/Community ownership check, silently
 * orphaning that content when a moderator deleted its owner. The guard was
 * added, but nothing pinned it — mutating it away left the whole suite green.
 * These tests exist so the parallel path can't drift from
 * AccountDeletionController's again.
 */
$asModerator = fn () => User::factory()->create(['type' => 'm']);
$asAdmin = fn () => User::factory()->create(['type' => 'a']);

test('a moderator cannot delete a user who owns an organizer, and the organizer survives', function () use ($asModerator) {
    $moderator = $asModerator();
    $owner = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    $this->actingAs($moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Organizer::find($organizer->id))->not->toBeNull();
});

test('a moderator cannot delete a user who created an event, and the event survives', function () use ($asModerator) {
    $moderator = $asModerator();
    $owner = User::factory()->create();
    $event = Event::factory()->published()->create(['user_id' => $owner->id]);

    $this->actingAs($moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Event::find($event->id))->not->toBeNull();
});

test('a moderator cannot delete a user who owns a community, and the community survives', function () use ($asModerator) {
    $moderator = $asModerator();
    $owner = User::factory()->create();
    $community = Community::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($moderator)
        ->deleteJson("/api/admin/manage/users/{$owner->id}")
        ->assertStatus(422);

    expect(User::find($owner->id))->not->toBeNull();
    expect(Community::find($community->id))->not->toBeNull();
});

test('a moderator can delete a plain user who owns nothing', function () use ($asModerator) {
    $moderator = $asModerator();
    $target = User::factory()->create();

    $this->actingAs($moderator)
        ->deleteJson("/api/admin/manage/users/{$target->id}")
        ->assertOk();

    expect(User::find($target->id))->toBeNull();
});

test('nobody can delete their own account through the admin endpoint', function () use ($asAdmin) {
    $admin = $asAdmin();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/manage/users/{$admin->id}")
        ->assertStatus(403);

    expect(User::find($admin->id))->not->toBeNull();
});

test('a moderator cannot delete an admin', function () use ($asModerator, $asAdmin) {
    $moderator = $asModerator();
    $admin = $asAdmin();

    $this->actingAs($moderator)
        ->deleteJson("/api/admin/manage/users/{$admin->id}")
        ->assertStatus(403);

    expect(User::find($admin->id))->not->toBeNull();
});

test('an admin can delete another admin', function () use ($asAdmin) {
    $admin = $asAdmin();
    $other = $asAdmin();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/manage/users/{$other->id}")
        ->assertOk();

    expect(User::find($other->id))->toBeNull();
});

test('a plain user cannot reach the admin user endpoints at all', function () {
    $user = User::factory()->create(['type' => 'u']);
    $target = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin/manage/users')->assertForbidden();
    $this->actingAs($user)->deleteJson("/api/admin/manage/users/{$target->id}")->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Privilege escalation on update()
// ---------------------------------------------------------------------------

test('a moderator cannot change a users role', function () use ($asModerator) {
    $moderator = $asModerator();
    $target = User::factory()->create(['type' => 'u']);

    $this->actingAs($moderator)
        ->patchJson("/api/admin/manage/users/{$target->id}", ['type' => 'a'])
        ->assertStatus(403);

    expect($target->fresh()->type)->toBe('u');
});

test('a moderator can still edit ordinary user fields', function () use ($asModerator) {
    $moderator = $asModerator();
    $target = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($moderator)
        ->patchJson("/api/admin/manage/users/{$target->id}", ['name' => 'New Name'])
        ->assertOk();

    expect($target->fresh()->name)->toBe('New Name');
});

test('an admin cannot change their own role, so a sole admin cannot lock everyone out', function () use ($asAdmin) {
    $admin = $asAdmin();

    $this->actingAs($admin)
        ->patchJson("/api/admin/manage/users/{$admin->id}", ['type' => 'm'])
        ->assertStatus(403);

    expect($admin->fresh()->type)->toBe('a');
});

test('an admin can change another users role', function () use ($asAdmin) {
    $admin = $asAdmin();
    $target = User::factory()->create(['type' => 'u']);

    $this->actingAs($admin)
        ->patchJson("/api/admin/manage/users/{$target->id}", ['type' => 'm'])
        ->assertOk();

    expect($target->fresh()->type)->toBe('m');
});
