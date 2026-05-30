<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

// SocialAuthController authenticates via Google / GitHub / Apple. We mock the
// Socialite facade so no real OAuth happens, and Http::fake() GitHub's
// /user/emails endpoint. Each callback either creates a new user (type 'g',
// newsletter_type 'n', email_verified_at set) or updates an existing one,
// then logs in and redirect()->intended('/').

/**
 * Build a fake Socialite "Two" user with the given raw claims, so the
 * controller can read $user->user['email_verified'] etc.
 */
function fakeSocialiteUser(array $overrides = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->map([
        'id' => $overrides['id'] ?? 'provider-id-123',
        'name' => $overrides['name'] ?? 'Ada Lovelace',
        'email' => $overrides['email'] ?? 'ada@example.com',
        'avatar' => $overrides['avatar'] ?? null,
    ]);
    $user->user = $overrides['raw'] ?? ['email_verified' => true];
    $user->token = $overrides['token'] ?? 'gh-token-abc';

    return $user;
}

// ----------------------------------------------------------------------------
// Google
// ----------------------------------------------------------------------------

test('google callback redirects to login when there is no code', function () {
    // note: the controller short-circuits before touching Socialite when the
    // OAuth `code` query param is absent (user cancelled / direct hit).
    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

test('google callback rejects an unverified google email', function () {
    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'unverified@example.com',
            'raw' => ['email_verified' => false],
        ]));

    $this->get('/auth/google/callback?code=abc')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
    expect(User::where('email', 'unverified@example.com')->exists())->toBeFalse();
});

test('google callback rejects when the email_verified claim is missing', function () {
    // note: missing claim defaults to false (defense-in-depth), so it's rejected.
    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'noclaim@example.com',
            'raw' => [],
        ]));

    $this->get('/auth/google/callback?code=abc')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
    expect(User::where('email', 'noclaim@example.com')->exists())->toBeFalse();
});

test('google callback creates a new user with general defaults and logs them in', function () {
    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'google-999',
            'name' => 'New Googler',
            'email' => 'newgoogler@example.com',
            'raw' => ['email_verified' => true],
        ]));

    $this->get('/auth/google/callback?code=abc')
        ->assertRedirect('/');

    $user = User::where('email', 'newgoogler@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->provider)->toBe('google');
    expect($user->provider_id)->toBe('google-999');
    expect($user->type)->toBe('g');
    expect($user->newsletter_type)->toBe('n');
    expect($user->silence)->toBe('n');
    expect($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('google callback stores the avatar on a new user when present', function () {
    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'avatar@example.com',
            'avatar' => 'https://cdn.example.com/pic.png',
            'raw' => ['email_verified' => true],
        ]));

    $this->get('/auth/google/callback?code=abc')->assertRedirect('/');

    $user = User::where('email', 'avatar@example.com')->first();
    expect($user->largeImagePath)->toBe('https://cdn.example.com/pic.png');
    expect($user->thumbImagePath)->toBe('https://cdn.example.com/pic.png');
});

test('google callback links an existing user and logs them in', function () {
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'provider' => null,
        'provider_id' => null,
    ]);

    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'google-link-1',
            'email' => 'existing@example.com',
            'raw' => ['email_verified' => true],
        ]));

    $this->get('/auth/google/callback?code=abc')->assertRedirect('/');

    $existing->refresh();
    expect($existing->provider)->toBe('google');
    expect($existing->provider_id)->toBe('google-link-1');
    expect($existing->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($existing);
    // No duplicate row created.
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
});

test('google callback redirects to login when socialite throws', function () {
    Socialite::shouldReceive('driver->stateless->user')
        ->andThrow(new \Exception('OAuth blew up'));

    $this->get('/auth/google/callback?code=abc')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
});

// ----------------------------------------------------------------------------
// GitHub
// ----------------------------------------------------------------------------

test('github callback rejects when no primary+verified email exists', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'gh-1',
            'email' => 'ghdefault@example.com',
            'token' => 'gh-token-1',
        ]));

    // Primary but NOT verified -> controller refuses.
    Http::fake([
        'api.github.com/user/emails' => Http::response([
            ['email' => 'ghdefault@example.com', 'primary' => true, 'verified' => false],
        ], 200),
    ]);

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
    expect(User::where('email', 'ghdefault@example.com')->exists())->toBeFalse();
});

test('github callback rejects when the emails endpoint returns non-2xx', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'ghfail@example.com',
            'token' => 'gh-token-2',
        ]));

    Http::fake([
        'api.github.com/user/emails' => Http::response([], 401),
    ]);

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
});

test('github callback creates a new user bound to the verified primary email', function () {
    // note: the controller binds the account to the verified primary email from
    // /user/emails, NOT the default email Socialite returned.
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'gh-new-1',
            'name' => 'Octo Cat',
            'email' => 'public@example.com',
            'avatar' => 'https://avatars.github.com/u/1',
            'token' => 'gh-token-3',
        ]));

    Http::fake([
        'api.github.com/user/emails' => Http::response([
            ['email' => 'public@example.com', 'primary' => false, 'verified' => true],
            ['email' => 'verified-primary@example.com', 'primary' => true, 'verified' => true],
        ], 200),
    ]);

    $this->get('/auth/github/callback')->assertRedirect('/');

    expect(User::where('email', 'public@example.com')->exists())->toBeFalse();
    $user = User::where('email', 'verified-primary@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->provider)->toBe('github');
    expect($user->provider_id)->toBe('gh-new-1');
    expect($user->type)->toBe('g');
    expect($user->newsletter_type)->toBe('n');
    expect($user->largeImagePath)->toBe('https://avatars.github.com/u/1');
    $this->assertAuthenticatedAs($user);
});

test('github callback falls back to nickname when name is null', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(tap(fakeSocialiteUser([
            'id' => 'gh-nick-1',
            'email' => 'whatever@example.com',
            'token' => 'gh-token-4',
        ]), function (SocialiteUser $u) {
            $u->name = null;
            $u->nickname = 'octocat';
        }));

    Http::fake([
        'api.github.com/user/emails' => Http::response([
            ['email' => 'nick@example.com', 'primary' => true, 'verified' => true],
        ], 200),
    ]);

    $this->get('/auth/github/callback')->assertRedirect('/');

    $user = User::where('email', 'nick@example.com')->first();
    expect($user->name)->toBe('octocat');
});

test('github callback links an existing user found by the verified email', function () {
    $existing = User::factory()->create([
        'email' => 'ghexisting@example.com',
        'provider' => null,
    ]);

    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'gh-link-1',
            'email' => 'something-else@example.com',
            'token' => 'gh-token-5',
        ]));

    Http::fake([
        'api.github.com/user/emails' => Http::response([
            ['email' => 'ghexisting@example.com', 'primary' => true, 'verified' => true],
        ], 200),
    ]);

    $this->get('/auth/github/callback')->assertRedirect('/');

    $existing->refresh();
    expect($existing->provider)->toBe('github');
    expect($existing->provider_id)->toBe('gh-link-1');
    $this->assertAuthenticatedAs($existing);
    expect(User::where('email', 'ghexisting@example.com')->count())->toBe(1);
});

test('github callback redirects to login when socialite throws', function () {
    Socialite::shouldReceive('driver->user')
        ->andThrow(new \Exception('gh oauth failure'));

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
});

// ----------------------------------------------------------------------------
// Redirect entry points (smoke: they should issue an OAuth redirect, not 500)
// ----------------------------------------------------------------------------

test('google redirect issues a redirect response', function () {
    Socialite::shouldReceive('driver->with->stateless->redirect')
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $this->get('/auth/google')->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('github redirect issues a redirect response', function () {
    Socialite::shouldReceive('driver->scopes->redirect')
        ->andReturn(redirect('https://github.com/login/oauth/authorize'));

    $this->get('/auth/github')->assertRedirect('https://github.com/login/oauth/authorize');
});

// ----------------------------------------------------------------------------
// Apple
// ----------------------------------------------------------------------------
// note: Apple's callback is a POST (form_post response mode) and is CSRF-exempt.
// The controller reads Socialite::driver('apple')->user(); we mock that. The real
// OAuth handshake needs live Apple credentials (APPLE_CLIENT_ID/SECRET/REDIRECT)
// and a deployed HTTPS environment, so only the callback logic is covered here.

test('apple redirect issues a redirect response', function () {
    Socialite::shouldReceive('driver->scopes->redirect')
        ->andReturn(redirect('https://appleid.apple.com/auth/authorize'));

    $this->get('/auth/apple')->assertRedirect('https://appleid.apple.com/auth/authorize');
});

test('apple callback creates a new user with general defaults and logs them in', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'apple-001',
            'name' => 'Tim Appleseed',
            'email' => 'newapple@example.com',
            'raw' => ['email_verified' => true],
        ]));

    $this->post('/auth/apple/callback')->assertRedirect('/');

    $user = User::where('email', 'newapple@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->provider)->toBe('apple');
    expect($user->provider_id)->toBe('apple-001');
    expect($user->type)->toBe('g');
    expect($user->newsletter_type)->toBe('n');
    expect($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('apple callback accepts the historical string "true" email_verified claim', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'stringclaim@example.com',
            'raw' => ['email_verified' => 'true'],
        ]));

    $this->post('/auth/apple/callback')->assertRedirect('/');

    expect(User::where('email', 'stringclaim@example.com')->exists())->toBeTrue();
});

test('apple callback rejects an unverified apple email', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'email' => 'unverified-apple@example.com',
            'raw' => ['email_verified' => false],
        ]));

    $this->post('/auth/apple/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('error');

    expect(auth()->check())->toBeFalse();
    expect(User::where('email', 'unverified-apple@example.com')->exists())->toBeFalse();
});

test('apple callback links an existing user and logs them in', function () {
    $existing = User::factory()->create([
        'email' => 'existing-apple@example.com',
        'provider' => null,
        'provider_id' => null,
    ]);

    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeSocialiteUser([
            'id' => 'apple-link-1',
            'email' => 'existing-apple@example.com',
            'raw' => ['email_verified' => true],
        ]));

    $this->post('/auth/apple/callback')->assertRedirect('/');

    $existing->refresh();
    expect($existing->provider)->toBe('apple');
    expect($existing->provider_id)->toBe('apple-link-1');
    $this->assertAuthenticatedAs($existing);
});
