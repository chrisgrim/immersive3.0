<?php

use App\Models\User;

/**
 * Smoke tests verifying that auth/role gates on critical endpoints are wired correctly.
 * These do not test the full controller logic — only that the gate refuses the wrong actor.
 */

test('guests cannot update an event', function () {
    $this->postJson('/api/hosting/event/1')->assertStatus(401);
});

test('guests cannot duplicate an event', function () {
    $this->postJson('/api/events/1/duplicate')->assertStatus(401);
});

test('guests cannot read click stats', function () {
    $this->getJson('/api/events/1/click-stats')->assertStatus(401);
});

test('guests cannot reach admin approval endpoints', function () {
    $this->getJson('/api/admin/approve/events')->assertStatus(401);
    $this->postJson('/api/admin/approve/events/1/approve')->assertStatus(401);
    $this->getJson('/api/admin/approval-counts')->assertStatus(401);
});

test('regular users cannot reach admin approval endpoints', function () {
    $user = User::factory()->create(['type' => 'u']);

    // Note: routes with {event} model binding 404 before the moderator gate runs,
    // so we test routes without bindings here.
    $this->actingAs($user)->getJson('/api/admin/approve/events')->assertStatus(403);
    $this->actingAs($user)->getJson('/api/admin/approval-counts')->assertStatus(403);
});

test('curators cannot reach admin approval endpoints', function () {
    $user = User::factory()->create(['type' => 'c']);

    $this->actingAs($user)->getJson('/api/admin/approve/events')->assertStatus(403);
});

test('moderators pass the admin gate', function () {
    $user = User::factory()->create(['type' => 'm']);

    // 200 from a valid pending-events fetch, or 5xx from missing data — either way, NOT 401/403.
    $response = $this->actingAs($user)->getJson('/api/admin/approval-counts');
    expect($response->status())->not->toBe(401);
    expect($response->status())->not->toBe(403);
});
