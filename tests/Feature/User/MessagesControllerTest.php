<?php

use App\Http\Controllers\User\MessagesController;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// note: MessagesController::checkUnread() and markAllRead() are NOT wired to any
// route in routes/web.php, routes/api.php, routes/auth.php or routes/curated.php.
// They are therefore invoked directly on the controller with an authenticated
// user (Auth::login). See skipped[] for the missing-route note.
//
// note: a "conversation" here is keyed by the user_one / user_two columns on the
// conversations table (the controller queries those directly), NOT by the
// conversation_user pivot. Unread is determined per message via is_seen=false and
// user_id != current user.

beforeEach(function () {
    $this->user = User::factory()->create(['type' => 'u']);
    $this->other = User::factory()->create(['type' => 'u']);
});

function controller(): MessagesController
{
    return app(MessagesController::class);
}

// ----- checkUnread() -----

test('checkUnread reports no unread when there are no messages', function () {
    Auth::login($this->user);

    $payload = controller()->checkUnread()->getData(true);

    expect($payload['has_unread'])->toBeFalse();
    expect($payload['unread_count'])->toBe(0);
});

test('checkUnread counts conversations with unread messages from the other party', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);
    $this->user->update(['unread' => 'm']);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['has_unread'])->toBeTrue();
    expect($payload['unread_count'])->toBe(1);
});

test('checkUnread ignores the users own unread messages', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    // Message authored by the current user — should not count as unread for them.
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->user->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['unread_count'])->toBe(0);
});

test('checkUnread ignores seen messages', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => true,
    ]);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['unread_count'])->toBe(0);
});

test('checkUnread counts conversations where the user is user_two', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->other->id,
        'user_two' => $this->user->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['unread_count'])->toBe(1);
});

test('checkUnread does not count conversations the user is not part of', function () {
    $third = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $this->other->id,
        'user_two' => $third->id,
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['unread_count'])->toBe(0);
});

test('checkUnread has_unread reflects the unread flag not the count', function () {
    // The flag is driven purely by users.unread === 'm', independent of any
    // actual unread message rows.
    $this->user->update(['unread' => 'm']);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['has_unread'])->toBeTrue();
    expect($payload['unread_count'])->toBe(0);
});

test('checkUnread counts each qualifying conversation once', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    // Two unread messages in the same conversation still count as one conversation.
    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    $payload = controller()->checkUnread()->getData(true);

    expect($payload['unread_count'])->toBe(1);
});

// ----- markAllRead() -----

test('markAllRead marks the other partys unread messages as seen', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    $unread = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    $response = controller()->markAllRead();

    expect($response->getData(true))->toBe(['status' => 'success']);
    expect((bool) $unread->fresh()->is_seen)->toBeTrue();
});

test('markAllRead resets the users unread flag to null', function () {
    $this->user->update(['unread' => 'm']);

    Auth::login($this->user);
    controller()->markAllRead();

    expect($this->user->fresh()->unread)->toBeNull();
});

test('markAllRead does not touch the users own messages', function () {
    $conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
    $ownMessage = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->user->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    controller()->markAllRead();

    // The current user's own outgoing message stays unseen (it is "unread" for the recipient).
    expect((bool) $ownMessage->fresh()->is_seen)->toBeFalse();
});

test('markAllRead does not touch messages in other peoples conversations', function () {
    $third = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $this->other->id,
        'user_two' => $third->id,
    ]);
    $foreign = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $this->other->id,
        'is_seen' => false,
    ]);

    Auth::login($this->user);
    controller()->markAllRead();

    expect((bool) $foreign->fresh()->is_seen)->toBeFalse();
});

test('markAllRead is idempotent and succeeds with no conversations', function () {
    Auth::login($this->user);

    $response = controller()->markAllRead();

    expect($response->getData(true))->toBe(['status' => 'success']);
});
