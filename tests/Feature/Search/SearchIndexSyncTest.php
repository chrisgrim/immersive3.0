<?php

use App\Jobs\SyncEventSearchIndex;
use App\Mcp\Servers\EiServer;
use App\Mcp\Tools\UpdateEvent;
use App\Models\Event;
use App\Models\Genre;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Laravel\Scout\Jobs\MakeSearchable;

/**
 * One search-index update per save, queued, after commit, and decided when
 * the job RUNS. Before this an event save reached Elasticsearch
 * synchronously up to ten times (every save()/update() on the event plus
 * explicit searchable() calls in Show/Ticket/Genre), a down cluster failed
 * the whole request, and an index job decided "index" at dispatch time,
 * which with retries could resurrect an event deleted in the meantime.
 *
 * Scout runs on the null driver in tests. A recording engine captures what
 * would reach Elasticsearch when a SyncEventSearchIndex job is run by hand.
 */
beforeEach(function () {
    config(['scout.queue' => true]);
    Queue::fake();
});

/** Swap in a NullEngine that records the ids it was asked to index/delete. */
function recordingSearchEngine(): object
{
    $spy = new class extends NullEngine
    {
        public array $updated = [];

        public array $deleted = [];

        public function update($models)
        {
            $this->updated[] = $models->map->getScoutKey()->all();
        }

        public function delete($models)
        {
            $this->deleted[] = $models->map->getScoutKey()->all();
        }
    };

    $manager = new class(app(), $spy) extends EngineManager
    {
        public function __construct($app, public $spy)
        {
            parent::__construct($app);
        }

        public function engine($name = null)
        {
            return $this->spy;
        }
    };
    app()->instance(EngineManager::class, $manager);

    return $spy;
}

/** Run every queued SyncEventSearchIndex job now, in the order it was queued. */
function runQueuedSearchSyncs(): void
{
    Queue::pushed(SyncEventSearchIndex::class)->each(fn ($job) => $job->handle());
}

$publishedEvent = function (): Event {
    $user = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $organizer = Organizer::factory()->create(['user_id' => $user->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $organizer->id, 'user_id' => $user->id, 'status' => 'p']);
    $event->location()->create([]);
    $event->advisories()->create(['audience' => '', 'advisories' => '']);
    Queue::fake(); // the rows above queued their own syncs; count only the test's

    return $event;
};

test('syncSearchIndex queues one reconcile job for the event', function () use ($publishedEvent) {
    $event = $publishedEvent();

    $event->syncSearchIndex();

    Queue::assertPushed(SyncEventSearchIndex::class, 1);
    Queue::assertPushed(SyncEventSearchIndex::class, fn ($job) => $job->eventId === $event->id);
});

test('the job indexes a published event and removes an unpublished one, decided when it runs', function () use ($publishedEvent) {
    $event = $publishedEvent();
    $spy = recordingSearchEngine();

    $event->syncSearchIndex();
    runQueuedSearchSyncs();
    expect($spy->updated)->toBe([[$event->id]]);
    expect($spy->deleted)->toBe([]);

    Queue::fake();
    $event->update(['status' => 'd']);
    Queue::assertPushed(SyncEventSearchIndex::class, 1); // the observer path uses the same job
    runQueuedSearchSyncs();
    expect($spy->deleted)->toBe([[$event->id]]);
});

test('a delayed index job cannot resurrect an event deleted after it was queued', function () use ($publishedEvent) {
    $event = $publishedEvent();
    $spy = recordingSearchEngine();

    $event->syncSearchIndex();          // queued while published
    $event->delete();                   // soft-deleted before the job runs
    runQueuedSearchSyncs();             // both jobs, in order

    expect($spy->updated)->toBe([]);
    expect($spy->deleted)->not->toBeEmpty();
    expect(collect($spy->deleted)->flatten()->unique()->all())->toBe([$event->id]);
});

test('a delayed removal cannot drop an event republished after it was queued', function () use ($publishedEvent) {
    $event = $publishedEvent();
    $spy = recordingSearchEngine();
    $event->update(['status' => 'd']);
    Queue::fake();

    $event->syncSearchIndex();          // queued while a draft
    $event->update(['status' => 'p']);  // republished before the job runs
    runQueuedSearchSyncs();

    expect($spy->deleted)->toBe([]);
    expect(collect($spy->updated)->flatten()->unique()->all())->toBe([$event->id]);
});

test('a soft-deleted published event is removed from the index', function () use ($publishedEvent) {
    $event = $publishedEvent();
    $spy = recordingSearchEngine();
    $event->delete();

    runQueuedSearchSyncs();

    expect($spy->updated)->toBe([]);
    expect(collect($spy->deleted)->flatten()->unique()->all())->toBe([$event->id]);
});

test('with the queue off, syncSearchIndex reconciles inline', function () use ($publishedEvent) {
    $event = $publishedEvent();
    config(['scout.queue' => false]);
    $spy = recordingSearchEngine();

    $event->syncSearchIndex();

    expect($spy->updated)->toBe([[$event->id]]);
    Queue::assertNothingPushed();
});

test('a bulk searchable() (scout:import) keeps Scout\'s chunked job', function () use ($publishedEvent) {
    $a = $publishedEvent();
    $b = $publishedEvent();

    Event::whereKey([$a->id, $b->id])->get()->searchable();

    Queue::assertPushed(MakeSearchable::class, 1);
    Queue::assertNotPushed(SyncEventSearchIndex::class);
});

test('deferringSearchSync collapses every save and sync inside the block into one job', function () use ($publishedEvent) {
    $event = $publishedEvent();

    Event::deferringSearchSync(function () use ($event) {
        $event->update(['name' => 'Renamed once']);   // observer save
        $event->update(['name' => 'Renamed twice']);  // observer save
        $event->syncSearchIndex();                    // explicit, as Show/Ticket/Genre do
        $event->syncSearchIndex();
    });

    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('deferringSearchSync waits for the enclosing transaction to commit', function () use ($publishedEvent) {
    $event = $publishedEvent();

    DB::transaction(function () use ($event) {
        Event::deferringSearchSync(fn () => $event->syncSearchIndex());

        // Still inside the transaction: nothing may have reached the queue.
        Queue::assertNothingPushed();
    });

    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('deferringSearchSync still reconciles the touched events when the block throws', function () use ($publishedEvent) {
    // Rows written before the failure may have committed (the action is not
    // one transaction); a reconcile only reflects the database, so it is safe.
    $event = $publishedEvent();

    try {
        Event::deferringSearchSync(function () use ($event) {
            $event->update(['name' => 'Committed before the failure']);
            $event->syncSearchIndex();
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Queue::assertPushed(SyncEventSearchIndex::class, 1);

    // And the deferral flag was reset: a later sync behaves normally.
    $event->syncSearchIndex();
    Queue::assertPushed(SyncEventSearchIndex::class, 2);
});

test('nested deferringSearchSync blocks flush once, from the outermost block', function () use ($publishedEvent) {
    $event = $publishedEvent();

    Event::deferringSearchSync(function () use ($event) {
        Event::deferringSearchSync(fn () => $event->syncSearchIndex());
        Queue::assertNothingPushed();
        $event->syncSearchIndex();
    });

    Queue::assertPushed(SyncEventSearchIndex::class, 1);
});

test('a genre update through UpdateEventAction (MCP update-event) queues exactly one job that indexes the new genre', function () use ($publishedEvent) {
    $event = $publishedEvent();
    Genre::factory()->create(['name' => 'Immersive theatre', 'slug' => 'immersive-theatre', 'admin' => true]);
    Queue::fake();
    $spy = recordingSearchEngine();

    EiServer::actingAs($event->user)->tool(UpdateEvent::class, [
        'event_slug' => $event->slug,
        'name' => 'A renamed show',
        'genres' => [['name' => 'Immersive theatre'], ['name' => 'Spooky Season']],
        'confirm_live_edit' => true,
    ])->assertOk();

    expect($event->fresh()->name)->toBe('A renamed show');
    Queue::assertPushed(SyncEventSearchIndex::class, 1);

    runQueuedSearchSyncs();
    expect($spy->updated)->toBe([[$event->id]]);
});

$fullSchedulePayload = function (Event $event): array {
    $day = fn (int $days) => now('America/New_York')->addDays($days)->setTime(12, 0, 0)->utc()->format('Y-m-d H:i:s');

    return [
        'event_slug' => $event->slug,
        'timezone' => 'America/New_York',
        'showtype' => 's',
        'dateArray' => [$day(30), $day(31)],
        'tickets' => [
            ['name' => 'General', 'ticket_price' => 35.00, 'currency' => 'USD', 'description' => 'Standard entry'],
        ],
        'confirm_live_edit' => true,
    ];
};

test('a schedule + tickets update (inner transactions in Show and Ticket) still queues exactly one job', function () use ($publishedEvent, $fullSchedulePayload) {
    $event = $publishedEvent();
    $spy = recordingSearchEngine();

    EiServer::actingAs($event->user)->tool(UpdateEvent::class, $fullSchedulePayload($event))->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
    expect($event->fresh()->shows()->first()->tickets()->count())->toBe(1);
    Queue::assertPushed(SyncEventSearchIndex::class, 1);

    runQueuedSearchSyncs();
    expect($spy->updated)->toBe([[$event->id]]);
    expect($spy->deleted)->toBe([]);
});

test('the same update on a draft queues one job that removes it from the index', function () use ($publishedEvent, $fullSchedulePayload) {
    $event = $publishedEvent();
    $event->update(['status' => 'd']);
    Queue::fake();
    $spy = recordingSearchEngine();

    $payload = $fullSchedulePayload($event);
    unset($payload['confirm_live_edit']);
    EiServer::actingAs($event->user)->tool(UpdateEvent::class, $payload)->assertOk();

    expect($event->fresh()->shows()->count())->toBe(2);
    Queue::assertPushed(SyncEventSearchIndex::class, 1);

    runQueuedSearchSyncs();
    expect($spy->updated)->toBe([]);
    expect($spy->deleted)->toBe([[$event->id]]);
});

test('the job retries with backoff instead of immediately', function () {
    $job = new SyncEventSearchIndex(1);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([30, 120]);
});
