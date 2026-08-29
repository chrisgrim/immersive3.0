<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Carbon;

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

/*
 * A finished run used to be refused outright for anyone but a moderator. That
 * also blocked the most ordinary reason an organizer returns to one — they are
 * running the show again and want to add dates — leaving them only the
 * duplicate flow, which starts a new listing at a new URL and abandons the
 * original's favourites and click stats.
 *
 * It is editable now. The record is protected by the narrower rule below
 * instead: Show::saveShows() will not DELETE an already-passed show for a
 * non-moderator, so history can be added to but not erased.
 */
test('an organizer can edit an event whose run has already fully ended', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
        'description' => 'Original.',
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['description' => 'Back for another run.'])
        ->assertOk();

    expect($event->fresh()->description)->toBe('Back for another run.');
});

test('an organizer can add a future date to a finished run, reviving it', function () {
    // The whole point of reopening these: a new date moves closingDate, and
    // the event comes back to life at its own URL rather than as a new listing.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'closingDate' => now()->subMonth(),
    ]);
    $user = memberOf($organizer);
    $tz = 'America/Los_Angeles';
    $past = now($tz)->subMonth()->format('Y-m-d H:i:s');
    $future = now($tz)->addDays(30)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => $tz,
            'dateArray' => [$past, $future],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
    expect($event->fresh()->isShowing)->toBeTrue();
});

test('reopening the event does not let an organizer erase its past dates', function () {
    // The guarantee that replaced the blanket lock. Without this, "editable
    // again" would quietly mean "rewritable", which is what the lock existed
    // to prevent in the first place.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'closingDate' => now()->subMonth(),
    ]);
    $user = memberOf($organizer);
    $tz = 'America/Los_Angeles';
    $past = '2020-01-01 12:00:00';
    $future = now($tz)->addDays(30)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => $tz,
            'dateArray' => [$future],
        ])
        ->assertOk();

    expect($response->json('warning'))->toContain('2020-01-01');
    expect($event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d))->toContain($past);
});

test('a moderator can still edit an event whose run has already fully ended', function () {
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

test('the edit form loads for an event whose run has already ended', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
    ]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->get("/hosting/event/{$event->slug}/edit")
        ->assertOk();
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

/*
 * The three fences that make a reopened finished event safe. Removing the edit
 * lock (c3db259) let organizers back into their own archive, which is what we
 * want — but it also exposed three ways to bring a finished event back to life
 * or misrepresent it that the lock had been incidentally covering.
 */

test('closingDate cannot be set directly, so one field cannot revive a finished run', function () {
    // It is DERIVED from the schedule. Accepted as input, this single field put
    // a finished event back in search and listings while every show stayed in
    // the past — an ended run rendered as live, invisible to date search.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'closingDate' => now()->subMonth(),
    ]);
    $user = memberOf($organizer);
    $before = (string) $event->closingDate;

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", ['closingDate' => now()->addYears(4)->format('Y-m-d H:i:s')])
        ->assertOk();

    expect((string) $event->fresh()->closingDate)->toBe($before);
});

test('an organizer cannot re-announce a finished event to the organizer followers', function () {
    // published → embargoed → published is how an event announces itself, and
    // clearing the embargo mails every follower. events.organizer_notified_at
    // was added without a backfill, so every older event is unmarked and would
    // announce again — the whole archive, one toggle away.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $event->shows()->create(['date' => now()->subMonth()->format('Y-m-d H:i:s')]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [now()->subMonth()->format('Y-m-d H:i:s')],
            'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->assertOk();

    expect($event->fresh()->status)->toBe('p');
});

test('embargo still works when the same save gives the event a real future run', function () {
    // The test is the schedule AFTER the save, not before it — an organizer
    // relaunching with genuine future dates is embargoing an upcoming run.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$past, now()->addMonths(2)->format('Y-m-d H:i:s')],
            'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->assertOk();

    expect($event->fresh()->status)->toBe('e');
});

test('a moderator can still embargo a finished event', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $moderator = User::factory()->create(['type' => 'm']);

    $this->actingAs($moderator)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$past],
            'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->assertOk();

    expect($event->fresh()->status)->toBe('e');
});

test('an organizer cannot invent a show in the past, and is told so', function () {
    // The mirror of the deletion guard. The ongoing editor regenerates the
    // whole schedule from its recurrence rule, so ticking an extra weekday on
    // a finished event would otherwise create real show rows for days it never
    // ran — which the deletion guard would then make permanent.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 's']);
    $user = memberOf($organizer);
    $future = now()->addDays(20)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $future]);

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$future, '2019-06-01 19:00:00'],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->count())->toBe(1);
    expect($response->json('warning'))->toContain('2019-06-01');
});

test('a moderator can backfill a show in the past', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 's']);
    $moderator = User::factory()->create(['type' => 'm']);
    $future = now()->addDays(20)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $future]);

    $this->actingAs($moderator)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$future, '2019-06-01 19:00:00'],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('an existing past show is still preserved, not re-rejected as a new one', function () {
    // The two guards must not collide: a real past occurrence that is already
    // saved has to survive a re-save, while a NEW past date is refused.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 's']);
    $user = memberOf($organizer);
    $past = '2019-06-01 19:00:00';
    $future = now()->addDays(20)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $event->shows()->create(['date' => $future]);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$past, $future],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
});

test('a published event with no closing date cannot be cycled through embargo either', function () {
    // A null closingDate proves nothing about whether the run is upcoming.
    // The permissive reading handed the announcement path back to any
    // organizer whose event happened to have one.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => null,
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$past],
            'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->assertOk();

    expect($event->fresh()->status)->toBe('p');
});

// ----- The past-date creation guard is by calendar day where the event is -----

test('an organizer can add a show for tonight after local noon', function () {
    // The wizard pins every show to 12:00 in the event's timezone and lets
    // today be picked. A to-the-second guard against now() refused that to
    // anyone saving in the afternoon — today is today until local midnight.
    Carbon::setTestNow('2026-08-29 22:00:00'); // 15:00 in Los Angeles
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
    ]);
    $user = memberOf($organizer);
    $tonight = '2026-08-29 19:00:00'; // noon in Los Angeles, stored as UTC — three hours "ago" by the clock

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'America/Los_Angeles',
            'dateArray' => [$tonight],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->all())->toBe([$tonight]);
    expect($response->json('warning'))->toBeNull();
});

test('moving the only upcoming show to tonight does not strand the event without shows', function () {
    // The obsolete show is deleted before the replacement is created, so a
    // refused replacement would have left nothing behind — the invariant the
    // empty-schedule guard at the top of saveShows exists to protect.
    Carbon::setTestNow('2026-08-29 22:00:00');
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
    ]);
    $event->shows()->create(['date' => '2026-09-05 19:00:00']);
    $user = memberOf($organizer);
    $tonight = '2026-08-29 19:00:00';

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'America/Los_Angeles',
            'dateArray' => [$tonight],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->all())->toBe([$tonight]);
});

test('yesterday is refused, and named, by the day where the event is', function () {
    // 03:00 UTC on the 30th is still the evening of the 29th in Los Angeles.
    // A show at 22:00 LA on the 28th is stored under the 29th in UTC — it is
    // yesterday, so it is refused, and the warning must say the 28th.
    Carbon::setTestNow('2026-08-30 03:00:00');
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'timezone' => 'America/Los_Angeles',
    ]);
    $user = memberOf($organizer);
    $today = '2026-08-29 20:00:00';     // 13:00 LA on the 29th — today, kept
    $yesterday = '2026-08-29 05:00:00'; // 22:00 LA on the 28th — refused

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'America/Los_Angeles',
            'dateArray' => [$today, $yesterday],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->all())->toBe([$today]);
    expect($response->json('warning'))->toContain('2026-08-28')->not->toContain('2026-08-29');
});

test('a refused embargo is not stored, and the organizer is told', function () {
    // Refusing the status change but keeping the date left a published event
    // carrying an embargo that never took effect; the Dates step resends it on
    // every save, so once it had passed, `after:now` 422'd unrelated edits.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
        'embargo_date' => null,
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$past],
            'embargo_date' => now()->addMonth()->format('Y-m-d H:i:s'),
        ])
        ->assertOk();

    $event->refresh();
    expect($event->status)->toBe('p');
    expect($event->embargo_date)->toBeNull();
    expect($response->json('warning'))->toContain('embargo was not applied');
});

// ----- closingDate is derived from the shows; sentinel rows are not history -----

test('ongoing_config.endDate cannot revive a finished run either', function () {
    // closingDate is out of the request rules, but it used to be taken
    // straight from ongoing_config.endDate — the same one-field revival,
    // spelled differently. Derived from the shows, a run ends when its last
    // show does, whatever the recurrence rule was asked to say.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 'o',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 'o',
            'timezone' => 'UTC',
            'dateArray' => [$past],
            'ongoing_config' => [
                'startDate' => now()->subMonths(2)->format('Y-m-d H:i:s'),
                'endDate' => now()->addYears(2)->format('Y-m-d H:i:s'),
                'daysOfWeek' => [1],
            ],
        ])
        ->assertOk();

    $event->refresh();
    expect(substr((string) $event->closingDate, 0, 10))->toBe(substr($past, 0, 10));
    expect($event->isShowing)->toBeFalse();
});

test('an ongoing run extended with real shows closes after the last of them', function () {
    // The honest version of the above: the recurrence produced future shows,
    // and the closing date follows the last one.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 'o',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $future = now()->addDays(45)->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 'o',
            'timezone' => 'UTC',
            'dateArray' => [$past, $future],
            'ongoing_config' => [
                'startDate' => $past,
                'endDate' => now()->addYears(2)->format('Y-m-d H:i:s'),
                'daysOfWeek' => [1],
            ],
        ])
        ->assertOk();

    $event->refresh();
    expect(substr((string) $event->closingDate, 0, 10))->toBe(substr($future, 0, 10));
    expect($event->isShowing)->toBeTrue();
});

test('closing an always-available event by a past end date keeps its ticket tiers', function () {
    // An 'a' event's one show row is just its end date, not a performance.
    // Treating it as history deleted the old (future) sentinel, refused the
    // new (past) one, and left the event with no shows and its tickets gone.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'showtype' => 'a', 'status' => 'p']);
    $sentinel = $event->shows()->create(['date' => now()->addDays(20)->format('Y-m-d H:i:s')]);
    $sentinel->tickets()->create(['name' => 'General', 'ticket_price' => '20.00', 'currency' => '$', 'type' => 's']);
    $user = memberOf($organizer);
    $yesterday = now()->subDay()->format('Y-m-d H:i:s');

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 'a',
            'timezone' => 'UTC',
            'always_config' => ['endDate' => $yesterday],
        ])
        ->assertOk();

    $event->refresh();
    expect($event->shows()->count())->toBe(1);
    expect((string) $event->shows()->first()->date)->toBe($yesterday);
    expect($event->shows()->first()->tickets()->count())->toBe(1);
    expect($event->isShowing)->toBeFalse();
    expect($response->json('warning'))->toBeNull();
});

test('converting an expired always-available event to dates does not keep its sentinel as a past show', function () {
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 'a',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $event->shows()->create(['date' => now()->subMonth()->format('Y-m-d H:i:s')]);
    $user = memberOf($organizer);
    $future = now()->addDays(30)->format('Y-m-d H:i:s');

    $response = $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 's',
            'timezone' => 'UTC',
            'dateArray' => [$future],
        ])
        ->assertOk();

    expect($event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->all())->toBe([$future]);
    expect($response->json('warning'))->toBeNull();
});

test('a finished dated run converted to always-available still keeps its real past shows', function () {
    // The other direction: those rows ARE history, and survive the switch.
    $organizer = Organizer::factory()->create();
    $event = Event::factory()->create([
        'organizer_id' => $organizer->id,
        'showtype' => 's',
        'status' => 'p',
        'closingDate' => now()->subMonth(),
    ]);
    $past = now()->subMonth()->format('Y-m-d H:i:s');
    $event->shows()->create(['date' => $past]);
    $user = memberOf($organizer);

    $this->actingAs($user)
        ->postJson("/api/hosting/event/{$event->slug}", [
            'showtype' => 'a',
            'timezone' => 'UTC',
            'always_config' => ['endDate' => now()->addMonths(6)->format('Y-m-d H:i:s')],
        ])
        ->assertOk();

    $dates = $event->fresh()->shows()->pluck('date')->map(fn ($d) => (string) $d)->all();
    expect($dates)->toContain($past);
    expect(count($dates))->toBe(2);
});
