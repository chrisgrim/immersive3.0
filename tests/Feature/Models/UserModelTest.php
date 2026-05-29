<?php

use App\Models\Curated\Community;
use App\Models\Messaging\Conversation;
use App\Models\Organizer;
use App\Models\User;

// ----- getCurrentOrganizer() : path 1 — valid current_team_id -----

test('getCurrentOrganizer returns the organizer matching a valid current_team_id', function () {
    $user = User::factory()->create();
    $owned = Organizer::factory()->create(['user_id' => $user->id]);
    $user->update(['current_team_id' => $owned->id]);

    $current = $user->fresh()->getCurrentOrganizer();

    expect($current)->not->toBeNull();
    expect($current->id)->toBe($owned->id);
});

// ----- getCurrentOrganizer() : path 2 — fall back to owned org & persist current_team_id -----

test('getCurrentOrganizer falls back to a first owned organizer and persists current_team_id', function () {
    $user = User::factory()->create(['current_team_id' => 999999]); // invalid id
    $owned = Organizer::factory()->create(['user_id' => $user->id]);

    $current = $user->getCurrentOrganizer();

    expect($current->id)->toBe($owned->id);
    // side effect: current_team_id is corrected in the DB
    expect($user->fresh()->current_team_id)->toBe($owned->id);
});

// ----- getCurrentOrganizer() : path 3 — fall back to a membership -----

test('getCurrentOrganizer falls back to a team membership when the user owns nothing', function () {
    $owner = User::factory()->create();
    $org = Organizer::factory()->create(['user_id' => $owner->id]);

    $member = User::factory()->create(['current_team_id' => null]);
    $org->users()->attach($member->id, ['role' => 'member']);

    $current = $member->getCurrentOrganizer();

    expect($current)->not->toBeNull();
    expect($current->id)->toBe($org->id);
    // side effect: current_team_id is set to the membership org
    expect($member->fresh()->current_team_id)->toBe($org->id);
});

// ----- getCurrentOrganizer() : path 4 — clear current_team_id when nothing matches -----

test('getCurrentOrganizer clears a stale current_team_id and returns null when the user has no organizers', function () {
    $user = User::factory()->create(['current_team_id' => 555555]); // dangling id, no orgs

    $current = $user->getCurrentOrganizer();

    expect($current)->toBeNull();
    // side effect: stale current_team_id is wiped
    expect($user->fresh()->current_team_id)->toBeNull();
});

// ----- forClientSide() -----

test('forClientSide exposes the expected DTO fields with a null organizer when the user has none', function () {
    $user = User::factory()->create(['type' => 'u']);

    $dto = $user->forClientSide();

    expect($dto)->toHaveKeys([
        'id', 'name', 'email', 'hexColor', 'hasMessages', 'thumbImagePath',
        'isModerator', 'isAdmin', 'isCurator', 'isCommunityMember', 'unread',
        'hasCreatedOrganizers', 'organizer',
    ]);
    expect($dto['id'])->toBe($user->id);
    expect($dto['organizer'])->toBeNull();
    expect($dto['isAdmin'])->toBeFalse();
    expect($dto['isModerator'])->toBeFalse();
    expect($dto['isCurator'])->toBeFalse();
});

test('forClientSide includes a populated organizer object when the user owns one', function () {
    $user = User::factory()->create();
    $org = Organizer::factory()->create(['user_id' => $user->id, 'name' => 'My Org']);
    $user->update(['current_team_id' => $org->id]);

    $dto = $user->fresh()->forClientSide();

    expect($dto['organizer'])->toBe([
        'id' => $org->id,
        'name' => 'My Org',
    ]);
});

test('forClientSide reports elevated role flags for an admin', function () {
    $admin = User::factory()->create(['type' => 'a']);

    $dto = $admin->forClientSide();

    expect($dto['isAdmin'])->toBeTrue();
    expect($dto['isModerator'])->toBeTrue();
    expect($dto['isCurator'])->toBeTrue();
    expect($dto['isCommunityMember'])->toBeTrue();
});

// ----- role accessors by type char -----

test('hexColor is deterministic from the user id', function () {
    $palette = ['#2F405F', '#DA5E8E', '#20B7A6', '#749EEB', '#1EAA9A'];
    $user = User::factory()->create();

    expect($user->hexColor)->toBe($palette[$user->id % count($palette)]);
});

test('isCurator isAdmin isModerator isUser accessors map correctly for a guest type g', function () {
    $user = User::factory()->create(['type' => 'g']);

    expect($user->isCurator)->toBeFalse();
    expect($user->isAdmin)->toBeFalse();
    expect($user->isModerator)->toBeFalse();
    // isUser is true only for the currently authenticated user
    $this->actingAs($user);
    expect($user->isUser)->toBeTrue();
});

test('a curator type c is a curator but not a moderator or admin', function () {
    $user = User::factory()->create(['type' => 'c']);

    expect($user->isCurator)->toBeTrue();
    expect($user->isModerator)->toBeFalse();
    expect($user->isAdmin)->toBeFalse();
});

test('a moderator type m is a moderator and curator but not an admin', function () {
    $user = User::factory()->create(['type' => 'm']);

    expect($user->isModerator)->toBeTrue();
    expect($user->isCurator)->toBeTrue();
    expect($user->isAdmin)->toBeFalse();
});

test('an admin type a is admin moderator and curator', function () {
    $user = User::factory()->create(['type' => 'a']);

    expect($user->isAdmin)->toBeTrue();
    expect($user->isModerator)->toBeTrue();
    expect($user->isCurator)->toBeTrue();
});

test('isUser is false when a different user is authenticated', function () {
    $user = User::factory()->create(['type' => 'u']);
    $other = User::factory()->create(['type' => 'u']);

    $this->actingAs($other);

    expect($user->isUser)->toBeFalse();
});

// ----- getHasCreatedOrganizersAttribute -----

test('hasCreatedOrganizers is false when the user is not on any team pivot', function () {
    $user = User::factory()->create();

    expect($user->hasCreatedOrganizers)->toBeFalse();
});

test('hasCreatedOrganizers is true once the user is attached to an organizer team', function () {
    $user = User::factory()->create();
    // OrganizerFactory attaches the owner to organizer_user, so owning is enough
    Organizer::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->hasCreatedOrganizers)->toBeTrue();
});

// ----- getHasMessagesAttribute -----

test('hasMessages is false when the user is in no conversations', function () {
    $user = User::factory()->create();

    expect($user->hasMessages)->toBeFalse();
});

test('hasMessages is true when the user is user_one of a conversation', function () {
    $user = User::factory()->create();
    Conversation::factory()->create(['user_one' => $user->id]);

    expect($user->hasMessages)->toBeTrue();
});

test('hasMessages is true when the user is user_two of a conversation', function () {
    $user = User::factory()->create();
    Conversation::factory()->create(['user_two' => $user->id]);

    expect($user->hasMessages)->toBeTrue();
});

// ----- getIsCommunityMemberAttribute -----

test('isCommunityMember is false for a plain user with no communities', function () {
    $user = User::factory()->create(['type' => 'u']);

    expect($user->isCommunityMember)->toBeFalse();
});

test('isCommunityMember is true for a moderator and an admin regardless of communities', function () {
    expect(User::factory()->create(['type' => 'm'])->isCommunityMember)->toBeTrue();
    expect(User::factory()->create(['type' => 'a'])->isCommunityMember)->toBeTrue();
});

test('isCommunityMember is true for a plain user who actually belongs to a community', function () {
    $user = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create();
    $user->communities()->attach($community->id);

    expect($user->fresh()->isCommunityMember)->toBeTrue();
});
