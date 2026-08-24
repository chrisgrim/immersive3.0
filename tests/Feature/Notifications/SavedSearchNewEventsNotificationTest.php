<?php

use App\Mail\SavedSearchNewEventsMail;
use App\Models\Event;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchNewEventsNotification;
use Illuminate\Support\Facades\Log;

test('via includes database and mail when the search is still notify-enabled', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['notify_new_events' => true]);
    $events = Event::factory()->published()->count(2)->create();

    $notification = new SavedSearchNewEventsNotification($search, $events);

    expect($notification->via($user))->toBe(['database', 'mail']);
});

test('via suppresses delivery if notify was disabled after this job was queued', function () {
    // Regression (Codex flagged this in review): the command dispatches this
    // ShouldQueue notification while notify_new_events is still true, but
    // via() runs later, whenever the worker actually processes the job — if
    // the user disables the toggle (or hits Clear All) in that window, the
    // already-queued email must not still go out.
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['notify_new_events' => true]);
    $events = Event::factory()->published()->count(2)->create();

    $notification = new SavedSearchNewEventsNotification($search, $events);
    $search->update(['notify_new_events' => false]);

    expect($notification->via($user))->toBe([]);
});

test('via suppresses delivery if the saved search was deleted after this job was queued', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['notify_new_events' => true]);
    $events = Event::factory()->published()->count(2)->create();

    $notification = new SavedSearchNewEventsNotification($search, $events);
    $search->delete();

    expect($notification->via($user))->toBe([]);
});

test('toMail addresses a branded mailable to the notifiable, not a generic MailMessage', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);
    $search = SavedSearch::factory()->create();
    $events = Event::factory()->published()->count(1)->create();

    $mail = (new SavedSearchNewEventsNotification($search, $events))->toMail($user);

    expect($mail)->toBeInstanceOf(SavedSearchNewEventsMail::class);
    expect(collect($mail->to)->pluck('address'))->toContain('someone@example.com');
});

test('the mailable renders and mentions the search name and event names', function () {
    $search = SavedSearch::factory()->create(['name' => 'Comedy in LA']);
    Event::factory()->published()->create(['name' => 'Improv Night']);
    Event::factory()->published()->create(['name' => 'Sketch Showcase']);
    $events = Event::orderBy('id')->get();

    $rendered = (new SavedSearchNewEventsMail($search, $events))->render();

    expect($rendered)->toContain('Comedy in LA');
    expect($rendered)->toContain('Improv Night');
    expect($rendered)->toContain('Sketch Showcase');
});

test('the mailable truncates to 5 events shown individually and summarizes the rest', function () {
    // Never exercised before (caught in review) — every other render test
    // in this file uses 2-3 events, under the template's own take(5) split.
    $search = SavedSearch::factory()->create(['name' => 'Comedy in LA']);
    Event::factory()->published()->count(7)->create();
    $events = Event::orderBy('id')->get();

    $rendered = (new SavedSearchNewEventsMail($search, $events))->render();

    foreach ($events->take(5) as $event) {
        expect($rendered)->toContain($event->name);
    }
    foreach ($events->skip(5) as $event) {
        expect($rendered)->not->toContain($event->name);
    }
    expect($rendered)->toContain('and 2 more');
});

test('the mailable subject is singular for exactly one event', function () {
    $search = SavedSearch::factory()->create(['name' => 'Test Search']);
    $event = Event::factory()->published()->create();
    $mail = new SavedSearchNewEventsMail($search, Event::whereKey($event->id)->get());

    expect($mail->build()->subject)->toBe('1 new event matches "Test Search"');
});

test('the mailable subject is plural for multiple events', function () {
    $search = SavedSearch::factory()->create(['name' => 'Test Search']);
    $mail = new SavedSearchNewEventsMail($search, Event::factory()->published()->count(3)->create());

    expect($mail->build()->subject)->toBe('3 new events match "Test Search"');
});

test('toDatabase carries the search, count, a representative image, and a search url for the feed', function () {
    // The url/saved_search_name/event_count fields are what Notifications/
    // index.vue's saved_search_new_events branch reads (Codex caught in
    // review that this type used to have no compatible link or message at
    // all, falling into the other types' event_slug/event_name fields and
    // producing a broken `/events/undefined` link).
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['name' => 'My Search', 'criteria' => ['city' => 'Chicago, IL']]);
    $events = Event::factory()->published()->count(2)->create();

    $data = (new SavedSearchNewEventsNotification($search, $events))->toDatabase($user);

    expect($data)->toMatchArray([
        'type' => 'saved_search_new_events',
        'saved_search_id' => $search->id,
        'saved_search_name' => 'My Search',
        'event_count' => 2,
    ]);
    expect($data)->toHaveKey('event_image');
    expect($data['url'])->toBeString()->not->toBeEmpty();
});

test('backoff escalates across retries instead of retrying immediately', function () {
    $notification = new SavedSearchNewEventsNotification(SavedSearch::factory()->create(), new \Illuminate\Database\Eloquent\Collection);

    expect($notification->backoff)->toBe([60, 300, 900]);
});

test('a permanently-failed send logs which saved search and error it was', function () {
    Log::spy();
    $search = SavedSearch::factory()->create();
    $exception = new Exception('mail server unreachable');

    (new SavedSearchNewEventsNotification($search, new \Illuminate\Database\Eloquent\Collection))->failed($exception);

    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message, $context) => $message === '[notifications] saved_search_new_events permanently failed'
            && $context['saved_search_id'] === $search->id
            && $context['exception'] === 'mail server unreachable'
    );
});
