<?php

use App\Models\User;

/**
 * HTTP-level auth on the /mcp endpoint: Sanctum bearer tokens with the 'mcp'
 * ability only. No session, no CSRF — the SPA cookie path does not apply here.
 */
function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => [],
            'clientInfo' => ['name' => 'pest', 'version' => '1.0'],
        ],
    ];
}

test('the mcp endpoint rejects unauthenticated requests', function () {
    $this->postJson('/mcp', mcpInitializePayload())->assertStatus(401);
});

test('the mcp endpoint rejects an invalid bearer token', function () {
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer nope'])
        ->assertStatus(401);
});

test('the mcp endpoint rejects a token without the mcp ability', function () {
    $user = User::factory()->create();
    $token = $user->createToken('no-ability', ['other'])->plainTextToken;

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

test('the mcp endpoint rejects an expired token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('expired', ['mcp'], now()->subDay())->plainTextToken;

    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

test('the mcp endpoint accepts a valid mcp token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('valid', ['mcp'])->plainTextToken;

    $response = $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"]);

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('json');
});

test('GET on the mcp endpoint returns 405 without leaking data', function () {
    $this->get('/mcp')->assertStatus(405);
});
