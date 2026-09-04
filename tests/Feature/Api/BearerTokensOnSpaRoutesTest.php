<?php

use App\Models\Organizer;
use App\Models\User;

/**
 * Bearer tokens belong to the MCP endpoint only. Until 2026-09-03 the User
 * model carried Sanctum's token trait, and Sanctum's guard accepted any
 * valid personal access token on EVERY auth:sanctum SPA route — an "mcp"
 * token was a full session-equivalent credential. The trait is Passport's
 * now, and the SPA routes are cookie-only.
 */
test('a browser session still authenticates the SPA api routes', function () {
    $user = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->getJson('/api/teams/search')->assertStatus(200);
});

test('a personal access token does not authenticate the SPA api routes', function () {
    $user = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $user->id]);
    $token = mcpToken($user);

    $this->getJson('/api/teams/search', ['Authorization' => "Bearer {$token}"])->assertStatus(401);
});

test('the same token does authenticate the mcp endpoint', function () {
    $token = mcpToken(User::factory()->create(['type' => 'u']));

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])->assertStatus(200);
});

test('the user model no longer carries Sanctum tokens', function () {
    expect(in_array(\Laravel\Sanctum\HasApiTokens::class, class_uses_recursive(User::class), true))->toBeFalse();
    expect(in_array(\Laravel\Passport\HasApiTokens::class, class_uses_recursive(User::class), true))->toBeTrue();
});
