<?php

use App\Mail\PasskeyChanged;
use App\Models\User;
use App\Support\Passkeys\PasskeyOffer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Passkeys\Passkey;
use Tests\Support\FakePasskeyAuthenticator;

beforeEach(function () {
    config([
        'passkeys.relying_party_id' => 'ei.test',
        'passkeys.allowed_origins' => ['https://ei.test'],
        'passkeys.user_handle_secret' => 'test-handle-secret',
    ]);
    Mail::fake();
});

/** Adds a passkey for $user through the real routes; returns the authenticator. */
function registerPasskey($test, User $user, string $name = 'Chrome on Mac'): FakePasskeyAuthenticator
{
    $authenticator = new FakePasskeyAuthenticator;

    $options = $test->actingAs($user)->getJson('/user/passkeys/options')->assertOk()->json('options');

    $test->postJson('/user/passkeys', [
        'name' => $name,
        'credential' => $authenticator->register($options),
    ])->assertOk()->assertJson(['status' => 'passkey-registered', 'name' => $name]);

    return $authenticator;
}

/** Signs out, then asks for login options as a guest would. */
function passkeyLoginOptions($test): array
{
    auth()->guard('web')->logout();
    $test->flushSession();

    return $test->getJson('/passkeys/login/options')->assertOk()->json('options');
}

// ---------------------------------------------------------------------------
// Adding a passkey
// ---------------------------------------------------------------------------

test('a signed-in user can add a passkey, and is emailed about it', function () {
    $user = User::factory()->create(['email' => 'pat@example.com']);

    $authenticator = registerPasskey($this, $user);

    $passkey = Passkey::where('user_id', $user->id)->sole();
    expect($passkey->name)->toBe('Chrome on Mac')
        ->and($passkey->credential_id)->toBe($authenticator->credentialIdBase64Url())
        ->and($passkey->authenticator)->toBe('Google Password Manager');

    Mail::assertQueued(PasskeyChanged::class, fn (PasskeyChanged $mail) => $mail->hasTo('pat@example.com')
        && $mail->change === PasskeyChanged::ADDED
        && $mail->passkeyName === 'Chrome on Mac');
});

test('registration options name the site and the account, and leave out existing passkeys only by id', function () {
    $user = User::factory()->create(['email' => 'pat@example.com', 'name' => 'Pat']);

    $options = $this->actingAs($user)->getJson('/user/passkeys/options')->assertOk()->json('options');

    expect($options['rp']['id'])->toBe('ei.test')
        ->and($options['user']['name'])->toBe('pat@example.com')
        ->and($options['user']['displayName'])->toBe('Pat')
        ->and($options['authenticatorSelection']['residentKey'])->toBe('required')
        ->and($options['authenticatorSelection']['userVerification'])->toBe('required')
        ->and($options['attestation'])->toBe('none');
});

test('guests cannot ask for registration options or save a passkey', function () {
    $this->getJson('/user/passkeys/options')->assertUnauthorized();
    $this->postJson('/user/passkeys', ['name' => 'x', 'credential' => []])->assertUnauthorized();
});

test('a passkey answer for a different challenge is refused', function () {
    $user = User::factory()->create();
    $authenticator = new FakePasskeyAuthenticator;

    $options = $this->actingAs($user)->getJson('/user/passkeys/options')->json('options');
    $options['challenge'] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    $this->postJson('/user/passkeys', [
        'name' => 'Chrome on Mac',
        'credential' => $authenticator->register($options),
    ])->assertUnprocessable();

    expect(Passkey::count())->toBe(0);
});

test('a passkey made for another site is refused', function () {
    $user = User::factory()->create();
    $authenticator = new FakePasskeyAuthenticator('evil.test', 'https://evil.test');

    $options = $this->actingAs($user)->getJson('/user/passkeys/options')->json('options');

    $this->postJson('/user/passkeys', [
        'name' => 'Chrome on Mac',
        'credential' => $authenticator->register($options),
    ])->assertUnprocessable();

    expect(Passkey::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Signing in
// ---------------------------------------------------------------------------

test('a guest signs in with a passkey and goes back to the page they came from', function () {
    $user = User::factory()->create();
    $authenticator = registerPasskey($this, $user);

    $options = passkeyLoginOptions($this);
    expect($options['rpId'])->toBe('ei.test')
        ->and($options['userVerification'])->toBe('required')
        ->and($options['allowCredentials'] ?? [])->toBe([]);

    session()->put('url.intended', url('/oauth/authorize?client_id=abc'));

    $response = $this->postJson('/passkeys/login', [
        'credential' => $authenticator->authenticate($options),
        'remember' => true,
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
    expect($response->json('redirect'))->toBe(url('/oauth/authorize?client_id=abc'))
        ->and($response->json('signals.rpId'))->toBe('ei.test')
        ->and($response->json('signals.allAcceptedCredentialIds'))->toBe([$authenticator->credentialIdBase64Url()])
        ->and($response->json('signals.name'))->toBe($user->email);
    expect(Passkey::sole()->last_used_at)->not->toBeNull();
});

test('a passkey login answer can only be used once', function () {
    $user = User::factory()->create();
    $authenticator = registerPasskey($this, $user);

    $answer = $authenticator->authenticate(passkeyLoginOptions($this));

    $this->postJson('/passkeys/login', ['credential' => $answer])->assertOk();

    auth()->guard('web')->logout();

    $this->postJson('/passkeys/login', ['credential' => $answer])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('credential');
    $this->assertGuest();
});

test('a signature from the wrong key does not sign anyone in', function () {
    $user = User::factory()->create();
    $authenticator = registerPasskey($this, $user);

    $options = passkeyLoginOptions($this);
    $answer = $authenticator->authenticate($options);
    // Same credential id, signed by a different key.
    $impostor = (new FakePasskeyAuthenticator)->authenticate($options);
    $answer['response']['signature'] = $impostor['response']['signature'];

    $this->postJson('/passkeys/login', ['credential' => $answer])->assertUnprocessable();
    $this->assertGuest();
});

test('a removed passkey answers unknown_credential so the page can tell the password manager', function () {
    $user = User::factory()->create();
    $authenticator = registerPasskey($this, $user);
    Passkey::query()->delete();

    $this->postJson('/passkeys/login', ['credential' => $authenticator->authenticate(passkeyLoginOptions($this))])
        ->assertUnprocessable()
        ->assertJson([
            'code' => 'unknown_credential',
            'credential_id' => $authenticator->credentialIdBase64Url(),
        ]);

    $this->assertGuest();
});

test('a passkey answer from another address of the site (www) does not sign in', function () {
    $user = User::factory()->create();
    registerPasskey($this, $user);

    $options = passkeyLoginOptions($this);
    $fromWww = (new FakePasskeyAuthenticator('ei.test', 'https://www.ei.test'))->authenticate($options);

    $this->postJson('/passkeys/login', ['credential' => $fromWww])->assertUnprocessable();
    $this->assertGuest();
});

test('signed-in browsers are sent away from the passkey login routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/passkeys/login/options')->assertRedirect();
});

test('passkey login options are rate limited per connection', function () {
    Cache::flush();

    foreach (range(1, 30) as $ignored) {
        $this->getJson('/passkeys/login/options')->assertOk();
    }

    $this->getJson('/passkeys/login/options')->assertTooManyRequests();
});

test('adding and removing passkeys is rate limited per person', function () {
    Cache::flush();
    $user = User::factory()->create();

    foreach (range(1, 10) as $ignored) {
        $this->actingAs($user)->getJson('/user/passkeys/options')->assertOk();
    }

    $this->actingAs($user)->getJson('/user/passkeys/options')->assertTooManyRequests();
});

test('an empty user handle secret falls back to the app key, never an empty key', function () {
    $before = [$_ENV['PASSKEYS_USER_HANDLE_SECRET'] ?? null, $_SERVER['PASSKEYS_USER_HANDLE_SECRET'] ?? null];
    $_ENV['PASSKEYS_USER_HANDLE_SECRET'] = $_SERVER['PASSKEYS_USER_HANDLE_SECRET'] = '';
    try {
        $config = require config_path('passkeys.php');
    } finally {
        [$_ENV['PASSKEYS_USER_HANDLE_SECRET'], $_SERVER['PASSKEYS_USER_HANDLE_SECRET']] = $before;
    }

    expect($config['user_handle_secret'])->toBe(config('app.key'))->not->toBe('');
});

// ---------------------------------------------------------------------------
// Removing
// ---------------------------------------------------------------------------

test('a user can remove their own passkey, and is emailed about it', function () {
    $user = User::factory()->create(['email' => 'pat@example.com']);
    registerPasskey($this, $user, 'Old phone');
    $passkey = Passkey::sole();

    $this->actingAs($user)->deleteJson("/user/passkeys/{$passkey->id}")->assertOk();

    expect(Passkey::count())->toBe(0);
    Mail::assertQueued(PasskeyChanged::class, fn (PasskeyChanged $mail) => $mail->change === PasskeyChanged::REMOVED
        && $mail->passkeyName === 'Old phone');
});

test('nobody can remove someone else\'s passkey', function () {
    $owner = User::factory()->create();
    registerPasskey($this, $owner);
    $passkey = Passkey::sole();

    $this->actingAs(User::factory()->create())->deleteJson("/user/passkeys/{$passkey->id}")->assertForbidden();

    expect(Passkey::count())->toBe(1);
});

test('deleting an account deletes its passkeys', function () {
    $user = User::factory()->create();
    registerPasskey($this, $user);

    $user->delete();

    expect(Passkey::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Login & security
// ---------------------------------------------------------------------------

test('login & security lists passkeys with their provider and logo', function () {
    $user = User::factory()->create();
    registerPasskey($this, $user);

    $data = $this->actingAs($user)->getJson('/api/account-settings/login-security')->assertOk()->json();

    expect($data['passkeys'])->toHaveCount(1)
        ->and($data['passkeys'][0]['name'])->toBe('Chrome on Mac')
        ->and($data['passkeys'][0]['authenticator'])->toBe('Google Password Manager')
        ->and($data['passkeys'][0]['icon']['light'])->toStartWith('data:image/svg+xml;base64,')
        ->and($data['passkeys'][0])->not->toHaveKey('credential')
        ->and($data['passkeySignals']['allAcceptedCredentialIds'])->toHaveCount(1)
        ->and($data['passkeysRecommended'])->toBeFalse();
});

test('passkeys are recommended to moderators and admins', function (string $type) {
    $user = User::factory()->create(['type' => $type]);

    $this->actingAs($user)->getJson('/api/account-settings/login-security')
        ->assertOk()
        ->assertJson(['passkeysRecommended' => true]);
})->with(['m', 'a']);

// ---------------------------------------------------------------------------
// "Sign in faster next time?"
// ---------------------------------------------------------------------------

test('an email code sign-in queues the passkey offer for someone without one', function () {
    $user = User::factory()->create(['email' => 'pat@example.com']);
    Cache::put('login_code_pat@example.com', ['code' => '123456', 'user_id' => $user->id], now()->addMinutes(15));

    $this->postJson('/login/verify', ['email' => 'pat@example.com', 'code' => '123456'])->assertOk();

    expect(session(PasskeyOffer::SESSION_KEY))->toBeTrue();
});

test('no offer for someone who already has a passkey', function () {
    $user = User::factory()->create(['email' => 'pat@example.com']);
    registerPasskey($this, $user);
    auth()->guard('web')->logout();
    $this->flushSession();
    Cache::put('login_code_pat@example.com', ['code' => '123456', 'user_id' => $user->id], now()->addMinutes(15));

    $this->postJson('/login/verify', ['email' => 'pat@example.com', 'code' => '123456'])->assertOk();

    expect(session(PasskeyOffer::SESSION_KEY))->toBeNull();
});

test('the next page shows the offer once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withSession([PasskeyOffer::SESSION_KEY => true])
        ->get('/')->assertOk()->assertSee('<vue-passkey-offer>', false);

    $this->get('/')->assertOk()->assertDontSee('<vue-passkey-offer>', false);
});

test('the offer never covers the connect-an-app consent page, it waits for the next page', function () {
    $this->actingAs(User::factory()->create())->withSession([PasskeyOffer::SESSION_KEY => true]);
    $this->app->instance('request', \Illuminate\Http\Request::create('/oauth/authorize'));

    expect(PasskeyOffer::take())->toBeFalse()
        ->and(session(PasskeyOffer::SESSION_KEY))->toBeTrue();
});
