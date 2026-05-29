<?php

use App\Models\Event;
use App\Models\TrackClick;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

afterEach(function () {
    Carbon::setTestNow();
});

// ----- ei:archive-clicks (ArchiveOldClicks) -----

test('archive-clicks deletes click rows older than 90 days and keeps recent ones', function () {
    // Age an old row by creating it 100 days ago, then return to "now".
    $this->travelTo(now()->subDays(100));
    $old = TrackClick::factory()->create();
    $this->travelBack();

    $recent = TrackClick::factory()->create(['created_at' => now()->subDays(10)]);

    $exit = Artisan::call('ei:archive-clicks');

    expect($exit)->toBe(0);
    expect(TrackClick::find($old->id))->toBeNull();
    expect(TrackClick::find($recent->id))->not->toBeNull();
});

test('archive-clicks keeps a row sitting exactly on the cutoff boundary', function () {
    // Cutoff is now()->subDays(90); the delete uses created_at < cutoff (strict),
    // so a row created exactly 90 days ago is kept.
    $onCutoff = TrackClick::factory()->create(['created_at' => now()->subDays(90)]);

    Artisan::call('ei:archive-clicks');

    expect(TrackClick::find($onCutoff->id))->not->toBeNull();
});

test('archive-clicks honours the --days option', function () {
    $row = TrackClick::factory()->create(['created_at' => now()->subDays(20)]);

    // With --days=10 the 20-day-old row is now past cutoff and gets deleted.
    Artisan::call('ei:archive-clicks', ['--days' => 10]);

    expect(TrackClick::find($row->id))->toBeNull();
});

test('archive-clicks reports the number deleted', function () {
    $this->travelTo(now()->subDays(120));
    TrackClick::factory()->count(3)->create();
    $this->travelBack();

    Artisan::call('ei:archive-clicks');

    expect(Artisan::output())->toContain('Deleted 3 click rows');
});

// ----- ei:check-closing-events (CheckClosingEvents) -----

// note: the body of CheckClosingEvents is DISABLED — handle() returns Command::SUCCESS
// immediately and the original logic is commented out. See skipped[].
test('check-closing-events is disabled and sends no mail', function () {
    Mail::fake();

    // Even with an event closing within the old 4-5 day window, nothing should send.
    Event::factory()->create([
        'status' => 'p',
        'closingDate' => now()->addDays(4)->addHours(12),
    ]);

    $exit = Artisan::call('ei:check-closing-events');

    expect($exit)->toBe(0);
    Mail::assertNothingSent();
});

// ----- ei:send-newsletter (NewsletterCommand) -----

// note: BUG — NewsletterCommand references App\Mail\Newsletter, which does not exist
// in the codebase. The command fatals with "Class App\Mail\Newsletter not found"
// the moment it tries to build the mailable, so it can never send and is effectively
// broken. We assert the actual (throwing) behavior. See bugsFound[].
test('send-newsletter throws because the Newsletter mailable is missing', function () {
    Mail::fake();

    Event::factory()->create([
        'status' => 'p',
        'created_at' => now()->subDays(2),
    ]);

    expect(fn () => Artisan::call('ei:send-newsletter'))
        ->toThrow(\Error::class, 'Class "App\Mail\Newsletter" not found');

    // It dies before queuing/sending anything.
    Mail::assertNothingSent();
});

test('send-newsletter throws even when there are no recent events', function () {
    Mail::fake();

    // The query runs fine (returns an empty collection); the crash is on `new Newsletter`.
    expect(fn () => Artisan::call('ei:send-newsletter'))
        ->toThrow(\Error::class, 'Class "App\Mail\Newsletter" not found');

    Mail::assertNothingSent();
});
