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

test('send-newsletter emails the configured recipients with recent events', function () {
    Mail::fake();

    $event = Event::factory()->create([
        'status' => 'p',
        'created_at' => now()->subDays(2),
    ]);

    Artisan::call('ei:send-newsletter');

    // One Newsletter to each hardcoded recipient.
    Mail::assertSent(\App\Mail\Newsletter::class, 2);
    Mail::assertSent(\App\Mail\Newsletter::class, fn ($mail) => $mail->hasTo('chgrim@gmail.com'));
    Mail::assertSent(\App\Mail\Newsletter::class, fn ($mail) => $mail->hasTo('noah@noproscenium.com'));
    // The recent published event is carried in the newsletter payload.
    Mail::assertSent(\App\Mail\Newsletter::class, fn ($mail) => $mail->events->contains('id', $event->id));
});

test('send-newsletter still emails the recipients when there are no recent events', function () {
    Mail::fake();

    Artisan::call('ei:send-newsletter');

    Mail::assertSent(\App\Mail\Newsletter::class, 2);
    Mail::assertSent(\App\Mail\Newsletter::class, fn ($mail) => $mail->events->isEmpty());
});
