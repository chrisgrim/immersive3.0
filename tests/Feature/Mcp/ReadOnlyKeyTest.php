<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * The read-only door: a key carrying mcp:read alone opens /mcp/read, which
 * holds only reading tools, and never /mcp. It reads any published event and
 * nothing else.
 */
function readCall($test, string $token, string $method, array $params = [], string $path = '/mcp/read')
{
    app('auth')->forgetGuards();

    return $test->postJson($path, ['jsonrpc' => '2.0', 'id' => 2, 'method' => $method, 'params' => $params],
        ['Authorization' => "Bearer {$token}"]);
}

function someonesEvent(string $status): Event
{
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);

    return Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id, 'status' => $status]);
}

test('a read-only key opens /mcp/read and lists only reading tools', function () {
    $token = mcpToken(User::factory()->create(['type' => 'm']), [User::READ_SCOPE]);

    $tools = collect(readCall($this, $token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();

    expect($tools)->toBe(['get-event', 'list-all-events', 'whoami']);
});

test('a read-only key is refused by /mcp, where the writing tools live', function () {
    $token = mcpToken(User::factory()->create(['type' => 'a']), [User::READ_SCOPE]);

    mcpCall($this, $token)->assertStatus(403);
});

test('an ordinary key is refused by /mcp/read', function () {
    $token = mcpToken(User::factory()->create());

    $this->postJson('/mcp/read', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"])->assertStatus(403);
});

test('a read-only key reads someone else\'s published event', function () {
    $event = someonesEvent('p');
    $token = mcpToken(User::factory()->create(['type' => 'm']), [User::READ_SCOPE]);

    $response = readCall($this, $token, 'tools/call', ['name' => 'get-event', 'arguments' => ['event_slug' => $event->slug, 'summary' => true]]);

    $response->assertOk();
    expect($response->json('result.isError'))->toBeFalsy();
    expect(json_encode($response->json('result')))->toContain($event->slug);
});

test('a read-only key cannot read a draft or an event in review', function (string $status) {
    $event = someonesEvent($status);
    $token = mcpToken(User::factory()->create(['type' => 'a']), [User::READ_SCOPE]);

    $response = readCall($this, $token, 'tools/call', ['name' => 'get-event', 'arguments' => ['event_slug' => $event->slug]]);

    expect($response->json('result.isError'))->toBeTrue();
})->with(['draft' => 'd', 'in review' => 'r']);

test('a read-only key cannot call a writing tool through /mcp/read', function () {
    $event = someonesEvent('p');
    $token = mcpToken(User::factory()->create(['type' => 'a']), [User::READ_SCOPE]);

    $response = readCall($this, $token, 'tools/call', ['name' => 'update-event', 'arguments' => ['event_slug' => $event->slug, 'name' => 'Changed']]);

    expect($response->json('error') ?? $response->json('result.isError'))->toBeTruthy();
    expect($event->fresh()->name)->not->toBe('Changed');
});

test('only a moderator can mint a read-only key, and it carries mcp:read alone', function () {
    personalAccessClient();

    $this->actingAs(User::factory()->create(['type' => 'u']))
        ->postJson('/settings/api-tokens', ['name' => 'checker', 'read_only' => true])
        ->assertStatus(403);

    $response = $this->actingAs(User::factory()->create(['type' => 'm']))
        ->postJson('/settings/api-tokens', ['name' => 'checker', 'read_only' => true])
        ->assertCreated();

    expect($response->json('scopes'))->toBe([User::READ_SCOPE]);
});

test('a key cannot be both read-only and a moderator key', function () {
    $this->actingAs(User::factory()->create(['type' => 'm']))
        ->postJson('/settings/api-tokens', ['name' => 'both', 'read_only' => true, 'moderate' => true])
        ->assertStatus(422);
});

test('a read-only key never sees a secret location\'s street', function () {
    $event = someonesEvent('p');
    $event->location()->create(['street' => '12 Hidden Lane', 'home' => 'Back door', 'postal_code' => '94110',
        'city' => 'San Francisco', 'hiddenLocationToggle' => true, 'hiddenLocation' => 'Address sent with your ticket.']);
    $token = mcpToken(User::factory()->create(['type' => 'm']), [User::READ_SCOPE]);

    $json = json_encode(readCall($this, $token, 'tools/call', ['name' => 'get-event', 'arguments' => ['event_slug' => $event->slug, 'summary' => true]])->json('result'));

    expect($json)->not->toContain('12 Hidden Lane')->not->toContain('Back door')->not->toContain('94110')
        ->toContain('San Francisco')->toContain('Address sent with your ticket.');
});

test('a read-only key cannot read even its owner\'s own draft', function () {
    $owner = User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id, 'status' => 'p']);
    $draft = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $owner->id, 'status' => 'd']);
    $token = mcpToken($owner, [User::READ_SCOPE]);

    $response = readCall($this, $token, 'tools/call', ['name' => 'get-event', 'arguments' => ['event_slug' => $draft->slug]]);

    expect($response->json('result.isError'))->toBeTrue();
});
