<?php

use App\Models\User;

// AuthenticatedSessionController@destroy (logout) is the only routed action on this
// controller. The previously-present login (store) and registration controllers were
// unrouted dead code — the app uses passwordless email-code + social login — and have
// been removed (see test-suite-findings.md M11).

test('logout returns 204 and logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertNoContent();

    $this->assertGuest();
});

test('logout requires authentication', function () {
    // /logout is behind the `auth` middleware; a guest is redirected to login.
    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});
