<?php

use App\Http\Middleware\ThrottleMcpByIp;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\Passport;

/**
 * HTTP-level auth on the /mcp endpoint: Passport bearer tokens carrying the
 * mcp:use scope, and nothing else — no session, no cookie, no CSRF.
 */
test('the mcp endpoint rejects unauthenticated requests and says where to get a token', function () {
    $response = $this->postJson('/mcp', mcpInitializePayload())->assertStatus(401);

    // RFC 9728: the challenge names the protected-resource metadata, which
    // is how an MCP client discovers the OAuth flow without being told.
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('Bearer')
        ->toContain('resource_metadata=');
});

test('the mcp endpoint rejects a garbage bearer token', function () {
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer nope'])
        ->assertStatus(401);
});

test('the mcp endpoint rejects a token from the retired Sanctum scheme', function () {
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer 1|'.str_repeat('a', 40)])
        ->assertStatus(401);
});

test('the mcp endpoint rejects a token without the mcp:use scope', function () {
    $token = mcpToken(User::factory()->create(['type' => 'a']), [User::MODERATE_SCOPE]);

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

test('the mcp endpoint rejects a revoked token', function () {
    $user = User::factory()->create();
    $token = mcpToken($user);
    $user->tokens()->update(['revoked' => true]);

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

test('the mcp endpoint rejects an expired token', function () {
    // Expiry is a claim inside the signed token, checked against the clock —
    // editing the row would prove nothing. Issue one that is already stale.
    Passport::personalAccessTokensExpireIn(now()->subMinute());

    try {
        $token = mcpToken(User::factory()->create());
    } finally {
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(90));
    }

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

test('a browser session alone does not authenticate the mcp endpoint', function () {
    // The SPA cookie path must not apply here: a page on the site that a
    // signed-in user visits can never be talked into calling /mcp as them.
    $this->actingAs(User::factory()->create())
        ->postJson('/mcp', mcpInitializePayload())
        ->assertStatus(401);
});

test('the mcp endpoint accepts a valid token', function () {
    $token = mcpToken(User::factory()->create());

    $response = $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"]);

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('json');
});

test('GET on the mcp endpoint returns 405 without leaking data', function () {
    $this->get('/mcp')->assertStatus(405);
});

test('a flood from one address is refused before authentication runs', function () {
    // The named `throttle:mcp` limiter runs after auth, so a bad-token flood
    // never reached it. ThrottleMcpByIp is the pre-auth limit.
    $key = 'mcp-ip:127.0.0.1';
    RateLimiter::clear($key);

    try {
        foreach (range(1, ThrottleMcpByIp::MAX_PER_MINUTE) as $i) {
            RateLimiter::hit($key, 60);
        }

        $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer nope'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    } finally {
        RateLimiter::clear($key);
    }
});
