<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\CreateEventDraft;
use App\Mcp\Tools\UpdateEvent;
use App\Mcp\Tools\Whoami;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/*
 * Fixes from staff batch entry (Kathryn's list): EI staff skip the draft
 * limit and events in review never count; whoami pages and searches; the
 * levels are accepted by id alone.
 */

function batchStaff(): User
{
    return User::factory()->create(['type' => 'm', 'email_verified_at' => now()]);
}

test('EI staff are not held by the draft limit, and events in review never count', function () {
    $organizer = Organizer::factory()->create(['status' => 'p']);
    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS)->create(['organizer_id' => $organizer->id, 'status' => '0']);

    EiServer::actingAs(batchStaff())->tool(CreateEventDraft::class, ['organizer_id' => $organizer->id])
        ->assertOk()->assertSee('Draft created.');

    $tour = Organizer::factory()->create(['status' => 'p']);
    Event::factory()->count(Event::MAX_UNPUBLISHED_EVENTS)->create(['organizer_id' => $tour->id, 'status' => 'r']);
    expect(Event::countUnpublishedEvents($tour->id))->toBe(0);
});

test('whoami pages and searches a long organizer list', function () {
    $staff = batchStaff();
    foreach (range(1, 30) as $n) {
        $organizer = Organizer::factory()->create(['name' => "Company {$n}"]);
        $staff->teams()->attach($organizer->id, ['role' => 'owner']);
    }
    $busy = Organizer::factory()->create(['name' => 'Faulty Towers Live']);
    $staff->teams()->attach($busy->id, ['role' => 'owner']);
    Event::factory()->create(['organizer_id' => $busy->id, 'status' => '0']);

    EiServer::actingAs($staff)->tool(Whoami::class)
        ->assertOk()->assertSee('"organizers_total":31')->assertSee('Faulty Towers Live')->assertSee('Showing 25 of 31');

    EiServer::actingAs($staff)->tool(Whoami::class, ['organizer_query' => 'faulty'])
        ->assertOk()->assertSee('"organizers_total":1');
});

test('interactive and contact levels are accepted by id alone', function () {
    $staff = batchStaff();
    $event = Event::factory()->create(['status' => '0']);
    $interactive = (\App\Models\Events\InteractiveLevel::query()->first()
        ?? \App\Models\Events\InteractiveLevel::create(['name' => 'Passive', 'description' => 'Watch only', 'user_id' => $staff->id]))->id;
    $contact = (\App\Models\Events\ContactLevel::query()->first()
        ?? \App\Models\Events\ContactLevel::create(['name' => 'None', 'user_id' => $staff->id]))->id;

    EiServer::actingAs($staff)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'interactiveLevel' => ['id' => $interactive],
        'contactLevel' => ['id' => $contact],
    ])->assertOk()->assertHasNoErrors();

    expect($event->fresh()->interactive_level_id)->toBe($interactive);
});
