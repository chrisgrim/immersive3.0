<?php

use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\User;

// ==========================================================================
// ConversationPolicy — view / update / viewAny
// ==========================================================================
// note: ConversationPolicy::canAccessConversation() allows user_one, user_two,
// or any moderator (isModerator() === type 'm' or 'a'). A third unrelated user
// is denied. viewAny() always returns true for any authenticated user.

test('viewAny is allowed for any authenticated user', function () {
    $user = User::factory()->create(['type' => 'u']);

    expect($user->can('viewAny', Conversation::class))->toBeTrue();
});

test('conversation view and update are allowed for user_one', function () {
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($userOne->can('view', $conversation))->toBeTrue();
    expect($userOne->can('update', $conversation))->toBeTrue();
});

test('conversation view and update are allowed for user_two', function () {
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($userTwo->can('view', $conversation))->toBeTrue();
    expect($userTwo->can('update', $conversation))->toBeTrue();
});

test('conversation view and update are allowed for a moderator', function () {
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $moderator = User::factory()->create(['type' => 'm']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($moderator->can('view', $conversation))->toBeTrue();
    expect($moderator->can('update', $conversation))->toBeTrue();
});

test('conversation view and update are allowed for an admin', function () {
    // note: isModerator() returns true for type 'a' too, so admins pass.
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $admin = User::factory()->create(['type' => 'a']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($admin->can('view', $conversation))->toBeTrue();
    expect($admin->can('update', $conversation))->toBeTrue();
});

test('conversation view and update are denied for a third unrelated user', function () {
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $stranger = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($stranger->can('view', $conversation))->toBeFalse();
    expect($stranger->can('update', $conversation))->toBeFalse();
});

test('conversation view and update are denied for a curator who is not a participant', function () {
    // note: a curator (type 'c') is NOT a moderator, so they are denied access
    // to conversations they are not part of.
    $userOne = User::factory()->create(['type' => 'u']);
    $userTwo = User::factory()->create(['type' => 'u']);
    $curator = User::factory()->create(['type' => 'c']);
    $conversation = Conversation::factory()->create([
        'user_one' => $userOne->id,
        'user_two' => $userTwo->id,
    ]);

    expect($curator->can('view', $conversation))->toBeFalse();
    expect($curator->can('update', $conversation))->toBeFalse();
});

// ==========================================================================
// MessagePolicy — view / update
// ==========================================================================
// note: MessagePolicy::view() allows either conversation participant
// (user_one/user_two) or admin/moderator. update() allows the message sender
// (message.user_id) or admin/moderator. A curator (type 'c') has no special
// privileges here.

test('message view is allowed for a conversation participant', function () {
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($sender->can('view', $message))->toBeTrue();
    expect($recipient->can('view', $message))->toBeTrue();
});

test('message view is allowed for admin and moderator', function () {
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $admin = User::factory()->create(['type' => 'a']);
    $moderator = User::factory()->create(['type' => 'm']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($admin->can('view', $message))->toBeTrue();
    expect($moderator->can('view', $message))->toBeTrue();
});

test('message view is denied for an unrelated regular user', function () {
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $stranger = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($stranger->can('view', $message))->toBeFalse();
});

test('message view is denied for a curator who is not a participant', function () {
    // note: curator (type 'c') is neither participant nor admin/moderator → denied.
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $curator = User::factory()->create(['type' => 'c']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($curator->can('view', $message))->toBeFalse();
});

test('message update is allowed for the senders own message', function () {
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($sender->can('update', $message))->toBeTrue();
});

test('message update is allowed for admin and moderator', function () {
    $sender = User::factory()->create(['type' => 'u']);
    $admin = User::factory()->create(['type' => 'a']);
    $moderator = User::factory()->create(['type' => 'm']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => User::factory()->create(['type' => 'u'])->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($admin->can('update', $message))->toBeTrue();
    expect($moderator->can('update', $message))->toBeTrue();
});

test('message update is denied for a non-sender regular user', function () {
    // note: the recipient (the other conversation participant) can VIEW the
    // message but cannot UPDATE it because they did not send it.
    $sender = User::factory()->create(['type' => 'u']);
    $recipient = User::factory()->create(['type' => 'u']);
    $conversation = Conversation::factory()->create([
        'user_one' => $sender->id,
        'user_two' => $recipient->id,
    ]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);

    expect($recipient->can('update', $message))->toBeFalse();
});
