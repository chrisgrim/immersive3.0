<?php

use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// ==========================================================================
// PostPolicy — update / destroy / preview
// ==========================================================================
// note: PostPolicy::isCuratorOrAdmin() allows a user who is in the post's
// community->curators (community_user pivot) OR whose type is 'a' or 'm'.
// A bare curator (type 'c') who is NOT attached to the community is denied.
// preview() additionally allows ANYONE (even guests/null user) when the post
// status === 'p'.

test('update is allowed for a curator attached to the posts community', function () {
    $community = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $community->curators()->attach($curator->id);
    $post = Post::factory()->create(['community_id' => $community->id]);

    expect($curator->can('update', $post))->toBeTrue();
});

test('destroy is allowed for a curator attached to the posts community', function () {
    $community = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $community->curators()->attach($curator->id);
    $post = Post::factory()->create(['community_id' => $community->id]);

    expect($curator->can('destroy', $post))->toBeTrue();
});

test('update and destroy are allowed for admin and moderator', function () {
    $community = Community::factory()->create();
    $admin = User::factory()->create(['type' => 'a']);
    $moderator = User::factory()->create(['type' => 'm']);
    $post = Post::factory()->create(['community_id' => $community->id]);

    expect($admin->can('update', $post))->toBeTrue();
    expect($admin->can('destroy', $post))->toBeTrue();
    expect($moderator->can('update', $post))->toBeTrue();
    expect($moderator->can('destroy', $post))->toBeTrue();
});

test('update and destroy are denied for a curator not attached to the community', function () {
    // note: type 'c' alone is not enough — the user must be in the community's
    // curators pivot. A curator of a *different* (or no) community is denied.
    $community = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $post = Post::factory()->create(['community_id' => $community->id]);

    expect($curator->can('update', $post))->toBeFalse();
    expect($curator->can('destroy', $post))->toBeFalse();
});

test('update and destroy are denied for a regular user', function () {
    $community = Community::factory()->create();
    $user = User::factory()->create(['type' => 'u']);
    $post = Post::factory()->create(['community_id' => $community->id]);

    expect($user->can('update', $post))->toBeFalse();
    expect($user->can('destroy', $post))->toBeFalse();
});

test('a curator attached to a different community cannot update the post', function () {
    $communityA = Community::factory()->create();
    $communityB = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $communityB->curators()->attach($curator->id);
    $post = Post::factory()->create(['community_id' => $communityA->id]);

    expect($curator->can('update', $post))->toBeFalse();
    expect($curator->can('destroy', $post))->toBeFalse();
});

// ----- preview() -----

test('preview is allowed to anyone for a published post', function () {
    $community = Community::factory()->create();
    $post = Post::factory()->published()->create(['community_id' => $community->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    expect($stranger->can('preview', $post))->toBeTrue();
});

test('preview is allowed to a guest user for a published post', function () {
    // note: preview() accepts a nullable User; a null (unauthenticated) user is
    // allowed to preview a published post. Tested via Gate::forUser(null).
    $community = Community::factory()->create();
    $post = Post::factory()->published()->create(['community_id' => $community->id]);

    expect(Gate::forUser(null)->allows('preview', $post))->toBeTrue();
});

test('preview is allowed for a community curator on a non-published post', function () {
    $community = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $community->curators()->attach($curator->id);
    $post = Post::factory()->draft()->create(['community_id' => $community->id]);

    expect($curator->can('preview', $post))->toBeTrue();
});

test('preview is allowed for an admin on a non-published post', function () {
    $community = Community::factory()->create();
    $admin = User::factory()->create(['type' => 'a']);
    $post = Post::factory()->draft()->create(['community_id' => $community->id]);

    expect($admin->can('preview', $post))->toBeTrue();
});

test('preview is denied for a guest on a non-published post', function () {
    $community = Community::factory()->create();
    $post = Post::factory()->draft()->create(['community_id' => $community->id]);

    expect(Gate::forUser(null)->allows('preview', $post))->toBeFalse();
});

test('preview is denied for a non-curator regular user on a non-published post', function () {
    $community = Community::factory()->create();
    $user = User::factory()->create(['type' => 'u']);
    $post = Post::factory()->draft()->create(['community_id' => $community->id]);

    expect($user->can('preview', $post))->toBeFalse();
});

test('preview is denied for a curator not attached to the community on a non-published post', function () {
    $community = Community::factory()->create();
    $curator = User::factory()->create(['type' => 'c']);
    $post = Post::factory()->draft()->create(['community_id' => $community->id]);

    expect($curator->can('preview', $post))->toBeFalse();
});

// ==========================================================================
// UserPolicy — update
// ==========================================================================
// note: UserPolicy::update() allows the user updating their own profile
// ($user->id === $model->id) or any moderator (isModerator() === type 'm'/'a').

test('user update is allowed for self', function () {
    $user = User::factory()->create(['type' => 'u']);

    expect($user->can('update', $user))->toBeTrue();
});

test('user update is allowed for a moderator updating another user', function () {
    $moderator = User::factory()->create(['type' => 'm']);
    $target = User::factory()->create(['type' => 'u']);

    expect($moderator->can('update', $target))->toBeTrue();
});

test('user update is allowed for an admin updating another user', function () {
    // note: isModerator() returns true for type 'a' too.
    $admin = User::factory()->create(['type' => 'a']);
    $target = User::factory()->create(['type' => 'u']);

    expect($admin->can('update', $target))->toBeTrue();
});

test('user update is denied for a different regular user', function () {
    $user = User::factory()->create(['type' => 'u']);
    $target = User::factory()->create(['type' => 'u']);

    expect($user->can('update', $target))->toBeFalse();
});

test('user update is denied for a curator updating another user', function () {
    // note: a curator (type 'c') is not a moderator, so they cannot update
    // another user's profile.
    $curator = User::factory()->create(['type' => 'c']);
    $target = User::factory()->create(['type' => 'u']);

    expect($curator->can('update', $target))->toBeFalse();
});
