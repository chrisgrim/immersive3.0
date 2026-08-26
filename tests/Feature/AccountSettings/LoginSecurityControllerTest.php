<?php

use App\Http\Controllers\AccountSettings\LoginSecurityController;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;

// ---------------------------------------------------------------------------
// RecordLoginHistory middleware
// ---------------------------------------------------------------------------

test('a users first authenticated request records a login history row', function () {
    $user = User::factory()->create();
    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(0);

    $this->actingAs($user)->get('/')->assertOk();

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(1);
});

test('a second request in the same session does not create a duplicate row', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->get('/')->assertOk();
    $this->get('/')->assertOk();

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(1);
});

test('a new session on the same device (device cookie) reuses the existing row instead of creating a new one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
    $original = LoginHistory::where('user_id', $user->id)->firstOrFail();
    // Read the plain device id straight off the row rather than the
    // response's Set-Cookie header — the response cookie is encrypted (the
    // default for any cookie not excluded from EncryptCookies), so the
    // stored plain value is what withCookie() below needs to resend it.
    $deviceCookie = $original->device_id;

    // Simulate the same browser coming back after its session expired and
    // restarted (see RecordLoginHistory's docblock) — the middleware only
    // keys off "have I already recorded this session," so forgetting that
    // one flag reproduces the same code path a real expired session hits.
    // The device cookie is what carries "this is the same browser" across
    // that gap, so it has to be resent explicitly here the way a real
    // browser would resend it automatically.
    session()->forget('login_history_id');

    $this->withCookie('ei_device', $deviceCookie)->get('/')->assertOk();

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(1);
    expect(LoginHistory::where('user_id', $user->id)->firstOrFail()->id)->toBe($original->id);
});

test('a device cookie surviving an ip change still merges into the same row', function () {
    // The device cookie is the dedup key now, not ip_address — a laptop
    // moving from home wifi to a coffee shop is still the same device.
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
    $original = LoginHistory::where('user_id', $user->id)->firstOrFail();
    // Read the plain device id straight off the row rather than the
    // response's Set-Cookie header — the response cookie is encrypted (the
    // default for any cookie not excluded from EncryptCookies), so the
    // stored plain value is what withCookie() below needs to resend it.
    $deviceCookie = $original->device_id;
    $original->update(['ip_address' => '203.0.113.5']); // pretend it was seen from elsewhere last time
    session()->forget('login_history_id');

    $this->withCookie('ei_device', $deviceCookie)->get('/')->assertOk();

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(1);
    expect(LoginHistory::where('user_id', $user->id)->firstOrFail()->id)->toBe($original->id);
});

test('two different devices sharing the same browser, platform, and ip stay as separate rows', function () {
    // Regression test (Codex caught this): matching on browser+platform+ip
    // alone would merge two genuinely different devices that happen to
    // share all three — e.g. two Macs on Chrome behind the same home
    // router — silently overwriting the first device's session_id and
    // making it disappear from Device History with no way to revoke it.
    // Each device gets its own randomly-generated cookie with no state
    // carried between them here, simulating exactly that scenario.
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
    session()->forget('login_history_id');
    $this->get('/')->assertOk(); // a fresh request with no device cookie sent — a "different" device

    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(2);
});

test('login history is capped at 20 rows per user', function () {
    $user = User::factory()->create();
    LoginHistory::factory()->count(25)->create(['user_id' => $user->id]);
    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(25);

    $this->actingAs($user)->get('/')->assertOk();

    // The 25 pre-existing rows plus this request's new one is pruned to 20.
    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(20);
});

test('pruning never deletes the row just inserted, even when timestamps tie', function () {
    // Regression test: pruning originally ordered by created_at (second
    // precision) alone — with 20+ rows sharing a timestamp, the row this
    // very request just inserted (and stored the id of in the session)
    // could be the one pruned, silently breaking that session's "already
    // recorded" check forever after. Ordering by id instead guarantees the
    // newest row (highest id) always survives.
    $user = User::factory()->create();
    $now = now();
    LoginHistory::factory()->count(20)->create(['user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);

    $this->actingAs($user)->get('/')->assertOk();

    $newHistoryId = session('login_history_id');
    expect($newHistoryId)->not->toBeNull();
    expect(LoginHistory::find($newHistoryId))->not->toBeNull();
    expect(LoginHistory::where('user_id', $user->id)->count())->toBe(20);
});

// ---------------------------------------------------------------------------
// GET /api/account-settings/login-security
// ---------------------------------------------------------------------------

test('guests are blocked from viewing login security', function () {
    $this->getJson('/api/account-settings/login-security')->assertUnauthorized();
});

test('a magic-code user sees the passwordless login method', function () {
    $user = User::factory()->create(['provider' => null]);

    $this->actingAs($user)
        ->getJson('/api/account-settings/login-security')
        ->assertOk()
        ->assertJson(['provider' => null]);
});

test('an oauth user sees their provider', function () {
    $user = User::factory()->create(['provider' => 'google', 'provider_id' => '123']);

    $this->actingAs($user)
        ->getJson('/api/account-settings/login-security')
        ->assertOk()
        ->assertJson(['provider' => 'google']);
});

test('devices only include the current users own login history, most recent first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    LoginHistory::factory()->create(['user_id' => $other->id]);
    // Ordering is by updated_at ("last seen"), not created_at — see
    // LoginSecurityController::show().
    $older = LoginHistory::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subDays(2)]);
    $newer = LoginHistory::factory()->create(['user_id' => $user->id, 'updated_at' => now()->subHour()]);

    $response = $this->actingAs($user)->getJson('/api/account-settings/login-security')->assertOk();

    $ids = collect($response->json('devices'))->pluck('id');
    expect($ids->all())->toBe([$newer->id, $older->id]);
});

test('the device matching the live session is flagged as current', function () {
    // Called at the PHP level rather than over HTTP: /api/* routes sit behind
    // auth:sanctum, not the web session middleware directly, and Pest's test
    // client doesn't reproduce the same-origin headers that make Sanctum's
    // EnsureFrontendRequestsAreStateful bridge a real session onto an API
    // request the way an actual browser's axios call does — so
    // $request->hasSession() is reliably false for a plain getJson() call
    // here even though it's true in production. This still exercises the
    // controller's real isCurrent comparison logic, just with a manually
    // attached session standing in for that bridge.
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk(); // records this session's LoginHistory row

    $history = LoginHistory::where('user_id', $user->id)->firstOrFail();
    expect($history->session_id)->not->toBeNull();

    $request = Request::create('/api/account-settings/login-security', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->setId($history->session_id);
    $request->setUserResolver(fn () => $user);

    $response = (new LoginSecurityController)->show($request);
    $data = json_decode($response->getContent(), true);

    expect(collect($data['devices'])->firstWhere('isCurrent', true))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// DELETE /api/account-settings/login-security/devices/{device}
// ---------------------------------------------------------------------------

test('guests cannot log out a device', function () {
    $device = LoginHistory::factory()->create();
    $this->deleteJson("/api/account-settings/login-security/devices/{$device->id}")->assertUnauthorized();
});

test('a user can log out one of their own devices', function () {
    $user = User::factory()->create();
    $device = LoginHistory::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/account-settings/login-security/devices/{$device->id}")
        ->assertOk();

    expect(LoginHistory::find($device->id))->toBeNull();
});

test('a user cannot log out another users device', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $device = LoginHistory::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->deleteJson("/api/account-settings/login-security/devices/{$device->id}")
        ->assertNotFound();

    expect(LoginHistory::find($device->id))->not->toBeNull();
});

test('logging out a device destroys its real session, not just the history row', function () {
    // The point of the feature. The existing test above only asserts the
    // LoginHistory row is gone, so reducing destroy() to a plain row delete
    // left the whole suite green — while the device it claimed to sign out
    // stayed logged in and simply vanished from Device History, with no way
    // left to reach it. That is the exact failure mode someone signing out a
    // lost or stolen laptop is relying on this not to have.
    $user = User::factory()->create();
    $handler = app('session')->driver()->getHandler();

    $sessionId = 'device-session-under-test';
    $handler->write($sessionId, serialize(['_token' => 'x']));
    expect($handler->read($sessionId))->not->toBe('');

    $device = LoginHistory::factory()->create([
        'user_id' => $user->id,
        'session_id' => $sessionId,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/account-settings/login-security/devices/{$device->id}")
        ->assertOk();

    expect($handler->read($sessionId))->toBe('');
    expect(LoginHistory::find($device->id))->toBeNull();
});
