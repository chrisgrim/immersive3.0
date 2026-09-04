<?php

use App\Models\User;

/**
 * The API-token page is gated: moderators/admins always, everyone else only
 * once services.mcp.public is flipped on. Tokens are Passport personal access
 * tokens with the mcp:use scope; mcp:moderate only on a moderator's say-so.
 */
function verifiedUser(string $type = 'u'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

beforeEach(fn () => personalAccessClient());

test('regular users cannot reach the token page while the flag is off', function () {
    config(['services.mcp.public' => false]);

    $this->actingAs(verifiedUser())->get('/settings/api-tokens')->assertStatus(403);
    $this->actingAs(verifiedUser())->postJson('/settings/api-tokens', ['name' => 'x'])->assertStatus(403);
});

test('moderators can reach the token page while the flag is off', function () {
    config(['services.mcp.public' => false]);

    $this->actingAs(verifiedUser('m'))->get('/settings/api-tokens')->assertStatus(200);
});

test('regular users can reach the token page when the flag is on', function () {
    config(['services.mcp.public' => true]);

    $this->actingAs(verifiedUser())->get('/settings/api-tokens')->assertStatus(200);
});

test('guests are redirected to login', function () {
    $this->get('/settings/api-tokens')->assertRedirect();
});

test('creating a token returns the signed token once with a 90-day expiry and the mcp:use scope', function () {
    $user = verifiedUser('m');

    $response = $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'Claude laptop'])
        ->assertStatus(201)
        ->assertJsonStructure(['token', 'name', 'scopes', 'expires_at']);

    // A Passport token is a signed JWT.
    expect($response->json('token'))->toStartWith('ey');
    expect($response->json('scopes'))->toBe(['mcp:use']);

    $stored = $user->tokens()->first();
    expect($stored->name)->toBe('Claude laptop');
    expect($stored->scopes)->toBe(['mcp:use']);
    expect((int) now()->diffInDays($stored->expires_at))->toBeGreaterThan(88);

    // Listing carries metadata only, never the token.
    $list = $this->actingAs($user)->getJson('/settings/api-tokens/list')->assertStatus(200);
    expect($list->json('tokens.0.name'))->toBe('Claude laptop');
    expect($list->json('tokens.0.moderate'))->toBeFalse();
    expect(json_encode($list->json()))->not->toContain($response->json('token'));
});

test('a moderator can ask for moderator powers on a token, and nobody else can', function () {
    $moderator = verifiedUser('m');
    $this->actingAs($moderator)
        ->postJson('/settings/api-tokens', ['name' => 'Ops script', 'moderate' => true])
        ->assertStatus(201)
        ->assertJsonPath('scopes', ['mcp:use', User::MODERATE_SCOPE]);
    expect($moderator->tokens()->first()->scopes)->toBe(['mcp:use', User::MODERATE_SCOPE]);

    config(['services.mcp.public' => true]);
    $user = verifiedUser();
    $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'Sneaky', 'moderate' => true])
        ->assertStatus(403);
    expect($user->tokens()->count())->toBe(0);
});

test('duplicate token names are rejected per user, but a revoked name can be reused', function () {
    $user = verifiedUser('m');
    $user->createToken('same-name', ['mcp:use']);

    $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'same-name'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $user->tokens()->update(['revoked' => true]);

    $this->actingAs($user)
        ->postJson('/settings/api-tokens', ['name' => 'same-name'])
        ->assertStatus(201);
});

test('a user can revoke their own token but not others', function () {
    $owner = verifiedUser('m');
    $other = verifiedUser('m');
    $ownToken = $owner->createToken('mine', ['mcp:use']);
    $otherToken = $other->createToken('theirs', ['mcp:use']);

    $this->actingAs($owner)
        ->deleteJson('/settings/api-tokens/'.$otherToken->getToken()->id)
        ->assertStatus(404);

    $this->actingAs($owner)
        ->deleteJson('/settings/api-tokens/'.$ownToken->getToken()->id)
        ->assertStatus(200);

    expect($owner->tokens()->where('revoked', false)->count())->toBe(0);
    expect($other->tokens()->where('revoked', false)->count())->toBe(1);

    // And a revoked token no longer opens the door.
    $this->postJson('/mcp', mcpInitializePayload(), ['Authorization' => "Bearer {$ownToken->accessToken}"])
        ->assertStatus(401);
});

test('the verified middleware treats token routes like the rest of the site', function () {
    // User does not implement MustVerifyEmail, so EnsureEmailIsVerified passes any
    // logged-in user (email verification happens implicitly via login-code/social
    // flows). Token routes intentionally match the site-wide behavior here.
    $user = User::factory()->create(['type' => 'm', 'email_verified_at' => null]);

    $this->actingAs($user)->getJson('/settings/api-tokens/list')->assertStatus(200);
});
