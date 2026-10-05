<?php

use App\Actions\Admin\ModerateSubmission;
use App\Mail\Comments;
use App\Models\Event;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The moderation steps the event, organizer and community admin controllers
 * share (they used to be three drifting copies).
 */
beforeEach(fn () => Mail::fake());

test('rejecting sends the owner the reason in-app and by email, worded the same for every kind', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $owner->id, 'status' => 'r']);
    $this->actingAs(User::factory()->create(['type' => 'm']));

    app(ModerateSubmission::class)->reject($event, $owner, 'Add a ticket link.', 'event', Message::MESSAGES['REJECTED']);

    expect($event->fresh()->status)->toBe('n')
        ->and(Message::latest('id')->value('message'))->toBe("We've reviewed your event and have some feedback that needs to be addressed.\n\nReason: Add a ticket link.");
    Mail::assertQueued(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('the queued rejection email still renders if the event is deleted before it goes out', function () {
    $event = Event::factory()->create(['name' => 'Deleted Before Delivery', 'status' => 'r']);
    $this->actingAs(User::factory()->create(['type' => 'm']));
    $mail = serialize(new Comments($event, 'Reason: x', 'rejected'));

    $event->forceDelete();

    $mail = unserialize($mail);
    expect($mail->render())->toContain('Deleted Before Delivery')
        ->and($mail->envelope()->subject)->toBe('Update About Deleted Before Delivery');
});

test('a moderator acting on their own submission, or one with no owner, sends nothing and does not fail', function () {
    $moderator = User::factory()->create(['type' => 'm']);
    $this->actingAs($moderator);
    $own = Event::factory()->create(['user_id' => $moderator->id, 'status' => 'r']);
    $orphan = Event::factory()->create(['status' => 'r']);

    app(ModerateSubmission::class)->reject($own, $moderator, 'x', 'event', Message::MESSAGES['REJECTED']);
    app(ModerateSubmission::class)->reject($orphan, null, 'x', 'event', Message::MESSAGES['REJECTED']);

    expect($own->fresh()->status)->toBe('n')->and($orphan->fresh()->status)->toBe('n');
    Mail::assertNothingQueued();
});
