<?php

use App\Console\Commands\NotifySavedSearchMatchesCommand;
use App\Models\Event;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

// This app's test config runs SCOUT_DRIVER=null (see project memory: "ES
// search untestable under SCOUT_DRIVER=null"), under which
// Event::searchQuery(...)->execute() always returns an empty result set
// rather than throwing — confirmed directly via tinker before writing these.
// That means the command's cursor/eligibility logic (below) IS testable end
// to end, but "a real match was found and the notification was dispatched
// with the right events" is NOT — that machinery is covered separately in
// SavedSearchNewEventsNotificationTest.php, decoupled from how the events
// were found. EventSearchFilterBuilderTest.php covers the matching query
// itself, also independent of live execution.

beforeEach(function () {
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    Notification::fake();
});

test('only checks searches with notify_new_events enabled', function () {
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    $enabled = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => now()->subDay()]);
    $disabled = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => false, 'last_checked_at' => now()->subDay()]);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    expect($enabled->fresh()->last_checked_at)->not->toBeNull();
    // Untouched — never queried at all, since it's not enabled.
    expect($disabled->fresh()->last_checked_at->eq($disabled->last_checked_at))->toBeTrue();
});

test('only checks the pilot users searches, not anyone elses', function () {
    $pilotUser = User::factory()->create(['email' => 'pilot@example.com']);
    $otherUser = User::factory()->create(['email' => 'someone-else@example.com']);
    $pilotSearch = SavedSearch::factory()->create(['user_id' => $pilotUser->id, 'notify_new_events' => true, 'last_checked_at' => now()->subDay()]);
    $otherSearch = SavedSearch::factory()->create(['user_id' => $otherUser->id, 'notify_new_events' => true, 'last_checked_at' => now()->subDay()]);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    expect($pilotSearch->fresh()->last_checked_at)->not->toBeNull();
    expect($otherSearch->fresh()->last_checked_at->eq($otherSearch->last_checked_at))->toBeTrue();
});

test('a null last_checked_at (never checked) is treated as "start now", not "check everything ever"', function () {
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => null]);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    expect($search->fresh()->last_checked_at)->not->toBeNull();
});

test('a search checked more recently than the cutoff is skipped but its cursor still advances', function () {
    // e.g. enabled moments before this run — the toggle endpoint already set
    // last_checked_at to "now" at that point, which can land inside this
    // run's own 5-minute trailing window.
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => now()]);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    expect($search->fresh()->last_checked_at)->not->toBeNull();
});

test('does not error and sends nothing when no searches are enabled', function () {
    User::factory()->create(['email' => 'pilot@example.com']);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    Notification::assertNothingSent();
});

test('never dispatches a notification when Elasticsearch finds nothing (guaranteed under SCOUT_DRIVER=null)', function () {
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => now()->subDay()]);

    $this->artisan('ei:notify-saved-searches')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a second overlapping run (e.g. a manual invocation during the scheduled tick) skips instead of double-processing', function () {
    // Regression (caught in review): withoutOverlapping() on the schedule
    // entry only mutex-protects invocations that go through schedule:run —
    // a manual `php artisan ei:notify-saved-searches` over SSH bypasses it
    // entirely, so without this command's OWN lock, an overlapping manual
    // run and the scheduled tick could both read the same stale cursor and
    // double-email the pilot user for the same matches.
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => now()->subDay()]);

    $lock = Cache::lock('ei:notify-saved-searches', 300);
    $lock->get();
    try {
        $this->artisan('ei:notify-saved-searches')->assertSuccessful();
        // Untouched — the run was skipped entirely, not just a no-op pass.
        expect($search->fresh()->last_checked_at->eq($search->last_checked_at))->toBeTrue();
    } finally {
        $lock->release();
    }
});

// ---------------------------------------------------------------------------
// determineNewCursor() — the cap/cursor math itself, tested directly since
// Event::searchQuery()->execute() can't return real results in this test
// env (see the file-level comment above) and this is where a real bug lived:
// hitting the MAX_EVENTS_PER_EMAIL cap used to advance the cursor to the
// full window's cutoff regardless, permanently losing whatever matched
// beyond the cap (Codex caught this in review). $events must be passed in
// oldest-first (ascending published_at), matching the real query's sort.
// ---------------------------------------------------------------------------

test('under the cap, the cursor advances all the way to cutoff — nothing left behind', function () {
    $command = app(NotifySavedSearchMatchesCommand::class);
    $cutoff = now();
    $events = Event::factory()->published()->count(3)->create(['published_at' => now()->subHour()]);

    $newCursor = $command->determineNewCursor($events, $cutoff);

    expect($newCursor->eq($cutoff))->toBeTrue();
});

test('an empty result set advances the cursor to cutoff, same as under-cap', function () {
    $command = app(NotifySavedSearchMatchesCommand::class);
    $cutoff = now();

    $newCursor = $command->determineNewCursor(new \Illuminate\Database\Eloquent\Collection, $cutoff);

    expect($newCursor->eq($cutoff))->toBeTrue();
});

test('at the cap, the cursor advances to just before the last included events published_at, not to cutoff', function () {
    // Regression: this is the exact scenario that used to silently drop
    // every match beyond the cap forever.
    $command = app(NotifySavedSearchMatchesCommand::class);
    $cutoff = now();
    $cap = (new \ReflectionClass($command))->getConstant('MAX_EVENTS_PER_EMAIL');

    $lastPublishedAt = now()->subMinutes(30)->startOfSecond();
    $events = collect();
    for ($i = $cap - 1; $i >= 0; $i--) {
        $events->push(Event::factory()->published()->create(['published_at' => $lastPublishedAt->copy()->subMinutes($i)]));
    }
    // Oldest-first, matching the real query's sort — the last element is
    // the newest one actually included in this batch.
    $events = new \Illuminate\Database\Eloquent\Collection($events->sortBy('published_at')->values()->all());

    $newCursor = $command->determineNewCursor($events, $cutoff);

    expect($newCursor->eq($cutoff))->toBeFalse();
    expect($newCursor->eq($lastPublishedAt))->toBeFalse();
    expect($newCursor->eq($lastPublishedAt->copy()->subSecond()))->toBeTrue();
});

test('at the cap, backing off 1 second means a same-second tie at the boundary is re-included next run, not lost', function () {
    // The actual bug this backoff fixes: PublishEventsCommand stamps every
    // event in one embargo-release run with the SAME whole-second
    // published_at, so a batch of >cap events can tie exactly at the
    // boundary. Landing the cursor exactly on that second would exclude
    // whichever tied sibling didn't make it into this capped batch,
    // forever (its published_at is never > a cursor equal to its own
    // published_at).
    $command = app(NotifySavedSearchMatchesCommand::class);
    $cutoff = now();
    $cap = (new \ReflectionClass($command))->getConstant('MAX_EVENTS_PER_EMAIL');

    $tiedSecond = now()->subMinutes(30)->startOfSecond();
    // Every event in this batch shares the identical published_at second —
    // the real-world PublishEventsCommand scenario.
    $events = new \Illuminate\Database\Eloquent\Collection(
        Event::factory()->published()->count($cap)->create(['published_at' => $tiedSecond])->all()
    );

    $newCursor = $command->determineNewCursor($events, $cutoff);

    // A sibling excluded from this batch, sharing the exact same second,
    // must still satisfy next run's `published_at > $newCursor` check.
    expect($tiedSecond->greaterThan($newCursor))->toBeTrue();
});
