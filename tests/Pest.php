<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use phpseclib3\Crypt\RSA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->beforeEach(fn () => passportTestKeys())->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Point Passport at a throwaway RSA key pair for the test run, generated once
 * per machine under storage/framework/testing (git-ignored). Real tokens are
 * signed JWTs, so HTTP-level tests need real keys; 2048 bits keeps the
 * one-off generation to a fraction of a second.
 */
function passportTestKeys(): void
{
    $dir = storage_path('framework/testing/oauth');

    if (! is_file($dir.'/oauth-private.key') || ! is_file($dir.'/oauth-public.key')) {
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $key = RSA::createKey(2048);
        file_put_contents($dir.'/oauth-private.key', (string) $key);
        file_put_contents($dir.'/oauth-public.key', (string) $key->getPublicKey());
        chmod($dir.'/oauth-private.key', 0600);
        chmod($dir.'/oauth-public.key', 0600);
    }

    Passport::loadKeysFrom($dir);
}

/**
 * The personal-access client createToken() needs. RefreshDatabase wipes it,
 * so it is made on demand — the same call `mcp:oauth-setup` makes on deploy.
 */
function personalAccessClient(): Client
{
    $clients = app(ClientRepository::class);

    try {
        return $clients->personalAccessClient('users');
    } catch (\RuntimeException) {
        return $clients->createPersonalAccessGrantClient('Test API keys', 'users');
    }
}

/**
 * A real, signed personal access token for HTTP-level tests of /mcp.
 */
function mcpToken(User $user, array $scopes = ['mcp:use'], string $name = 'test'): string
{
    personalAccessClient();

    return $user->createToken($name, $scopes)->accessToken;
}

/**
 * A JSON-RPC initialize call: the smallest request that proves /mcp let a
 * credential in. Shared by every HTTP-level test of the endpoint.
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

/**
 * Call /mcp with a bearer token. Guards are reset first: Passport's request
 * guard caches the user it resolved, and in one test process that cache
 * survives between requests, so a revoked token "worked" if a good one had
 * just been used. Every real request is a fresh process.
 */
function mcpCall($test, string $token)
{
    app('auth')->forgetGuards();

    return $test->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$token}"]);
}
