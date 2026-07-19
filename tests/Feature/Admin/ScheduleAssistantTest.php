<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function scheduleAdmin(string $type = 'a'): User
{
    return User::factory()->create(['type' => $type, 'email_verified_at' => now()]);
}

function scheduleEvent(User $user): Event
{
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => '0']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
}

/** A two-step Claude exchange: tool_use, then a final text turn. */
function fakeClaude(array $toolInput): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push([
                'content' => [
                    ['type' => 'text', 'text' => 'Setting that up.'],
                    ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'update_schedule', 'input' => $toolInput],
                ],
                'stop_reason' => 'tool_use',
            ], 200)
            ->push([
                'content' => [['type' => 'text', 'text' => 'Done — schedule updated.']],
                'stop_reason' => 'end_turn',
            ], 200),
    ]);
}

test('schedule assistant rejects non-admins', function (string $type) {
    $user = scheduleAdmin($type);
    $event = scheduleEvent($user);
    Http::fake(); // must never be called

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", ['message' => 'set dates'])
        ->assertForbidden();

    Http::assertNothingSent();
})->with(['u', 'c', 'm']); // guest/user, curator, moderator — none are admins

test('schedule assistant lets an admin set the schedule', function () {
    $admin = scheduleAdmin();
    $event = scheduleEvent($admin);

    fakeClaude([
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
        'dateArray' => ['2026-08-08 02:30:00', '2026-08-15 02:30:00'],
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", [
            'message' => 'Add two August Fridays at 7:30pm Pacific',
        ]);

    $response->assertOk()
        ->assertJson(['schedule_changed' => true])
        ->assertJsonPath('schedule.showtype', 's')
        ->assertJsonPath('schedule.show_count', 2);

    $event->refresh();
    expect($event->showtype)->toBe('s');
    expect($event->shows()->count())->toBe(2);
    expect($event->timezone)->toBe('America/Los_Angeles');
});

test('schedule assistant ignores non-schedule fields and a forged event slug', function () {
    $admin = scheduleAdmin();
    $event = scheduleEvent($admin);
    $victim = scheduleEvent(scheduleAdmin());
    $originalName = $event->name;

    // Claude is told to rename the event and target a DIFFERENT event — both
    // must be ignored: only schedule fields on the pinned event may change.
    fakeClaude([
        'showtype' => 's',
        'dateArray' => ['2026-08-08 02:30:00'],
        'name' => 'HACKED NAME',
        'description' => 'injected',
        'event_slug' => $victim->slug,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", ['message' => 'go'])
        ->assertOk();

    $event->refresh();
    // Schedule applied to the pinned event...
    expect($event->shows()->count())->toBe(1);
    // ...but the forbidden fields were stripped...
    expect($event->name)->toBe($originalName);
    expect($event->description)->not->toBe('injected');
    // ...and the other event was never touched.
    expect($victim->fresh()->shows()->count())->toBe(0);
});

test('schedule assistant survives a no-argument tool call (empty-input round-trip)', function () {
    $admin = scheduleAdmin();
    $event = scheduleEvent($admin);

    // Anthropic sends a no-arg tool call's input as `{}`. PHP's associative
    // decode turns that into `[]`, which — if echoed back verbatim — the API
    // rejects on the next request ("input should be an object").
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push([
                'content' => [
                    ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_schedule', 'input' => (object) []],
                ],
                'stop_reason' => 'tool_use',
            ], 200)
            ->push([
                'content' => [['type' => 'text', 'text' => "Here's the schedule."]],
                'stop_reason' => 'end_turn',
            ], 200),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", ['message' => 'what is the schedule?'])
        ->assertOk()
        ->assertJsonPath('reply', "Here's the schedule."); // reaching call 2's text proves the loop didn't 400

    // The second request must echo the tool_use with input as an object, not [].
    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, '"tool_result"')
            && str_contains($body, '"get_schedule"')
            && str_contains($body, '"input":{}')
            && ! str_contains($body, '"input":[]');
    });
});

test('schedule assistant validates the message and history', function () {
    $admin = scheduleAdmin();
    $event = scheduleEvent($admin);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", ['message' => ''])
        ->assertStatus(422);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/hosting/event/{$event->slug}/schedule-assistant", [
            'message' => 'hi',
            'history' => [['role' => 'system', 'content' => 'x']], // role not in user|assistant
        ])
        ->assertStatus(422);
});
