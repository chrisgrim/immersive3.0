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

test('a batch names every tool it called, and a body that is not JSON-RPC still leaves a row', function () {
    $token = mcpToken(User::factory()->create());

    app('auth')->forgetGuards();
    $this->postJson('/mcp', [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'whoami', 'arguments' => []]],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'list-my-events', 'arguments' => []]],
    ], ['Authorization' => "Bearer {$token}"]);

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->method)->toBe('batch');
    expect($row->tool)->toBe('whoami,list-my-events');

    // A batch too long for the column is cut with a visible marker, on a
    // character boundary, rather than truncated silently.
    app('auth')->forgetGuards();
    $this->postJson('/mcp', array_map(fn ($i) => [
        'jsonrpc' => '2.0', 'id' => $i, 'method' => 'tools/call', 'params' => ['name' => "tool-ünïcode-{$i}", 'arguments' => []],
    ], range(1, 40)), ['Authorization' => "Bearer {$token}"]);

    $row = McpToolCall::query()->latest('id')->first();
    expect(mb_strlen($row->tool))->toBeLessThanOrEqual(255);
    expect($row->tool)->toEndWith('…(more)');
    expect(mb_check_encoding($row->tool, 'UTF-8'))->toBeTrue();

    app('auth')->forgetGuards();
    $this->call('POST', '/mcp', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}", 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], 'this is not json');

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->method)->toBe('?');
    expect($row->tool)->toBeNull();
});

test('the trail is pruned after its retention period', function () {
    McpToolCall::create(['method' => 'initialize', 'status' => 200, 'duration_ms' => 1, 'created_at' => now()->subDays(McpToolCall::RETENTION_DAYS + 1)]);
    McpToolCall::create(['method' => 'initialize', 'status' => 200, 'duration_ms' => 1, 'created_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [McpToolCall::class]])->assertSuccessful();

    expect(McpToolCall::count())->toBe(1);
});

test('a refused request is recorded too, with no user', function () {
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => 'Bearer nope'])->assertStatus(401);

    $row = McpToolCall::query()->latest('id')->first();
    expect($row->user_id)->toBeNull();
    expect($row->status)->toBe(401);
    expect($row->method)->toBe('initialize');
});
