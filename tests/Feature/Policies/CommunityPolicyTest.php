<?php

use App\Models\Curated\Community;
use App\Models\User;

function attachCurator(Community $community, User $user): User
{
    $community->curators()->attach($user->id);
    return $user->fresh();
}

// ----- manageCurators() -----

test('manageCurators: owner can manage', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    expect($owner->can('manageCurators', $community))->toBeTrue();
});

test('manageCurators: curator (but not owner) cannot manage', function () {
    $community = Community::factory()->create();
    $curator = attachCurator($community, User::factory()->create(['type' => 'u']));
    expect($curator->can('manageCurators', $community))->toBeFalse();
});

test('manageCurators: stranger cannot manage', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'u'])->can('manageCurators', $community))->toBeFalse();
});

test('manageCurators: moderator can manage any', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'm'])->can('manageCurators', $community))->toBeTrue();
});

// ----- curator() -----

test('curator: a curator passes', function () {
    $community = Community::factory()->create();
    $curator = attachCurator($community, User::factory()->create(['type' => 'u']));
    expect($curator->can('curator', $community))->toBeTrue();
});

test('curator: owner alone does NOT pass — must be on the curators pivot too', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    // Owner is not auto-attached to curators by the factory.
    expect($owner->can('curator', $community))->toBeFalse();
});

test('curator: stranger cannot', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'u'])->can('curator', $community))->toBeFalse();
});

test('curator: moderator can without being a curator', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'm'])->can('curator', $community))->toBeTrue();
});

// ----- update() -----

test('update: curator can update', function () {
    $community = Community::factory()->create();
    $curator = attachCurator($community, User::factory()->create(['type' => 'u']));
    expect($curator->can('update', $community))->toBeTrue();
});

test('update: stranger cannot update', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'u'])->can('update', $community))->toBeFalse();
});

test('update: moderator can update any', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'm'])->can('update', $community))->toBeTrue();
});

// ----- preview() -----

test('preview: published community is visible to guests', function () {
    $community = Community::factory()->published()->create();
    // Null user — guest path.
    expect(app(\App\Policies\CommunityPolicy::class)->preview(null, $community)->allowed())->toBeTrue();
});

test('preview: pending community is hidden from guests', function () {
    $community = Community::factory()->pending()->create();
    expect(app(\App\Policies\CommunityPolicy::class)->preview(null, $community)->allowed())->toBeFalse();
});

test('preview: pending community is visible to its curator', function () {
    $community = Community::factory()->pending()->create();
    $curator = attachCurator($community, User::factory()->create(['type' => 'u']));
    expect($curator->can('preview', $community))->toBeTrue();
});

test('preview: pending community is visible to moderators', function () {
    $community = Community::factory()->pending()->create();
    $mod = User::factory()->create(['type' => 'm']);
    expect($mod->can('preview', $community))->toBeTrue();
});

// ----- owner() -----

test('owner: owner passes', function () {
    $user = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $user->id]);
    expect($user->can('owner', $community))->toBeTrue();
});

test('owner: stranger cannot', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'u'])->can('owner', $community))->toBeFalse();
});

test('owner: moderator passes', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'm'])->can('owner', $community))->toBeTrue();
});

// ----- removeSelf() -----

test('removeSelf: owner is explicitly denied (cannot self-remove)', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['user_id' => $owner->id]);
    // Even if the owner is also on the curators pivot, they're denied.
    $community->curators()->attach($owner->id);
    expect($owner->fresh()->can('removeSelf', $community))->toBeFalse();
});

test('removeSelf: a non-owner curator can remove themselves', function () {
    $community = Community::factory()->create();
    $curator = attachCurator($community, User::factory()->create(['type' => 'u']));
    expect($curator->can('removeSelf', $community))->toBeTrue();
});

test('removeSelf: a stranger gets a clear deny', function () {
    $community = Community::factory()->create();
    expect(User::factory()->create(['type' => 'u'])->can('removeSelf', $community))->toBeFalse();
});
