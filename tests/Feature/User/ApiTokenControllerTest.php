<?php

use App\Models\User;

/**
 * The API-token page is gated: moderators/admins always, everyone else only
 * once services.mcp.token_ui_public is flipped on.
 */
function verifiedUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

test('regular users cannot reach the token page while the flag is off', function () {
    config(['services.mcp.token_ui_public' => false]);

    $this->actingAs(verifiedUser())->get('/settings/api-tokens')->assertStatus(403);
    $this->actingAs(verifiedUser())->postJson('/settings/api-tokens', ['name' => 'x'])->assertStatus(403);
});

test('moderators can reach the token page while the flag is off', function () {
    config(['services.mcp.token_ui_public' => false]);

    $this->actingAs(verifiedUser('m'))->get('/settings/api-tokens')->assertStatus(200);
});

test('regular users can reach the token page when the flag is on', function () {
    config(['services.mcp.token_ui_public' => true]);

    $this->actingAs(verifiedUser())->get('/settings/api-tokens')->assertStatus(200);
});

test('guests are redirected to login', function () {
    $this->get('/settings/api-tokens')->assertRedirect();
});

test('creating a token returns the plaintext once with a 90-day expiry', function () {
    $user = verifiedUser('m');

    $response = $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'Claude laptop'])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'name', 'expires_at']);

    expect($response->json('token'))->toContain('|');

    $stored = $user->tokens()->first();
    expect($stored->name)->toBe('Claude laptop');
    expect($stored->abilities)->toBe(['mcp']);
    expect(now()->diffInDays($stored->expires_at))->toBeGreaterThan(88);

    // Listing never exposes the token hash or plaintext
    $list = $this->actingAs($user)->getJson('/settings/api-tokens/list')->assertStatus(200);
    expect(json_encode($list->json()))->not->toContain($stored->token);
});

test('duplicate token names are rejected per user', function () {
    $user = verifiedUser('m');
    $user->createToken('same-name', ['mcp']);

    $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'same-name'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('a user can revoke their own token but not others', function () {
    $owner = verifiedUser('m');
    $other = verifiedUser('m');
    $ownToken = $owner->createToken('mine', ['mcp']);
    $otherToken = $other->createToken('theirs', ['mcp']);

    $this->actingAs($owner)
        ->deleteJson('/settings/api-tokens/'.$otherToken->accessToken->id)
        ->assertStatus(404);

    $this->actingAs($owner)
        ->deleteJson('/settings/api-tokens/'.$ownToken->accessToken->id)
        ->assertStatus(200);

    expect($owner->tokens()->count())->toBe(0);
    expect($other->tokens()->count())->toBe(1);
});

test('the verified middleware treats token routes like the rest of the site', function () {
    // User does not implement MustVerifyEmail, so EnsureEmailIsVerified passes any
    // logged-in user (email verification happens implicitly via login-code/social
    // flows). Token routes intentionally match the site-wide behavior here.
    $user = User::factory()->create(['type' => 'm', 'email_verified_at' => null]);

    $this->actingAs($user)->getJson('/settings/api-tokens/list')->assertStatus(200);
});
