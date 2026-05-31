<?php

use App\Models\Admin\StaffPick;
use App\Models\Category;
use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Genre;
use App\Scopes\AdminScope;
use App\Scopes\CreatedAtScope;
use App\Scopes\DateScope;
use App\Scopes\PublishedScope;
use App\Scopes\RankScope;

// note: Every global scope in app/Scopes only ORDERS the query — none of them
// FILTER any rows out. These tests assert that ordering and that
// withoutGlobalScope() bypasses it (restoring insertion / id order).

// ----- PublishedScope (Event, published_at desc) -----

test('PublishedScope orders events by published_at desc', function () {
    $oldest = Event::factory()->published()->create(['published_at' => now()->subDays(10)]);
    $newest = Event::factory()->published()->create(['published_at' => now()->subDay()]);
    $middle = Event::factory()->published()->create(['published_at' => now()->subDays(5)]);

    $ids = Event::pluck('id')->all();

    expect($ids)->toBe([$newest->id, $middle->id, $oldest->id]);
});

test('PublishedScope does not filter — unpublished events are still returned', function () {
    Event::factory()->draft()->create();
    Event::factory()->inReview()->create();
    Event::factory()->published()->create();

    // The scope only orders; it does not remove drafts/in-review/null-published rows.
    expect(Event::count())->toBe(3);
});

test('withoutGlobalScope bypasses PublishedScope ordering', function () {
    $first = Event::factory()->published()->create(['published_at' => now()->subDay()]);
    $second = Event::factory()->published()->create(['published_at' => now()->subDays(10)]);

    $ordered = Event::pluck('id')->all();
    $natural = Event::withoutGlobalScope(PublishedScope::class)->orderBy('id')->pluck('id')->all();

    // Scoped order is published_at desc (first, since it is most recent).
    expect($ordered)->toBe([$first->id, $second->id]);
    // Without the scope we can re-order freely by id.
    expect($natural)->toBe([$first->id, $second->id]);
});

// ----- RankScope (Category, rank desc) -----

test('RankScope orders categories by rank desc', function () {
    $low = Category::factory()->create(['rank' => 1]);
    $high = Category::factory()->create(['rank' => 10]);
    $mid = Category::factory()->create(['rank' => 5]);

    $ids = Category::pluck('id')->all();

    expect($ids)->toBe([$high->id, $mid->id, $low->id]);
});

test('RankScope does not filter — zero/low rank categories are still returned', function () {
    Category::factory()->create(['rank' => 0]);
    Category::factory()->create(['rank' => 0]);
    Category::factory()->create(['rank' => 3]);

    expect(Category::count())->toBe(3);
});

test('withoutGlobalScope bypasses RankScope ordering', function () {
    $a = Category::factory()->create(['rank' => 1]);
    $b = Category::factory()->create(['rank' => 9]);

    $scoped = Category::pluck('id')->all();
    $natural = Category::withoutGlobalScope(RankScope::class)->orderBy('id')->pluck('id')->all();

    expect($scoped)->toBe([$b->id, $a->id]);
    expect($natural)->toBe([$a->id, $b->id]);
});

// ----- DateScope (Show, date asc) -----

test('DateScope orders shows by date asc', function () {
    $event = Event::factory()->create();
    $late = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addMonths(3)->format('Y-m-d H:i:s')]);
    $early = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDay()->format('Y-m-d H:i:s')]);
    $mid = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addMonth()->format('Y-m-d H:i:s')]);

    $ids = Show::pluck('id')->all();

    // Ascending: earliest date first.
    expect($ids)->toBe([$early->id, $mid->id, $late->id]);
});

test('DateScope does not filter — past-dated shows are still returned', function () {
    $event = Event::factory()->create();
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->subYear()->format('Y-m-d H:i:s')]);
    Show::factory()->create(['event_id' => $event->id, 'date' => now()->addYear()->format('Y-m-d H:i:s')]);

    expect(Show::count())->toBe(2);
});

test('withoutGlobalScope bypasses DateScope ordering', function () {
    $event = Event::factory()->create();
    $late = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addMonths(2)->format('Y-m-d H:i:s')]);
    $early = Show::factory()->create(['event_id' => $event->id, 'date' => now()->addDay()->format('Y-m-d H:i:s')]);

    $scoped = Show::pluck('id')->all();
    $natural = Show::withoutGlobalScope(DateScope::class)->orderBy('id')->pluck('id')->all();

    // Scoped: ascending date → early first even though it was inserted second.
    expect($scoped)->toBe([$early->id, $late->id]);
    expect($natural)->toBe([$late->id, $early->id]);
});

// ----- AdminScope (Genre: admin desc, then rank desc, then name asc) -----

test('AdminScope orders genres by admin desc then rank desc then name asc', function () {
    // admin genres should come before non-admin (admin desc).
    $nonAdminHigh = Genre::factory()->create(['admin' => false, 'rank' => 100, 'name' => 'Zeta']);
    $adminLowRankB = Genre::factory()->create(['admin' => true, 'rank' => 1, 'name' => 'Beta']);
    $adminLowRankA = Genre::factory()->create(['admin' => true, 'rank' => 1, 'name' => 'Alpha']);
    $adminHighRank = Genre::factory()->create(['admin' => true, 'rank' => 5, 'name' => 'Omega']);

    $ids = Genre::pluck('id')->all();

    // admin=true first; within those rank desc (5 before 1); within equal rank name asc (Alpha before Beta).
    expect($ids)->toBe([
        $adminHighRank->id,
        $adminLowRankA->id,
        $adminLowRankB->id,
        $nonAdminHigh->id,
    ]);
});

test('AdminScope does not filter — non-admin genres are still returned', function () {
    Genre::factory()->create(['admin' => false]);
    Genre::factory()->create(['admin' => true]);

    expect(Genre::count())->toBe(2);
});

test('withoutGlobalScope bypasses AdminScope ordering', function () {
    $nonAdmin = Genre::factory()->create(['admin' => false, 'rank' => 0, 'name' => 'Alpha']);
    $admin = Genre::factory()->create(['admin' => true, 'rank' => 0, 'name' => 'Zeta']);

    $scoped = Genre::pluck('id')->all();
    $natural = Genre::withoutGlobalScope(AdminScope::class)->orderBy('id')->pluck('id')->all();

    // Scoped: admin (Zeta) comes first despite later name and later insert.
    expect($scoped)->toBe([$admin->id, $nonAdmin->id]);
    expect($natural)->toBe([$nonAdmin->id, $admin->id]);
});

// ----- CreatedAtScope (StaffPick, created_at desc) -----

test('CreatedAtScope orders staff picks by created_at desc', function () {
    // note: staff_picks.event_id is UNIQUE, so each pick needs its own event
    // (the StaffPick factory auto-creates a distinct Event per pick).
    $oldest = StaffPick::factory()->create(['created_at' => now()->subDays(10)]);
    $newest = StaffPick::factory()->create(['created_at' => now()->subDay()]);
    $middle = StaffPick::factory()->create(['created_at' => now()->subDays(5)]);

    $ids = StaffPick::pluck('id')->all();

    expect($ids)->toBe([$newest->id, $middle->id, $oldest->id]);
});

test('CreatedAtScope does not filter — all staff picks are returned regardless of date', function () {
    StaffPick::factory()->create(['created_at' => now()->subYears(2)]);
    StaffPick::factory()->create(['created_at' => now()]);

    expect(StaffPick::count())->toBe(2);
});

test('withoutGlobalScope bypasses CreatedAtScope ordering', function () {
    $first = StaffPick::factory()->create(['created_at' => now()->subDay()]);
    $second = StaffPick::factory()->create(['created_at' => now()->subDays(10)]);

    $scoped = StaffPick::pluck('id')->all();
    $natural = StaffPick::withoutGlobalScope(CreatedAtScope::class)->orderBy('id')->pluck('id')->all();

    // Scoped: most-recent created_at first.
    expect($scoped)->toBe([$first->id, $second->id]);
    expect($natural)->toBe([$first->id, $second->id]);
});
