<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The passwordless code login used to answer with a hard-coded redirect to
 * "/", which broke every flow that sends a guest to sign in and expects them
 * back — the OAuth consent screen above all. Social sign-in already honoured
 * the intended URL; now the code login does too.
 */
function primeLoginCode(User $user, string $code = '123456'): void
{
    Cache::put("login_code_{$user->email}", ['code' => $code, 'user_id' => $user->id], now()->addMinutes(10));
}

test('verifying a login code sends the user back where they were going', function () {
    $user = User::factory()->create();
    primeLoginCode($user);
    $intended = url('/oauth/authorize?client_id=abc&state=xyz');

    $this->withSession(['url.intended' => $intended])
        ->postJson('/login/verify', ['email' => $user->email, 'code' => '123456'])
        ->assertOk()
        ->assertJsonPath('redirect', $intended);

    $this->assertAuthenticatedAs($user);
});

test('verifying a login code with nowhere to go back to lands on the home page', function () {
    $user = User::factory()->create();
    primeLoginCode($user);

    $this->postJson('/login/verify', ['email' => $user->email, 'code' => '123456'])
        ->assertOk()
        ->assertJsonPath('redirect', url('/'));
});
