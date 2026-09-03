<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * The browser-and-client halves of connecting an assistant, end to end over
 * HTTP: discovery, registration, sign-in redirect, consent, the PKCE code
 * exchange, refresh, revocation — and the rules the consent screen enforces.
 */
function oauthClient(array $redirectUris = ['http://localhost:9000/callback'], string $name = 'Test Assistant'): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $name,
        redirectUris: $redirectUris,
        confidential: false,
        enableDeviceFlow: false,
    );
}

/** @return array{0: string, 1: string} verifier, S256 challenge */
function pkcePair(): array
{
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function authorizeQuery(Client $client, string $challenge, array $extra = []): string
{
    return http_build_query(array_merge([
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'response_type' => 'code',
        'state' => 'st4te',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], $extra));
}

function consentUser(string $type = 'm'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

/** Walk the browser half as $user: view the consent screen, approve, return the code. */
function approveAndGetCode($test, User $user, Client $client, string $challenge, array $approveWith = []): string
{
    $test->actingAs($user)->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk();

    $response = $test->actingAs($user)->post('/oauth/authorize', $approveWith + [
        'auth_token' => session('authToken'),
        'state' => 'st4te',
        'client_id' => $client->getKey(),
    ]);
    $response->assertRedirect();

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'] ?? null)->toBe('st4te');
    expect($query['code'] ?? '')->not->toBe('');

    return $query['code'];
}

function exchangeCode($test, Client $client, string $code, ?string $verifier)
{
    return $test->postJson('/oauth/token', array_filter([
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'code' => $code,
        'code_verifier' => $verifier,
    ]));
}

// ── discovery ─────────────────────────────────────────────────────────────

test('the authorization server metadata describes exactly the flow we support', function () {
    $meta = $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    expect($meta['authorization_endpoint'])->toBe(url('/oauth/authorize'));
    expect($meta['token_endpoint'])->toBe(url('/oauth/token'));
    expect($meta['registration_endpoint'])->toBe(url('/oauth/register'));
    expect($meta['response_types_supported'])->toBe(['code']);
    expect($meta['code_challenge_methods_supported'])->toBe(['S256']);
    expect($meta['grant_types_supported'])->toBe(['authorization_code', 'refresh_token']);
    expect($meta['scopes_supported'])->toBe(['mcp:use']);
});

test('the protected resource metadata points the client at this server', function () {
    $meta = $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->json();

    expect($meta['resource'])->toBe(url('/mcp'));
    expect($meta['authorization_servers'])->toBe([url('/')]);
});

// ── dynamic client registration ───────────────────────────────────────────

test('registration accepts allowlisted redirects and refuses everything else', function () {
    $key = md5('oauth-register'.'oauth-register:127.0.0.1');
    RateLimiter::clear($key);

    try {
        // Claude Code's loopback callback.
        $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:51234/callback']])
            ->assertStatus(201)
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('scope', 'mcp:use');

        // claude.ai's connector callback.
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])
            ->assertStatus(201);

        // A desktop editor's private scheme.
        $this->postJson('/oauth/register', ['client_name' => 'Cursor', 'redirect_uris' => ['cursor://anysphere.cursor-retrieval/oauth/callback']])
            ->assertStatus(201);

        // Loopback on any port, either scheme, IPv6 too.
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://localhost:1234/cb', 'http://127.0.0.1:8/cb', 'http://[::1]:5555/cb']])
            ->assertStatus(201);

        // Anything else: the package default was '*', which would have let a
        // stranger register a client that sends approved codes to their server.
        $refused = [
            'https://claude.ai.evil.example/cb',     // suffix confusion
            'https://evil.example/cb',               // not on the list
            'evil://cb',                             // unknown scheme
            'http://localhost:80@evil.example/cb',   // "localhost" is the userinfo; the host is evil.example
            'http://127.0.0.1@evil.example/cb',
            'https://claude.ai@evil.example/cb',
            'https://claude.ai:8443/cb',             // no ports off loopback
            'http://claude.ai/cb',                   // https only off loopback
            'https://claude.ai/cb#fragment',
            'cursor://user:pw@host/cb',              // credentials in a private scheme
            'cursor:///no-host',
            'https://claude.ai/c b',                 // whitespace inside (the ends are trimmed by TrimStrings before validation)
        ];
        foreach ($refused as $uri) {
            RateLimiter::clear($key); // this test alone would trip the 10/minute limit
            $this->postJson('/oauth/register', ['client_name' => 'x', 'redirect_uris' => [$uri]])
                ->assertStatus(400)
                ->assertJsonPath('error', 'invalid_redirect_uri');
        }
        expect(Client::query()->whereJsonContains('redirect_uris', 'http://localhost:80@evil.example/cb')->exists())->toBeFalse();

        // One bad URI poisons the whole registration, and there is a cap.
        RateLimiter::clear($key);
        $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:1/cb', 'https://evil.example/cb']])->assertStatus(400);
        RateLimiter::clear($key);
        $this->postJson('/oauth/register', ['redirect_uris' => array_map(fn ($i) => "http://localhost:{$i}/cb", range(1, 6))])->assertStatus(400);
    } finally {
        RateLimiter::clear($key);
    }
});

test('registration is throttled per address', function () {
    $key = md5('oauth-register'.'oauth-register:127.0.0.1');
    RateLimiter::clear($key);

    try {
        foreach (range(1, 10) as $i) {
            RateLimiter::hit($key, 60);
        }

        $this->postJson('/oauth/register', ['redirect_uris' => ['http://localhost:1/cb']])->assertStatus(429);
    } finally {
        RateLimiter::clear($key);
    }
});

// ── the consent screen and its gate ───────────────────────────────────────

test('a guest is sent to sign in, and the authorize request is remembered', function () {
    [, $challenge] = pkcePair();
    $url = '/oauth/authorize?'.authorizeQuery(oauthClient(), $challenge);

    $this->get($url)->assertRedirect(route('login'));

    // Symfony re-sorts the query string when it records the URL, so compare
    // the parts rather than the exact text.
    $intended = (string) session('url.intended');
    expect($intended)->toStartWith(url('/oauth/authorize?'));
    parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('st4te');
    expect($query['code_challenge'])->toBe($challenge);
});

test('while the feature is private only moderators may connect an assistant', function () {
    config(['services.mcp.public' => false]);
    [, $challenge] = pkcePair();
    $client = oauthClient();

    $this->actingAs(consentUser('u'))->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertStatus(403);
    $this->actingAs(consentUser('m'))->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk();

    config(['services.mcp.public' => true]);
    $this->actingAs(consentUser('u'))->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk();
});

test('an unverified email cannot connect an assistant', function () {
    [, $challenge] = pkcePair();
    $user = User::factory()->create(['type' => 'm', 'email_verified_at' => null]);

    $this->actingAs($user)->get('/oauth/authorize?'.authorizeQuery(oauthClient(), $challenge))->assertStatus(403);
});

test('the consent screen names the app, the account, and where the browser will go', function () {
    [, $challenge] = pkcePair();
    $user = consentUser();
    $client = oauthClient(['http://localhost:9000/callback'], 'Claude Code');

    $this->actingAs($user)->get('/oauth/authorize?'.authorizeQuery($client, $challenge))
        ->assertOk()
        ->assertSee('Connect Claude Code?')
        ->assertSee($user->email)
        ->assertSee('localhost')
        ->assertSee('Not you? Sign out.')
        ->assertSee('Act as a moderator, unless you say so below') // consentUser() is a moderator
        ->assertSee('Approve');
});

test('a moderator can include moderator powers on a connection, and nobody else can', function () {
    // The consent screen offers the choice to moderators only…
    [, $challenge] = pkcePair();
    $client = oauthClient();
    $this->actingAs(consentUser('m'))->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk()->assertSee('data-test="moderate-option"', false);
    config(['services.mcp.public' => true]);
    $this->actingAs(consentUser('u'))->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk()->assertDontSee('data-test="moderate-option"', false);

    // …and honours it only from a moderator.
    foreach ([['m', true, ['mcp:use', User::MODERATE_SCOPE]], ['m', false, ['mcp:use']], ['u', true, ['mcp:use']]] as [$type, $tick, $expected]) {
        [$verifier, $challenge] = pkcePair();
        $user = consentUser($type);
        $client = oauthClient();
        $code = approveAndGetCode($this, $user, $client, $challenge, $tick ? ['moderate' => '1'] : []);
        $tokens = exchangeCode($this, $client, $code, $verifier)->assertOk()->json();

        $stored = $user->tokens()->first();
        expect($stored->scopes)->toBe($expected);
        // What the credential-aware check makes of it — the thing every policy
        // keys on. (Not Passport::actingAs: that flips the default guard to
        // `api`, which would derail the next iteration's browser-side calls.)
        $user->withAccessToken(new AccessToken(['oauth_user_id' => $user->id, 'oauth_scopes' => $stored->scopes]));
        expect($user->isModerator())->toBe($type === 'm' && $tick);
        $user->withAccessToken(null);

        // The grant survives a refresh unchanged, and shows in the connections list.
        $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => $tokens['refresh_token']])->assertOk();
        expect($user->tokens()->where('revoked', false)->first()->scopes)->toBe($expected);
        expect($this->actingAs($user)->getJson('/oauth/connections')->json('apps.0.scopes'))->toBe($expected);
    }
});

test('an assistant can only ever be granted the default scope', function () {
    [, $challenge] = pkcePair();
    $client = oauthClient();

    $this->actingAs(consentUser('a'))
        ->get('/oauth/authorize?'.authorizeQuery($client, $challenge, ['scope' => 'mcp:use '.User::MODERATE_SCOPE]))
        ->assertStatus(400);
});

// ── the full flow ─────────────────────────────────────────────────────────

test('approve, exchange the code with PKCE, and the token opens the mcp endpoint', function () {
    [$verifier, $challenge] = pkcePair();
    $user = consentUser('a');
    $client = oauthClient();

    $code = approveAndGetCode($this, $user, $client, $challenge);

    $tokens = exchangeCode($this, $client, $code, $verifier)->assertOk()->json();
    expect($tokens['token_type'])->toBe('Bearer');
    expect($tokens['expires_in'])->toBe(3600);
    expect($tokens['refresh_token'] ?? '')->not->toBe('');

    mcpCall($this, $tokens['access_token'])->assertOk();

    // The grant carries mcp:use and nothing more — even for an admin.
    $stored = $user->tokens()->first();
    expect($stored->scopes)->toBe(['mcp:use']);
    expect($stored->name)->toBeNull();
});

test('the code exchange fails without the PKCE verifier, or with the wrong one', function () {
    [$verifier, $challenge] = pkcePair();
    $client = oauthClient();

    $code = approveAndGetCode($this, consentUser(), $client, $challenge);
    exchangeCode($this, $client, $code, null)->assertStatus(400);

    $code = approveAndGetCode($this, consentUser(), $client, $challenge);
    exchangeCode($this, $client, $code, 'not-the-verifier-'.Str::random(40))->assertStatus(400);
});

test('an authorization code can only be redeemed once', function () {
    [$verifier, $challenge] = pkcePair();
    $client = oauthClient();
    $code = approveAndGetCode($this, consentUser(), $client, $challenge);

    exchangeCode($this, $client, $code, $verifier)->assertOk();
    exchangeCode($this, $client, $code, $verifier)->assertStatus(400);
});

test('cancelling sends the browser back with access_denied and the state', function () {
    [, $challenge] = pkcePair();
    $user = consentUser();
    $client = oauthClient();

    $this->actingAs($user)->get('/oauth/authorize?'.authorizeQuery($client, $challenge))->assertOk();
    $response = $this->actingAs($user)->delete('/oauth/authorize', [
        'auth_token' => session('authToken'),
        'state' => 'st4te',
        'client_id' => $client->getKey(),
    ])->assertRedirect();

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['error'] ?? null)->toBe('access_denied');
    expect($query['state'] ?? null)->toBe('st4te');
    expect($user->tokens()->count())->toBe(0);
});

test('refreshing rotates the tokens and retires the old ones', function () {
    [$verifier, $challenge] = pkcePair();
    $client = oauthClient();
    $first = exchangeCode($this, $client, approveAndGetCode($this, consentUser(), $client, $challenge), $verifier)->json();

    $second = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $client->getKey(),
        'refresh_token' => $first['refresh_token'],
    ])->assertOk()->json();

    expect($second['access_token'])->not->toBe($first['access_token']);
    mcpCall($this, $second['access_token'])->assertOk();

    // The old access token is revoked with the refresh, and the old refresh
    // token cannot be replayed.
    mcpCall($this, $first['access_token'])->assertStatus(401);
    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $client->getKey(),
        'refresh_token' => $first['refresh_token'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('the user can see the connection and revoke it, after which the token is dead', function () {
    [$verifier, $challenge] = pkcePair();
    $user = consentUser();
    $client = oauthClient(['http://localhost:9000/callback'], 'Claude Code');
    $tokens = exchangeCode($this, $client, approveAndGetCode($this, $user, $client, $challenge), $verifier)->json();

    $list = $this->actingAs($user)->getJson('/oauth/connections')->assertOk()->json('apps');
    expect($list)->toHaveCount(1);
    expect($list[0]['app'])->toBe('Claude Code');
    expect($list[0]['scopes'])->toBe(['mcp:use']);
    expect(json_encode($list))->not->toContain($tokens['access_token']);

    // An API key is not a connection, and does not show here.
    mcpToken($user);
    expect($this->actingAs($user)->getJson('/oauth/connections')->json('apps'))->toHaveCount(1);

    // Not someone else's to revoke.
    $this->actingAs(consentUser())->deleteJson('/oauth/connections/'.$list[0]['id'])->assertStatus(404);
    mcpCall($this, $tokens['access_token'])->assertOk();

    $this->actingAs($user)->deleteJson('/oauth/connections/'.$list[0]['id'])->assertOk();
    mcpCall($this, $tokens['access_token'])->assertStatus(401);

    // …and the refresh token went with it.
    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    expect($this->actingAs($user)->getJson('/oauth/connections')->json('apps'))->toHaveCount(0);
});

test('a dormant connection — access token expired, refresh token alive — is still listed and can still be cut off', function () {
    // Between uses every connection looks like this: the hour-long access
    // token has lapsed and only the 30-day refresh token remains. Keyed on
    // the access token alone, the list went blank and Disconnect had
    // nothing to revoke while the app could still mint a fresh token.
    [$verifier, $challenge] = pkcePair();
    $user = consentUser();
    $client = oauthClient(['http://localhost:9000/callback'], 'Claude');
    $tokens = exchangeCode($this, $client, approveAndGetCode($this, $user, $client, $challenge), $verifier)->json();
    $user->tokens()->update(['expires_at' => now()->subMinute()]);

    $list = $this->actingAs($user)->getJson('/oauth/connections')->json('apps');
    expect($list)->toHaveCount(1);
    expect((string) $list[0]['expires_at'])->not->toBe('');

    $this->actingAs($user)->deleteJson('/oauth/connections/'.$list[0]['id'])->assertOk();
    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token', 'client_id' => $client->getKey(), 'refresh_token' => $tokens['refresh_token'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    expect($this->actingAs($user)->getJson('/oauth/connections')->json('apps'))->toHaveCount(0);
});

// ── the scope model ───────────────────────────────────────────────────────

test('moderator powers follow the credential, not just the account', function () {
    $admin = User::factory()->create(['type' => 'a']);

    // A web session: the role is what it always was.
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isModerator())->toBeTrue();

    // An assistant's grant: mcp:use only, so no moderator powers.
    Passport::actingAs($admin, ['mcp:use']);
    expect($admin->isAdmin())->toBeFalse();
    expect($admin->isModerator())->toBeFalse();

    // A key minted with moderator powers on purpose.
    Passport::actingAs($admin, ['mcp:use', User::MODERATE_SCOPE]);
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isModerator())->toBeTrue();

    // The scope does nothing for an account that is not a moderator.
    $user = User::factory()->create(['type' => 'u']);
    Passport::actingAs($user, ['mcp:use', User::MODERATE_SCOPE]);
    expect($user->isModerator())->toBeFalse();
});
