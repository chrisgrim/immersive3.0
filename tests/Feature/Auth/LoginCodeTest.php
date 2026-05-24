<?php

use App\Mail\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    Cache::flush();
});

test('sendCode creates a new user, caches a 6-digit code, and emails it', function () {
    $response = $this->postJson('/login/code', ['email' => 'new@example.com']);

    $response->assertOk()
        ->assertJsonPath('email', 'new@example.com')
        ->assertJsonStructure(['message', 'email']);

    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();

    $cached = Cache::get('login_code_new@example.com');
    expect($cached)->not->toBeNull()
        ->and($cached['code'])->toMatch('/^\d{6}$/');

    Mail::assertSent(LoginCode::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

test('sendCode reuses an existing user', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->postJson('/login/code', ['email' => 'existing@example.com'])->assertOk();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
    expect(User::where('email', 'existing@example.com')->first()->id)->toBe($user->id);
});

test('sendCode rejects invalid email', function () {
    $this->postJson('/login/code', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

test('sendCode blocks after 5 attempts in an hour', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/login/code', ['email' => 'spammer@example.com'])->assertOk();
    }

    $this->postJson('/login/code', ['email' => 'spammer@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertSent(LoginCode::class, 5);
});

test('verify logs the user in with a correct code', function () {
    $user = User::factory()->unverified()->create(['email' => 'verify@example.com']);
    Cache::put('login_code_verify@example.com', ['code' => '123456', 'user_id' => $user->id], 60);

    $response = $this->postJson('/login/verify', [
        'email' => 'verify@example.com',
        'code' => '123456',
    ]);

    $response->assertOk()->assertJsonPath('redirect', '/');
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect(Cache::has('login_code_verify@example.com'))->toBeFalse();
});

test('verify rejects an incorrect code and does not log in', function () {
    $user = User::factory()->create(['email' => 'wrong@example.com']);
    Cache::put('login_code_wrong@example.com', ['code' => '111111', 'user_id' => $user->id], 60);

    $this->postJson('/login/verify', ['email' => 'wrong@example.com', 'code' => '999999'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->assertGuest();
});

test('verify rejects when no code has been issued', function () {
    User::factory()->create(['email' => 'noissue@example.com']);

    $this->postJson('/login/verify', ['email' => 'noissue@example.com', 'code' => '123456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->assertGuest();
});

test('verify blocks after 10 failed attempts in 15 minutes', function () {
    $user = User::factory()->create(['email' => 'brute@example.com']);
    Cache::put('login_code_brute@example.com', ['code' => '111111', 'user_id' => $user->id], 60);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/login/verify', ['email' => 'brute@example.com', 'code' => '000000'])
            ->assertStatus(422);
    }

    $this->postJson('/login/verify', ['email' => 'brute@example.com', 'code' => '111111'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    $this->assertGuest();
});

test('verify requires a 6-character code', function () {
    User::factory()->create(['email' => 'shortcode@example.com']);

    $this->postJson('/login/verify', ['email' => 'shortcode@example.com', 'code' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('logout ends the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertNoContent();
    $this->assertGuest();
});
