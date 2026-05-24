<?php

use App\Models\Category;
use App\Models\Events\AgeLimit;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Genre;
use App\Models\User;

// EventAttributesController applies auth:sanctum in its constructor.

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('GET /categories returns the catalog ordered by name', function () {
    Category::create(['name' => 'Zeta', 'slug' => 'zeta', 'description' => '', 'remote' => 0, 'rank' => 0]);
    Category::create(['name' => 'Alpha', 'slug' => 'alpha', 'description' => '', 'remote' => 0, 'rank' => 0]);

    $response = $this->actingAs($this->user)->getJson('/api/categories')->assertOk();
    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toContain('Alpha', 'Zeta');
    expect(array_search('Alpha', $names))->toBeLessThan(array_search('Zeta', $names));
});

test('GET /genres returns admin genres for any authenticated user', function () {
    Genre::create(['name' => 'Horror', 'slug' => 'horror', 'rank' => 0, 'admin' => 1, 'user_id' => $this->user->id]);

    $this->actingAs($this->user)->getJson('/api/genres')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Horror']);
});

test('GET /agelimits returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/agelimits')->assertOk();
});

test('GET /contentadvisories returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/contentadvisories')->assertOk();
});

test('GET /mobilityadvisories returns the catalog', function () {
    $this->actingAs($this->user)->getJson('/api/mobilityadvisories')->assertOk();
});

test('event attribute endpoints require authentication', function () {
    $this->getJson('/api/categories')->assertStatus(401);
    $this->getJson('/api/genres')->assertStatus(401);
    $this->getJson('/api/agelimits')->assertStatus(401);
});
