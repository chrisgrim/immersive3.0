<?php

use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;

/**
 * One search-index update per save, queued, after commit. Before this an
 * event save reached Elasticsearch synchronously up to ten times (every
 * save()/update() on the event plus explicit searchable() calls in
 * Show/Ticket/Genre), and a down cluster failed the whole request.
 *
 * Scout runs on the null driver in tests; with scout.queue on it still
 * dispatches the MakeSearchable/RemoveFromSearch jobs, which is what these
 * tests count.
 */
beforeEach(function () {
    config(['scout.queue' => true]);
    Queue::fake();
});

$publishedEvent = function (): Event {
    $user = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => 'p']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);

    return $event;
};

test('syncSearchIndex queues one MakeSearchable job for a published event', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Queue::fake();

    $event->syncSearchIndex();

    Queue::assertPushed(MakeSearchable::class, 1);
    Queue::assertNotPushed(RemoveFromSearch::class);
});

test('syncSearchIndex queues a RemoveFromSearch job for an unpublished event', function () use ($publishedEvent) {
    $event = $publishedEvent();
    $event->update(['status' => 'd']);
    Queue::fake();

    $event->syncSearchIndex();

    Queue::assertPushed(RemoveFromSearch::class, 1);
    Queue::assertNotPushed(MakeSearchable::class);
});

test('deferringSearchSync collapses every save and sync inside the block into one job', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Queue::fake();

    Event::deferringSearchSync(function () use ($event) {
        $event->update(['name' => 'Renamed once']);   // observer save
        $event->update(['name' => 'Renamed twice']);  // observer save
        $event->syncSearchIndex();                    // explicit, as Show/Ticket/Genre do
        $event->syncSearchIndex();
    });

    Queue::assertPushed(MakeSearchable::class, 1);
});

test('deferringSearchSync waits for the enclosing transaction to commit', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Queue::fake();

    DB::transaction(function () use ($event) {
        Event::deferringSearchSync(fn () => $event->syncSearchIndex());

        // Still inside the transaction: nothing may have reached the queue.
        Queue::assertNothingPushed();
    });

    Queue::assertPushed(MakeSearchable::class, 1);
});

test('deferringSearchSync queues nothing when the block throws', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Queue::fake();

    try {
        Event::deferringSearchSync(function () use ($event) {
            $event->syncSearchIndex();
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Queue::assertNothingPushed();

    // And the deferral flag was reset: a later sync behaves normally.
    $event->syncSearchIndex();
    Queue::assertPushed(MakeSearchable::class, 1);
});

test('nested deferringSearchSync blocks flush once, from the outermost block', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Queue::fake();

    Event::deferringSearchSync(function () use ($event) {
        Event::deferringSearchSync(fn () => $event->syncSearchIndex());
        Queue::assertNothingPushed();
        $event->syncSearchIndex();
    });

    Queue::assertPushed(MakeSearchable::class, 1);
});

test('a full event update through UpdateEventAction (MCP update-event) queues exactly one index job', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Genre::factory()->create(['name' => 'Immersive theatre', 'slug' => 'immersive-theatre', 'admin' => true]);
    $user = $event->user;
    Queue::fake();

    EiServer::actingAs($user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'A renamed show',
        'genres' => [['name' => 'Immersive theatre'], ['name' => 'Spooky Season']],
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->name)->toBe('A renamed show');
    Queue::assertPushed(MakeSearchable::class, 1);
    Queue::assertNotPushed(RemoveFromSearch::class);
});
