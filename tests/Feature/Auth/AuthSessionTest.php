<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

// IMPORTANT QUIRK (see bugsFound): AuthenticatedSessionController@store (login)
// and RegisteredUserController@store (register) are NOT wired to any route.
// `php artisan route:list` shows only POST /logout -> destroy. So the only
// HTTP-reachable action here is logout; login/register are exercised by
// invoking the controller actions directly with a real request so the genuine
// code path (LoginRequest::authenticate, RateLimiter, Auth::login, Registered
// event) still runs.

/**
 * Build a real LoginRequest with body + an active session/container so the
 * controller's $request->session()->regenerate() and authenticate() work.
 */
function makeLoginRequest(array $body): LoginRequest
{
    $request = LoginRequest::create('/login', 'POST', $body);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => Auth::user());
    // Run authorization + validation exactly like the framework would.
    $request->validateResolved();

    return $request;
}

// ----------------------------------------------------------------------------
// AuthenticatedSessionController@store  (login)
// ----------------------------------------------------------------------------

test('login with valid credentials authenticates and returns 204', function () {
    $user = User::factory()->create([
        'email' => 'log@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $request = makeLoginRequest(['email' => 'log@example.com', 'password' => 'correct-horse']);
    $response = (new AuthenticatedSessionController)->store($request);

    expect($response->getStatusCode())->toBe(204);
    $this->assertAuthenticatedAs($user);
});

test('login with a wrong password throws a validation exception on email and does not authenticate', function () {
    User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    $request = makeLoginRequest(['email' => 'wrong@example.com', 'password' => 'nope']);

    try {
        (new AuthenticatedSessionController)->store($request);
        $this->fail('Expected ValidationException for bad credentials.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('email');
    }

    expect(Auth::check())->toBeFalse();
});

test('login validation requires email and password', function () {
    // note: LoginRequest rules require both fields; validateResolved() throws
    // before authenticate() is ever reached.
    try {
        makeLoginRequest(['email' => '', 'password' => '']);
        $this->fail('Expected ValidationException for missing fields.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['email', 'password']);
    }
});

test('login locks out after 5 failed attempts and fires the Lockout event', function () {
    Event::fake([Lockout::class]);

    User::factory()->create([
        'email' => 'lockme@example.com',
        'password' => Hash::make('correct-horse'),
    ]);

    // 5 failures register 5 RateLimiter hits without yet tripping the limiter.
    for ($i = 0; $i < 5; $i++) {
        $request = makeLoginRequest(['email' => 'lockme@example.com', 'password' => 'bad']);
        try {
            (new AuthenticatedSessionController)->store($request);
        } catch (ValidationException $e) {
            // expected: bad credentials
        }
    }

    // The 6th attempt should be rate limited, even with the CORRECT password.
    $request = makeLoginRequest(['email' => 'lockme@example.com', 'password' => 'correct-horse']);
    try {
        (new AuthenticatedSessionController)->store($request);
        $this->fail('Expected lockout ValidationException.');
    } catch (ValidationException $e) {
        // The throttle message is keyed on email and mentions seconds.
        expect($e->errors())->toHaveKey('email');
        expect($e->errors()['email'][0])->toContain('seconds');
    }

    expect(Auth::check())->toBeFalse();
    Event::assertDispatched(Lockout::class);
});

// ----------------------------------------------------------------------------
// AuthenticatedSessionController@destroy  (logout)  -- the ONLY routed action
// ----------------------------------------------------------------------------

test('logout returns 204 and logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertNoContent();

    $this->assertGuest();
});

test('logout requires authentication', function () {
    // note: /logout is behind the `auth` middleware; a guest is redirected to login.
    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

// ----------------------------------------------------------------------------
// RegisteredUserController@store  (register)
// ----------------------------------------------------------------------------

/**
 * Build a plain Request bound as the app's current request, so the register
 * controller's $request->validate(), Auth::login() and session calls resolve.
 */
function makeRegisterRequest(array $body): Request
{
    $request = Request::create('/register', 'POST', $body);
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);

    return $request;
}

test('register creates a user, fires Registered, logs them in, and returns 204', function () {
    Event::fake([Registered::class]);

    $request = makeRegisterRequest([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'Sup3r-Secret-Pw!',
        'password_confirmation' => 'Sup3r-Secret-Pw!',
    ]);

    $response = (new RegisteredUserController)->store($request);

    expect($response->getStatusCode())->toBe(204);

    $user = User::where('email', 'grace@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Grace Hopper');
    expect(Hash::check('Sup3r-Secret-Pw!', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class, fn ($e) => $e->user->is($user));
});

test('register rejects a duplicate email', function () {
    User::factory()->create(['email' => 'dupe@example.com']);

    $request = makeRegisterRequest([
        'name' => 'Someone',
        'email' => 'dupe@example.com',
        'password' => 'Sup3r-Secret-Pw!',
        'password_confirmation' => 'Sup3r-Secret-Pw!',
    ]);

    try {
        (new RegisteredUserController)->store($request);
        $this->fail('Expected ValidationException for duplicate email.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('email');
    }
});

test('register rejects an unconfirmed password', function () {
    $request = makeRegisterRequest([
        'name' => 'Mismatch',
        'email' => 'mismatch@example.com',
        'password' => 'Sup3r-Secret-Pw!',
        'password_confirmation' => 'different-Pw!',
    ]);

    try {
        (new RegisteredUserController)->store($request);
        $this->fail('Expected ValidationException for unconfirmed password.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('password');
    }

    expect(User::where('email', 'mismatch@example.com')->exists())->toBeFalse();
});

test('register rejects a weak password', function () {
    // note: Password::defaults() enforces a minimum length (8) by default.
    $request = makeRegisterRequest([
        'name' => 'Weak',
        'email' => 'weak@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    try {
        (new RegisteredUserController)->store($request);
        $this->fail('Expected ValidationException for weak password.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('password');
    }

    expect(User::where('email', 'weak@example.com')->exists())->toBeFalse();
});

test('register requires a non-uppercase email (lowercase rule)', function () {
    // note: the email rule includes `lowercase`, so a mixed-case address fails
    // validation rather than being silently normalised.
    $request = makeRegisterRequest([
        'name' => 'Caps',
        'email' => 'Caps@Example.com',
        'password' => 'Sup3r-Secret-Pw!',
        'password_confirmation' => 'Sup3r-Secret-Pw!',
    ]);

    try {
        (new RegisteredUserController)->store($request);
        $this->fail('Expected ValidationException for non-lowercase email.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('email');
    }

    expect(User::where('email', 'Caps@Example.com')->exists())->toBeFalse();
});

afterEach(function () {
    RateLimiter::clear('lockme@example.com|127.0.0.1');
});
