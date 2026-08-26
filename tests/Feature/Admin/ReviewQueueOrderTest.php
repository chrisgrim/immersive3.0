<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

/**
 * The moderation queue is interleaved by organizer so near-identical listings
 * do not sit next to each other. The case it exists for: one organizer enters a
 * multi-city chain in a single sitting (six "The Drunken Lab <city>" events
 * created within six seconds), and a newest-first sort hands the moderator all
 * six back to back.
 */
function moderator(): User
{
    return User::factory()->create(['type' => 'm']);
}

function queuePage(int $page = 1): array
{
    return collect(test()->getJson("/api/admin/approve/events?page={$page}")->json('data'))
        ->pluck('id')->all();
}

function queueOrganizers(int $page = 1): array
{
    return collect(test()->getJson("/api/admin/approve/events?page={$page}")->json('data'))
        ->pluck('organizer_id')->all();
}

/** A batch of events all belonging to one organizer, created seconds apart. */
function batch(int $count, ?Organizer $organizer = null): Organizer
{
    $organizer = $organizer ?: Organizer::factory()->create(['status' => 'p']);

    foreach (range(1, $count) as $i) {
        Event::factory()->create([
            'organizer_id' => $organizer->id,
            'status' => 'r',
            'created_at' => now()->subSeconds($i),
        ]);
    }

    return $organizer;
}

/** The longest run of consecutive events from the same organizer. */
function longestRun(array $organizerIds): int
{
    $longest = 0;
    $current = 0;
    $previous = null;

    foreach ($organizerIds as $id) {
        $current = ($id === $previous) ? $current + 1 : 1;
        $longest = max($longest, $current);
        $previous = $id;
    }

    return $longest;
}

test('the queue is newest-first when interleaving is off', function () {
    config(['ei.interleave_review_queue' => false]);
    batch(5);
    $this->actingAs(moderator());

    $expected = Event::where('status', 'r')->orderByDesc('created_at')->limit(20)->pluck('id')->all();

    expect(queuePage())->toBe($expected);
});

test('no two events from the same organizer sit next to each other', function () {
    config(['ei.interleave_review_queue' => true]);
    // Today's real queue shape: one organizer with six, one with three, and
    // three singletons. Six others exist to separate the six, so a perfect
    // alternation is possible and nothing should ever repeat back to back.
    batch(6);
    batch(3);
    batch(1);
    batch(1);
    batch(1);
    $this->actingAs(moderator());

    expect(longestRun(queueOrganizers()))->toBe(1);
});

test('a newest-first sort would have clustered them, so the test above is meaningful', function () {
    // Guards against the interleave test passing trivially. With the same data
    // sorted by date, the six-event batch lands as one unbroken run.
    config(['ei.interleave_review_queue' => false]);
    batch(6);
    batch(3);
    batch(1);
    $this->actingAs(moderator());

    expect(longestRun(queueOrganizers()))->toBeGreaterThan(1);
});

test('a dominant organizer is spread as far as arithmetic allows, and loses nothing', function () {
    config(['ei.interleave_review_queue' => true]);
    // Nine of eleven from one organizer: they cannot all be separated, but the
    // two others must still be used to break the run rather than dumped at one
    // end, and every event must still be present.
    $big = batch(9);
    batch(1);
    batch(1);
    $this->actingAs(moderator());

    $organizers = queueOrganizers();

    expect(count($organizers))->toBe(11);
    // Best possible: the 2 spacers split the 9 into runs of 3, 3, 3.
    expect(longestRun($organizers))->toBe(3);
    expect(collect($organizers)->filter(fn ($id) => $id === $big->id))->toHaveCount(9);
});

test('the order is stable across repeated loads, so paginating cannot repeat or skip an event', function () {
    // Pagination slices a re-derived order. If interleaving were not
    // deterministic, page 2 would come from a different arrangement than page
    // 1 — showing some events twice and hiding others entirely.
    config(['ei.interleave_review_queue' => true]);
    batch(12);
    batch(8);
    batch(5);
    $this->actingAs(moderator());

    expect(queuePage(1))->toBe(queuePage(1));

    $everything = array_merge(queuePage(1), queuePage(2));

    expect($everything)->toHaveCount(25);
    expect(array_unique($everything))->toHaveCount(25);
});

test('interleaving never changes which events are in the queue', function () {
    config(['ei.interleave_review_queue' => true]);
    batch(6);
    batch(3);
    Event::factory()->count(3)->create(['status' => 'p']);
    Event::factory()->count(2)->create(['status' => 'n']);
    $this->actingAs(moderator());

    $response = $this->getJson('/api/admin/approve/events')->assertOk();

    expect($response->json('total'))->toBe(9);
});

test('an empty queue does not blow up', function () {
    config(['ei.interleave_review_queue' => true]);
    $this->actingAs(moderator());

    $response = $this->getJson('/api/admin/approve/events')->assertOk();

    expect($response->json('data'))->toBe([]);
    expect($response->json('total'))->toBe(0);
});

test('interleaving is on by default and can be turned off from the server env', function () {
    // Every test above sets the config value explicitly, so none of them touch
    // the default or the env binding — the two things that decide what actually
    // happens in production and how it gets reverted without a deploy.
    $config = require config_path('ei.php');

    expect($config['interleave_review_queue'])->toBeTrue();
    expect(file_get_contents(config_path('ei.php')))
        ->toContain("env('EI_INTERLEAVE_REVIEW_QUEUE', true)");
});
