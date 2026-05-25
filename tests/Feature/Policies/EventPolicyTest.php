<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * Covers App\Policies\EventPolicy.
 *
 * Permission model (from User::type):
 *   'g' guest, 'u' user, 'c' curator, 'm' moderator, 'a' admin
 * Belonging = User is on Organizer's organizer_user pivot OR owns the organizer.
 */

function makeOrgWith(User $owner, User $member = null): Organizer
{
    $organizer = Organizer::factory()->create(['user_id' => $owner->id]);
    if ($member) {
        $organizer->users()->attach($member->id, ['role' => 'member']);
    }
    return $organizer;
}

// host()
test('host: guest with no teams cannot host', function () {
    $user = User::factory()->create(['type' => 'u']);
    expect($user->can('host', Event::class))->toBeFalse();
});

test('host: organizer member can host', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    makeOrgWith($owner, $member);
    expect($member->fresh()->can('host', Event::class))->toBeTrue();
});

test('host: moderators with no teams can still host', function () {
    $mod = User::factory()->create(['type' => 'm']);
    expect($mod->can('host', Event::class))->toBeTrue();
});

// manage()
test('manage: organizer owner can manage own event', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $organizer = makeOrgWith($owner);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    expect($owner->fresh()->can('manage', $event))->toBeTrue();
});

test('manage: organizer member can manage org event', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer = makeOrgWith($owner, $member);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    expect($member->fresh()->can('manage', $event))->toBeTrue();
});

test('manage: stranger cannot manage event', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create(['type' => 'u']);
    $organizer = makeOrgWith($owner);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    expect($stranger->can('manage', $event))->toBeFalse();
});

test('manage: moderator can manage any event', function () {
    $mod = User::factory()->create(['type' => 'm']);
    $event = Event::factory()->create();
    expect($mod->can('manage', $event))->toBeTrue();
});

test('manage: admin can manage any event', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $event = Event::factory()->create();
    expect($admin->can('manage', $event))->toBeTrue();
});

test('manage: curator role alone does not grant manage', function () {
    $curator = User::factory()->create(['type' => 'c']);
    $event = Event::factory()->create();
    expect($curator->can('manage', $event))->toBeFalse();
});

// duplicate()
test('duplicate: organizer member can duplicate org event', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer = makeOrgWith($owner, $member);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    expect($member->fresh()->can('duplicate', $event))->toBeTrue();
});

test('duplicate: stranger cannot duplicate', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->create();
    expect($stranger->can('duplicate', $event))->toBeFalse();
});

test('duplicate: admin can duplicate any event', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $event = Event::factory()->create();
    expect($admin->can('duplicate', $event))->toBeTrue();
});

// moderate()
test('moderate: regular user cannot moderate', function () {
    $user = User::factory()->create(['type' => 'u']);
    expect($user->can('moderate', Event::class))->toBeFalse();
});

test('moderate: curator cannot moderate', function () {
    $curator = User::factory()->create(['type' => 'c']);
    expect($curator->can('moderate', Event::class))->toBeFalse();
});

test('moderate: moderator can moderate', function () {
    $mod = User::factory()->create(['type' => 'm']);
    expect($mod->can('moderate', Event::class))->toBeTrue();
});

test('moderate: admin can moderate', function () {
    $admin = User::factory()->create(['type' => 'a']);
    expect($admin->can('moderate', Event::class))->toBeTrue();
});

// viewClickStats()
test('viewClickStats: organizer member can view', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer = makeOrgWith($owner, $member);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id]);
    expect($member->fresh()->can('viewClickStats', $event))->toBeTrue();
});

test('viewClickStats: stranger cannot view', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->create();
    expect($stranger->can('viewClickStats', $event))->toBeFalse();
});

test('viewClickStats: moderator can view any', function () {
    $mod = User::factory()->create(['type' => 'm']);
    $event = Event::factory()->create();
    expect($mod->can('viewClickStats', $event))->toBeTrue();
});
