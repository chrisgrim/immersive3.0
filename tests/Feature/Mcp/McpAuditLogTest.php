<?php

use App\Models\McpToolCall;
use App\Models\User;

/**
 * What an assistant did on someone's behalf must be answerable afterwards.
 */
test('every request to the mcp endpoint leaves an audit row naming the user, the credential and the tool', function () {
    $user = User::factory()->create();
    $token = mcpToken($user, ['mcp:use'], 'Laptop');

    mcpCall($this, $token)->assertOk();

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->user_id)->toBe($user->id);
    expect($row->token_id)->not->toBeNull();
    expect($row->client_name)->toBe('Test API keys');
    expect($row->method)->toBe('initialize');
    expect($row->tool)->toBeNull();
    expect($row->status)->toBe(200);
    expect($row->ip)->toBe('127.0.0.1');

    app('auth')->forgetGuards();
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
        'params' => ['name' => 'whoami', 'arguments' => []],
    ], ['Authorization' => "Bearer {$token}"]);

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->method)->toBe('tools/call');
    expect($row->tool)->toBe('whoami');
});

test('a refused request is recorded too, with no user', function () {
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer nope'])->assertStatus(401);

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->user_id)->toBeNull();
    expect($row->status)->toBe(401);
    expect($row->method)->toBe('initialize');
});
