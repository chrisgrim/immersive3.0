<?php

use App\Models\Organizer;
use App\Models\User;

test('checkNameAvailability requires name', function () {
    $this->postJson('/api/organizers/check-name', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('checkNameAvailability rejects names over 80 chars', function () {
    $this->postJson('/api/organizers/check-name', ['name' => str_repeat('a', 81)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

test('checkNameAvailability returns available for unused name', function () {
    $this->postJson('/api/organizers/check-name', ['name' => 'Totally Unique Org Name'])
        ->assertOk()
        ->assertJsonPath('available', true);
});

test('checkNameAvailability returns unavailable when name exists', function () {
    $user = User::factory()->create();
    Organizer::create([
        'name' => 'Existing Org',
        'slug' => 'existing-org',
        'description' => 'Hi',
        'status' => 'p',
        'user_id' => $user->id,
    ]);

    $this->postJson('/api/organizers/check-name', ['name' => 'Existing Org'])
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('existing_organizations.0.slug', 'existing-org');
});

test('checkNameAvailability ignores soft-deactivated organizers', function () {
    $user = User::factory()->create();
    Organizer::create([
        'name' => 'Deleted Org',
        'slug' => 'deleted-org',
        'description' => 'Hi',
        'status' => 'd',
        'user_id' => $user->id,
    ]);

    $this->postJson('/api/organizers/check-name', ['name' => 'Deleted Org'])
        ->assertOk()
        ->assertJsonPath('available', true);
});
