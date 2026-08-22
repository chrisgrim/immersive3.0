<?php

use App\Mail\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

// EmailVerificationController powers the "change my email" flow:
//   POST /users/email/verify  -> sendVerificationCode  (auth)
//   POST /users/email/confirm -> verifyCode            (auth)
// A 6-digit code is cached for the user for 10 minutes and mailed to the NEW
// address; confirming with the matching code+email updates the user's email.

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create(['email' => 'current@example.com']);
});

function verificationKey(User $user): string
{
    return 'email_verification_'.$user->id;
}

// ----------------------------------------------------------------------------
// sendVerificationCode
// ----------------------------------------------------------------------------

test('sendVerificationCode caches a 6-digit code and emails it to the new address', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'new@example.com'])
        ->assertOk()
        ->assertJsonPath('message', 'Verification code sent');

    $cached = Cache::get(verificationKey($this->user));
    expect($cached)->not->toBeNull();
    expect($cached['email'])->toBe('new@example.com');
    expect($cached['code'])->toMatch('/^\d{6}$/');

    Mail::assertSent(EmailVerificationCode::class, function ($mail) use ($cached) {
        return $mail->hasTo('new@example.com') && $mail->code === $cached['code'];
    });
});

test('sendVerificationCode rejects an email already taken by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'taken@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    expect(Cache::get(verificationKey($this->user)))->toBeNull();
    Mail::assertNothingSent();
});

test('sendVerificationCode allows the user to re-verify their own current email', function () {
    // note: the unique rule ignores the authenticated user's own id, so
    // re-submitting their existing address is permitted.
    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'current@example.com'])
        ->assertOk();

    Mail::assertSent(EmailVerificationCode::class, fn ($mail) => $mail->hasTo('current@example.com'));
});

test('sendVerificationCode requires a valid email', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    $this->actingAs($this->user)
        ->postJson('/users/email/verify', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('sendVerificationCode requires authentication', function () {
    // note: route is behind `auth`; guest JSON requests get 401.
    $this->postJson('/users/email/verify', ['email' => 'new@example.com'])
        ->assertStatus(401);
});

test('the email-verification endpoints are rate limited', function () {
    // throttle:6,1 caps requests per minute, blocking the brute-force / DoS vector
    // (each verify guess would otherwise be an unthrottled DB-cache round-trip).
    // Flushed first: phpunit.xml's CACHE_STORE=array is silently ignored
    // (config/cache.php still reads the legacy CACHE_DRIVER key), so this
    // throttle actually hits real local Redis — without flushing, its
    // counter can carry over from an earlier test/run within the same
    // rate-limit window and make this test flake.
    Cache::flush();

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($this->user)
            ->postJson('/users/email/verify', ['email' => 'new@example.com'])
            ->assertOk();
    }

    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'new@example.com'])
        ->assertStatus(429);
});

// ----------------------------------------------------------------------------
// verifyCode
// ----------------------------------------------------------------------------

test('verifyCode with the correct cached code updates the email and clears the cache', function () {
    Cache::put(verificationKey($this->user), [
        'code' => '123456',
        'email' => 'changed@example.com',
    ], now()->addMinutes(10));

    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'changed@example.com',
            'code' => '123456',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Email updated successfully');

    expect($this->user->fresh()->email)->toBe('changed@example.com');
    expect(Cache::get(verificationKey($this->user)))->toBeNull();
});

test('verifyCode marks the email verified, since receiving the code there is proof of ownership', function () {
    // Regression test: this previously updated the email without touching
    // email_verified_at, so a never-verified (or previously verified, now
    // stale) user would show as "not verified" despite having just proven
    // ownership of the new address via the code.
    $this->user->update(['email_verified_at' => null]);

    Cache::put(verificationKey($this->user), [
        'code' => '123456',
        'email' => 'changed@example.com',
    ], now()->addMinutes(10));

    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'changed@example.com',
            'code' => '123456',
        ])
        ->assertOk();

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

test('verifyCode with a wrong code returns 422 and leaves the email unchanged', function () {
    Cache::put(verificationKey($this->user), [
        'code' => '123456',
        'email' => 'changed@example.com',
    ], now()->addMinutes(10));

    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'changed@example.com',
            'code' => '999999',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid verification code');

    expect($this->user->fresh()->email)->toBe('current@example.com');
    // Cache is NOT cleared on a single failed attempt.
    expect(Cache::get(verificationKey($this->user)))->not->toBeNull();
});

test('verifyCode burns the code after 5 failed attempts', function () {
    Cache::put(verificationKey($this->user), [
        'code' => '123456',
        'email' => 'changed@example.com',
    ], now()->addMinutes(10));

    // Four wrong attempts are rejected but keep the code alive.
    for ($i = 0; $i < 4; $i++) {
        $this->actingAs($this->user)
            ->postJson('/users/email/confirm', ['email' => 'changed@example.com', 'code' => '000000'])
            ->assertStatus(422);
    }
    expect(Cache::get(verificationKey($this->user)))->not->toBeNull();

    // The 5th wrong attempt burns the code so it can't be ground down further.
    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', ['email' => 'changed@example.com', 'code' => '000000'])
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too many invalid attempts. Please request a new code.');

    expect(Cache::get(verificationKey($this->user)))->toBeNull();
    expect($this->user->fresh()->email)->toBe('current@example.com');
});

test('verifyCode fails when the submitted email does not match the cached email', function () {
    // note: the controller cross-checks both code AND the email the code was
    // issued for, so swapping the target email mid-flow is rejected.
    Cache::put(verificationKey($this->user), [
        'code' => '123456',
        'email' => 'changed@example.com',
    ], now()->addMinutes(10));

    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'someoneelse@example.com',
            'code' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid verification code');

    expect($this->user->fresh()->email)->toBe('current@example.com');
});

test('verifyCode fails when no code has been cached', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'changed@example.com',
            'code' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid verification code');

    expect($this->user->fresh()->email)->toBe('current@example.com');
});

test('verifyCode validates that the code is exactly 6 characters', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'changed@example.com',
            'code' => '123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('verifyCode requires email and code', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'code']);
});

test('verifyCode requires authentication', function () {
    $this->postJson('/users/email/confirm', [
        'email' => 'changed@example.com',
        'code' => '123456',
    ])->assertStatus(401);
});

// ----------------------------------------------------------------------------
// end-to-end: send then confirm
// ----------------------------------------------------------------------------

test('the full send-then-confirm flow updates the email', function () {
    $this->actingAs($this->user)
        ->postJson('/users/email/verify', ['email' => 'flow@example.com'])
        ->assertOk();

    $code = Cache::get(verificationKey($this->user))['code'];

    $this->actingAs($this->user)
        ->postJson('/users/email/confirm', [
            'email' => 'flow@example.com',
            'code' => $code,
        ])
        ->assertOk();

    expect($this->user->fresh()->email)->toBe('flow@example.com');
});
