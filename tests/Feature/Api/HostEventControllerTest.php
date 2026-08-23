<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

// Helper: an organizer + member who can host/manage events on it.
function memberOf(Organizer $organizer, string $type = 'u'): User
{
    $user = User::factory()->create(['type' => $type]);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    return $user->fresh();
}

// ----- submit() — web route POST /hosting/event/{event}/submit -----

test('submit transitions a draft event to under-review', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertOk();

    expect($event->fresh()->status)->toBe('r');
});

test('submit rejects an already-submitted event with 422', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'r']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(422);

    expect($event->fresh()->status)->toBe('r');
});

test('submit rejects a published event with 422', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(422);
});

test('submit is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    // Stranger has no teams → fails the host gate first (403).
    $this->actingAs($stranger)
        ->postJson(route('hosting.event.submit', $event))
        ->assertStatus(403);
});

test('submit is denied to guests (302 to login)', function () {
    $event = Event::factory()->create();
    $this->postJson(route('hosting.event.submit', $event))->assertStatus(401);
});

// ----- destroy() — web route DELETE /hosting/event/{event} -----

test('destroy soft-deletes the event', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->deleteJson(route('hosting.event.destroy', $event))
        ->assertOk();

    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id))->not->toBeNull();
});

test('destroy is denied to non-members', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->deleteJson(route('hosting.event.destroy', $event))
        ->assertStatus(403);

    expect(Event::find($event->id))->not->toBeNull();
});

// ----- create() — web route POST /hosting/event/create -----

test('create rejects users who have no teams', function () {
    $user = User::factory()->create(['type' => 'u']);
    $organizer = Organizer::factory()->create();

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(403);
});

test('create returns 409 when a duplicate name exists without acknowledgement', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->create(['name' => 'Spooky Dinner Theater']);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), [
            'organizer_id' => $organizer->id,
            'name' => 'Spooky Dinner Theater',
        ])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'duplicateEvents', 'warning']);
});

test('create accepts a duplicate name when acknowledge_duplicate=1', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->create(['name' => 'Spooky Dinner Theater']);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), [
            'organizer_id' => $organizer->id,
            'name' => 'Spooky Dinner Theater',
            'acknowledge_duplicate' => 1,
        ])
        ->assertStatus(201);
});

test('create allows non-admins up to the unpublished cap', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS - 1)->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
    ]);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(201);
});

test('create blocks non-admins at the unpublished cap', function () {
    $organizer = Organizer::factory()->create();
    $user = memberOf($organizer);

    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS)->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
    ]);

    $this->actingAs($user)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(422);
});

test('admins bypass the unpublished cap', function () {
    $organizer = Organizer::factory()->create();
    $admin = memberOf($organizer, 'a');

    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS)->create([
        'organizer_id' => $organizer->id,
        'status' => 'd',
    ]);

    $this->actingAs($admin)
        ->postJson(route('hosting.event.create'), ['organizer_id' => $organizer->id])
        ->assertStatus(201);
});

// ----- update() — api route POST /api/hosting/event/{event} -----

test('update applies a description change for an organizer member', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'description' => 'Original.',
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Brand new copy.'])
        ->assertOk();

    expect($event->fresh()->description)->toBe('Brand new copy.');
});

test('update is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Hijack'])
        ->assertStatus(403);
});

test('update rejects editing an event whose run has already fully ended', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
        'description' => 'Original.',
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Rewriting history.'])
        ->assertStatus(403);

    expect($event->fresh()->description)->toBe('Original.');
});

test('update allows a moderator to edit an event whose run has already fully ended', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
        'description' => 'Original.',
    ]);
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Historical correction.'])
        ->assertOk();

    expect($event->fresh()->description)->toBe('Historical correction.');
});

test('edit GET rejects loading the edit form for an event whose run has already ended', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->get("/hosting/event/{$event->slug}/edit")
        ->assertStatus(403);
});

test('update still allows editing a draft event with no closingDate set yet', function () {
    // A brand-new draft mid-creation has no schedule yet — closingDate is
    // null, which must not be mistaken for "already happened".
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => null,
        'description' => 'Original.',
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Still drafting.'])
        ->assertOk();

    expect($event->fresh()->description)->toBe('Still drafting.');
});

test('update preserves a past show date a non-staff user tries to remove, and warns', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 's']);
    $user = memberOf($organizer);
    $tz = 'America/Los_Angeles';
    $past = '2020-01-01 12:00:00';
    $future = now($tz)->addDays(5)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $event->shows()->create(['date' => $future]);

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => $tz,
            'dateArray' => [$future],
        ])
        ->assertOk()
        ->assertJsonStructure(['warning']);

    expect($response->json('warning'))->toContain('2020-01-01');
    expect($event->fresh()->shows()->count())->toBe(2);
});

test('update lets a moderator remove a past show date with no warning', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 's']);
    $moderator = User::factory()->create(['type' => 'm']);
    $tz = 'America/Los_Angeles';
    $past = '2020-01-01 12:00:00';
    $future = now($tz)->addDays(5)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $event->shows()->create(['date' => $future]);

    $this->actingAs($moderator)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => $tz,
            'dateArray' => [$future],
        ])
        ->assertOk()
        ->assertJsonMissing(['warning']);

    expect($event->fresh()->shows()->count())->toBe(1);
});

test('update with ongoing showtype persists showtype_config (M11)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $user = memberOf($organizer);

    $payload = [
        'showtype' => 'o',
        'dateArray' => [
            now()->addDay()->format('Y-m-d H:i:s'),
            now()->addDays(3)->format('Y-m-d H:i:s'),
        ],
        'timezone' => 'America/New_York',
        'ongoing_config' => [
            'startDate' => now()->addDay()->format('Y-m-d H:i:s'),
            'endDate' => now()->addMonths(6)->format('Y-m-d H:i:s'),
            'daysOfWeek' => [1, 3], // Mon, Wed
        ],
    ];

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", $payload)
        ->assertOk();

    $config = $event->fresh()->showtype_config;
    expect($config)->toBeArray();
    expect($config['type'])->toBe('ongoing');
    expect($config['days_of_week'])->toBe([1, 3]);
    expect($config['start_date'])->not->toBeNull();
    expect($config['end_date'])->not->toBeNull();
});

test('update rejects status=p mass-assignment (CR1)', function () {
    // Without the allow-list, an organizer member could POST status:'p' and skip
    // the admin approval workflow entirely.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['status' => 'p'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($event->fresh()->status)->toBe('d');
});

test('update rejects status=e mass-assignment', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => 'd']);

    $this->actingAs(memberOf($organizer))
        ->postJson("/api/hosting/event/{$event->slug}", ['status' => 'e'])
        ->assertStatus(422);

    expect($event->fresh()->status)->toBe('d');
});

test('update accepts legitimate wizard-step status values', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'status' => '0']);
    $user = memberOf($organizer);

    // 'A'-'D' are the Advisories/Content/Mobility/Review wizard step markers
    // (see STEP_MAP in resources/js/PageComponents/Creation/Core/index.vue).
    // 'A' and 'D' were missed by the original CR1 allow-list, leaving users
    // stuck at the Advisories step with a 422.
    foreach (['d', '1', '5', '9', 'A', 'B', 'C', 'D'] as $status) {
        $this->actingAs($user)
            ->postJson("/api/hosting/event/{$event->slug}", ['status' => $status])
            ->assertOk();
    }
});

test('update with specific showtype clears showtype_config', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype_config' => ['type' => 'ongoing', 'days_of_week' => [1, 3]],
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'dateArray' => [now()->addDay()->format('Y-m-d H:i:s')],
        ])
        ->assertOk();

    expect($event->fresh()->showtype_config)->toBeNull();
});

test('update returns 409 on duplicate name without acknowledgement', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'name' => 'Original']);
    Event::factory()->create(['name' => 'Conflicting Name']);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Conflicting Name'])
        ->assertStatus(409);

    expect($event->fresh()->name)->toBe('Original');
});

test('update surfaces the duplicate event\'s organizer as claimable when staff-entered', function () {
    // The conflicting event sits under a staff-entered, externally-unowned org — claimable.
    // The event-title collision is the signal that points the creator at that org's claim flow.
    $staff = User::factory()->create(['type' => 'm']);
    $staffOrg = Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'p']);
    Event::factory()->create(['organizer_id' => $staffOrg->id, 'name' => 'Blooming Wonders']);

    // A different team is editing their own draft and lands on the same title.
    $prOrg = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $prOrg->id, 'name' => 'Original']);
    $user = memberOf($prOrg);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Blooming Wonders'])
        ->assertStatus(409)
        ->assertJsonPath('duplicateEvents.0.organizer.slug', $staffOrg->slug)
        ->assertJsonPath('duplicateEvents.0.organizer.claimable', true);
});

test('update marks the duplicate organizer not claimable when externally owned', function () {
    // A legitimately externally-owned event must never be claimable via a title collision.
    $external = User::factory()->create(['type' => 'u']);
    $externalOrg = Organizer::factory()->create(['user_id' => $external->id]);
    Event::factory()->create(['organizer_id' => $externalOrg->id, 'name' => 'Blooming Wonders']);

    $prOrg = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $prOrg->id, 'name' => 'Original']);
    $user = memberOf($prOrg);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['name' => 'Blooming Wonders'])
        ->assertStatus(409)
        ->assertJsonPath('duplicateEvents.0.organizer.claimable', false);
});

// ----- duplicate() — api route POST /api/events/{event}/duplicate -----

test('duplicate creates a new event for an organizer member', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'name' => 'Source Event',
    ]);
    $user = memberOf($organizer);

    $before = Event::count();

    $this->actingAs($user)
        ->postJson("/api/events/{$event->slug}/duplicate")
        ->assertStatus(201);

    expect(Event::count())->toBe($before + 1);
});

test('duplicate is denied to non-members (403)', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger)
        ->postJson("/api/events/{$event->slug}/duplicate")
        ->assertStatus(403);
});
