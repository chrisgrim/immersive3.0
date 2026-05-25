<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\TrackClick;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

// trackClick — public endpoint
test('trackClick records a click for an event', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/events/{$event->id}/track-click", [
        'destination_url' => 'https://example.com/tickets',
    ])->assertOk()->assertJson(['success' => true]);

    expect(TrackClick::where('event_id', $event->id)->count())->toBe(1);
    $row = TrackClick::where('event_id', $event->id)->first();
    expect($row->organizer_id)->toBe($event->organizer_id);
    expect($row->user_id)->toBeNull();
    expect($row->destination_url)->toBe('https://example.com/tickets');
    expect($row->click_type)->toBe('link');
});

test('trackClick attributes user_id when authenticated', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    $this->actingAs($user)->postJson("/api/events/{$event->id}/track-click")->assertOk();

    expect(TrackClick::where('event_id', $event->id)->first()->user_id)->toBe($user->id);
});

test('trackClick 404s for missing event', function () {
    $this->postJson('/api/events/99999/track-click')->assertStatus(404);
});

test('trackClick deduplicates same ip+UA within 5 minutes', function () {
    $event = Event::factory()->create();

    $this->postJson("/api/events/{$event->id}/track-click")->assertOk();
    // Second hit with the same IP + UA → still 200, but no new row.
    $this->postJson("/api/events/{$event->id}/track-click")->assertOk();

    expect(TrackClick::where('event_id', $event->id)->count())->toBe(1);
});

test('trackClick falls back to ticketUrl then websiteUrl when no destination_url', function () {
    $event = Event::factory()->create([
        'ticketUrl' => 'https://tix.example.com',
        'websiteUrl' => 'https://site.example.com',
    ]);

    $this->postJson("/api/events/{$event->id}/track-click")->assertOk();

    expect(TrackClick::where('event_id', $event->id)->first()->destination_url)
        ->toBe('https://tix.example.com');
});

test('trackClick truncates long URLs/UA to fit columns', function () {
    $event = Event::factory()->create();
    $longUrl = 'https://example.com/'.str_repeat('a', 400);

    $this->withHeaders(['User-Agent' => str_repeat('x', 400)])
        ->postJson("/api/events/{$event->id}/track-click", ['destination_url' => $longUrl])
        ->assertOk();

    $row = TrackClick::where('event_id', $event->id)->first();
    expect(strlen($row->destination_url))->toBeLessThanOrEqual(255);
    expect(strlen($row->user_agent))->toBeLessThanOrEqual(255);
});

// getStats — protected endpoint
test('getStats requires authentication', function () {
    $event = Event::factory()->create();
    $this->getJson("/api/events/{$event->id}/click-stats")->assertStatus(401);
});

test('getStats forbids strangers (403)', function () {
    $stranger = User::factory()->create(['type' => 'u']);
    $event = Event::factory()->create();

    $this->actingAs($stranger)->getJson("/api/events/{$event->id}/click-stats")
        ->assertStatus(403);
});

test('getStats returns total and daily for an organizer member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create(['user_id' => $owner->id]);
    $organizer->users()->attach($member->id, ['role' => 'member']);

    $event = Event::factory()->create(['organizer_id' => $organizer->id]);

    // Seed 3 clicks today
    TrackClick::factory()->count(3)->create([
        'event_id' => $event->id,
        'organizer_id' => $organizer->id,
    ]);

    $response = $this->actingAs($member->fresh())
        ->getJson("/api/events/{$event->id}/click-stats")
        ->assertOk()
        ->assertJsonStructure(['total', 'daily']);

    expect($response->json('total'))->toBe(3);
    expect($response->json('daily'))->toHaveCount(1);
    // Non-admins without ?detailed do NOT get unique_users / unique_ips.
    expect($response->json())->not->toHaveKey('unique_users');
});

test('getStats includes unique counts for admin/moderator without ?detailed', function () {
    $mod = User::factory()->create(['type' => 'm']);
    $event = Event::factory()->create();

    TrackClick::factory()->count(2)->create([
        'event_id' => $event->id,
        'organizer_id' => $event->organizer_id,
        'user_id' => $mod->id,
        'ip_address' => '10.0.0.1',
    ]);

    $response = $this->actingAs($mod)
        ->getJson("/api/events/{$event->id}/click-stats")
        ->assertOk()
        ->assertJsonStructure(['total', 'unique_users', 'unique_ips', 'daily']);

    expect($response->json('unique_users'))->toBe(1);
    expect($response->json('unique_ips'))->toBe(1);
});

test('getStats 404s for missing event', function () {
    $mod = User::factory()->create(['type' => 'm']);
    $this->actingAs($mod)->getJson('/api/events/99999/click-stats')->assertStatus(404);
});
