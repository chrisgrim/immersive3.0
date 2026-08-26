<?php

use App\Models\Event;
use App\Models\User;

/**
 * The moderation queue can be shuffled so the same events don't sit at the top
 * of a date sort forever. The shuffle has to be SEEDED: MySQL evaluates a bare
 * RAND() per query, so paginating an unseeded shuffle reshuffles between pages
 * — repeating some events and hiding others, which is worse than not shuffling.
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

beforeEach(function () {
    Event::factory()->count(25)->create(['status' => 'r']);
});

test('the queue is newest-first when shuffling is off', function () {
    config(['ei.shuffle_review_queue' => false]);
    $this->actingAs(moderator());

    $ids = queuePage();
    $expected = Event::where('status', 'r')->orderByDesc('created_at')->limit(20)->pluck('id')->all();

    expect($ids)->toBe($expected);
});

test('shuffling changes the order', function () {
    $this->actingAs(moderator());

    config(['ei.shuffle_review_queue' => false]);
    $sorted = array_merge(queuePage(1), queuePage(2));

    config(['ei.shuffle_review_queue' => true]);
    $shuffled = array_merge(queuePage(1), queuePage(2));

    // Compared across BOTH pages, not page 1 alone: with 25 events, shuffling
    // changes which 20 land on the first page, so a page-1 comparison would
    // pass even against a queue that had simply lost events.
    expect($shuffled)->not->toBe($sorted);
    expect(collect($shuffled)->sort()->values()->all())
        ->toBe(collect($sorted)->sort()->values()->all());
});

test('the order is stable across repeated loads, so paginating cannot repeat or skip an event', function () {
    // The failure this prevents: an unseeded RAND() reshuffles per query, so
    // page 2 is drawn from a different ordering than page 1 — some events
    // appear twice and others are never shown at all.
    config(['ei.shuffle_review_queue' => true]);
    $this->actingAs(moderator());

    expect(queuePage(1))->toBe(queuePage(1));

    $everything = array_merge(queuePage(1), queuePage(2));

    expect($everything)->toHaveCount(25);
    expect(array_unique($everything))->toHaveCount(25);
});

test('two moderators working at once get different orders', function () {
    config(['ei.shuffle_review_queue' => true]);

    $this->actingAs(moderator());
    $first = queuePage();

    $this->actingAs(moderator());
    $second = queuePage();

    expect($first)->not->toBe($second);
});

test('the same moderator gets a different order tomorrow', function () {
    config(['ei.shuffle_review_queue' => true]);
    $this->actingAs(moderator());

    $today = queuePage();

    $this->travel(1)->day();
    $tomorrow = queuePage();
    $this->travelBack();

    expect($tomorrow)->not->toBe($today);
});

test('shuffling never changes which events are in the queue', function () {
    config(['ei.shuffle_review_queue' => true]);
    Event::factory()->count(3)->create(['status' => 'p']);
    Event::factory()->count(2)->create(['status' => 'n']);
    $this->actingAs(moderator());

    $response = $this->getJson('/api/admin/approve/events')->assertOk();

    expect($response->json('total'))->toBe(25);
});

test('shuffling is on by default and can be turned off from the server env', function () {
    // Every test above sets the config value explicitly, so none of them touch
    // the default or the env binding — the two things that decide what actually
    // happens in production and how it gets reverted without a deploy.
    $config = require config_path('ei.php');

    expect($config['shuffle_review_queue'])->toBeTrue();
    expect(file_get_contents(config_path('ei.php')))
        ->toContain("env('EI_SHUFFLE_REVIEW_QUEUE', true)");
});
