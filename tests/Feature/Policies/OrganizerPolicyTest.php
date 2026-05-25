<?php

use App\Models\Organizer;
use App\Models\User;

// Helper — make an unrelated user (not on any organizer pivot, not an owner).
function strangerUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type]);
}

// ----- viewAny() -----

test('viewAny: owner of any organizer can view', function () {
    $user = strangerUser();
    Organizer::factory()->create(['user_id' => $user->id]);
    expect($user->fresh()->can('viewAny', Organizer::class))->toBeTrue();
});

test('viewAny: user with no organizers cannot', function () {
    expect(strangerUser()->can('viewAny', Organizer::class))->toBeFalse();
});

test('viewAny: moderator can without owning anything', function () {
    expect(strangerUser('m')->can('viewAny', Organizer::class))->toBeTrue();
});

// ----- edit() -----

test('edit: owner can edit', function () {
    $user = strangerUser();
    $organizer = Organizer::factory()->create(['user_id' => $user->id]);
    expect($user->fresh()->can('edit', $organizer))->toBeTrue();
});

test('edit: pivot member can edit', function () {
    $organizer = Organizer::factory()->create();
    $member = strangerUser();
    $organizer->users()->attach($member->id, ['role' => 'member']);
    expect($member->fresh()->can('edit', $organizer))->toBeTrue();
});

test('edit: stranger cannot edit', function () {
    $organizer = Organizer::factory()->create();
    expect(strangerUser()->can('edit', $organizer))->toBeFalse();
});

test('edit: moderator can edit any', function () {
    $organizer = Organizer::factory()->create();
    expect(strangerUser('m')->can('edit', $organizer))->toBeTrue();
});

// ----- create() -----

test('create: any authenticated user can create', function () {
    expect(strangerUser()->can('create', Organizer::class))->toBeTrue();
});

// ----- switchTeam() -----

test('switchTeam: owner can switch to their organizer', function () {
    $user = strangerUser();
    $organizer = Organizer::factory()->create(['user_id' => $user->id]);
    expect($user->fresh()->can('switchTeam', $organizer))->toBeTrue();
});

test('switchTeam: pivot member can switch', function () {
    $organizer = Organizer::factory()->create();
    $member = strangerUser();
    $organizer->users()->attach($member->id, ['role' => 'member']);
    expect($member->fresh()->can('switchTeam', $organizer))->toBeTrue();
});

test('switchTeam: stranger cannot switch', function () {
    $organizer = Organizer::factory()->create();
    expect(strangerUser()->can('switchTeam', $organizer))->toBeFalse();
});

test('switchTeam: moderator can switch into any organizer (intentional per H1)', function () {
    $organizer = Organizer::factory()->create();
    expect(strangerUser('m')->can('switchTeam', $organizer))->toBeTrue();
});

test('switchTeam: admin can switch into any organizer', function () {
    $organizer = Organizer::factory()->create();
    expect(strangerUser('a')->can('switchTeam', $organizer))->toBeTrue();
});
