<?php

use App\Mail\Message as MessageMail;
use App\Models\Messaging\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Replying in the inbox emails the other person if they were caught up.
 * emails/message.blade.php is shared with App\Mail\Comments and prints
 * $attributes['title']; ConversationsController never supplied one, so the
 * view threw and the catch block swallowed it: no inbox email ever went out
 * (Sentry EI-LARAVEL-1E, 2026-09-15). Mail::fake() does not render the view,
 * so the render is asserted explicitly here.
 */
test('an inbox reply emails a caught-up receiver and the email renders', function () {
    Mail::fake();

    $sender = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $receiver = User::factory()->create(['type' => 'u', 'unread' => null]);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $receiver->id,
        'subject' => 'Sleep No More tickets',
    ]);

    $this->actingAs($sender)
        ->postJson("/inbox/conversation/{$conversation->id}", ['message' => 'Still available?'])
        ->assertOk();

    expect($receiver->fresh()->unread)->toBe('m');

    Mail::assertSent(MessageMail::class, function (MessageMail $mail) use ($receiver, $sender) {
        $html = $mail->render();

        return $mail->hasTo($receiver->email)
            && str_contains($html, 'New Message From '.$sender->name)
            && str_contains($html, 'Sleep No More tickets');
    });
});

test('a receiver who already has unread messages is not emailed again', function () {
    Mail::fake();

    $sender = User::factory()->create(['type' => 'u', 'email_verified_at' => now()]);
    $receiver = User::factory()->create(['type' => 'u', 'unread' => 'm']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $receiver->id,
    ]);

    $this->actingAs($sender)
        ->postJson("/inbox/conversation/{$conversation->id}", ['message' => 'Hello again'])
        ->assertOk();

    Mail::assertNothingSent();
});
