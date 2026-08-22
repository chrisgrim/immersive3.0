<?php

use App\Models\User;

test('account settings page requires auth', function () {
    $this->get('/account-settings')->assertRedirect('/login');
});

test('a logged-in user can load the account settings shell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/account-settings')
        ->assertOk()
        ->assertSee('vue-account-settings-index', false);
});

test('a deep link to a specific account settings tab loads the same shell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/account-settings/login-security')
        ->assertOk();
});

test('an invalid account settings tab 404s', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/account-settings/not-a-real-tab')
        ->assertNotFound();
});

test('notifications feed page requires auth', function () {
    $this->get('/notifications')->assertRedirect('/login');
});

test('a logged-in user can load the notifications feed page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/notifications')
        ->assertOk()
        ->assertSee('vue-notifications-index', false);
});

test('the help page is publicly viewable', function () {
    $this->get('/help')->assertOk();
});
