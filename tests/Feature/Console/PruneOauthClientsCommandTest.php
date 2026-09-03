<?php

use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

function registeredClient(string $name = 'x'): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $name, redirectUris: ['http://localhost:1/cb'], confidential: false, enableDeviceFlow: false,
    );
}

test('it removes stale registrations that never obtained a token, and nothing else', function () {
    $staleUnused = registeredClient('stale unused');
    $staleUsed = registeredClient('stale used');
    $fresh = registeredClient('fresh');
    $personal = personalAccessClient();

    Client::whereIn('id', [$staleUnused->id, $staleUsed->id, $personal->id])->update(['created_at' => now()->subDays(2)]);
    $staleUsed->tokens()->create([
        'id' => str_repeat('a', 40), 'user_id' => User::factory()->create()->id, 'scopes' => ['mcp:use'], 'revoked' => false,
    ]);

    $this->artisan('mcp:prune-oauth-clients')->assertSuccessful()->expectsOutputToContain('Pruned 1');

    expect(Client::find($staleUnused->id))->toBeNull();
    expect(Client::find($staleUsed->id))->not->toBeNull();
    expect(Client::find($fresh->id))->not->toBeNull();
    expect(Client::find($personal->id))->not->toBeNull();
});
