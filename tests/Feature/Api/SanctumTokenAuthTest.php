<?php

use App\Models\Organizer;
use App\Models\User;

/**
 * Regression tests for enabling Sanctum personal access tokens (HasApiTokens on User).
 * Session-cookie auth on the SPA API routes must keep working exactly as before,
 * and bearer tokens must authenticate non-stateful requests.
 */
test('session-based auth on api routes still works with HasApiTokens enabled', function () {
    $user = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/teams/search')
        ->assertStatus(200);
});

test('a personal access token authenticates a bearer request', function () {
    $user = User::factory()->create(['type' => 'u']);
    Organizer::factory()->create(['user_id' => $user->id]);

    $token = $user->createToken('test-token', ['mcp'])->plainTextToken;

    $this->getJson('/api/teams/search', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(200);
});

test('an invalid bearer token is rejected', function () {
    $this->getJson('/api/teams/search', ['Authorization' => 'Bearer not-a-real-token'])
        ->assertStatus(401);
});

test('an expired token is rejected', function () {
    $user = User::factory()->create(['type' => 'u']);
    $token = $user->createToken('expiring', ['mcp'], now()->subMinute())->plainTextToken;

    $this->getJson('/api/teams/search', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

test('createToken stores an expiry and hashed token', function () {
    $user = User::factory()->create();
    $newToken = $user->createToken('mcp-token', ['mcp'], now()->addDays(90));

    expect($newToken->accessToken->expires_at)->not->toBeNull();
    expect($newToken->accessToken->abilities)->toBe(['mcp']);
    expect($newToken->plainTextToken)->not->toContain($newToken->accessToken->token);
});
