<?php

use App\Mail\Message as MessageMail;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    $this->conversation = Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
    ]);
});

// ----- Auth/policy gates -----

test('show requires authentication', function () {
    $this->getJson("/inbox/fetch/conversation/{$this->conversation->id}")->assertStatus(401);
});

test('show denies non-participants', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->getJson("/inbox/fetch/conversation/{$this->conversation->id}")
        ->assertStatus(403);
});

test('show allows a participant', function () {
    $this->actingAs($this->user)
        ->getJson("/inbox/fetch/conversation/{$this->conversation->id}")
        ->assertOk();
});

test('show allows a moderator (not a participant)', function () {
    $mod = User::factory()->create(['type' => 'm']);
    $this->actingAs($mod)
        ->getJson("/inbox/fetch/conversation/{$this->conversation->id}")
        ->assertOk();
});

test('update denies non-participants', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'hi'])
        ->assertStatus(403);
});

// ----- update happy paths + regression for the null-guard fix -----

test('update creates the first message in an empty conversation (regression for null-guard fix)', function () {
    // Pre-fix this would 500 on $latestMessage->created_at.
    expect($this->conversation->messages()->count())->toBe(0);

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'first hello'])
        ->assertOk();

    expect($this->conversation->fresh()->messages()->count())->toBe(1);
    $msg = $this->conversation->messages()->first();
    expect($msg->user_id)->toBe($this->user->id);
    expect($msg->message)->toBe('<p>first hello</p>');
});

test('update appends to the latest message when same sender and within 1 minute', function () {
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user->id,
        'message' => '<p>first line</p>',
        'created_at' => now()->subSeconds(20),
        'updated_at' => now()->subSeconds(20),
    ]);

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'second line'])
        ->assertOk();

    expect($this->conversation->messages()->count())->toBe(1);
    expect($this->conversation->messages()->first()->message)
        ->toBe('<p>first line<br>second line</p>');
});

test('update creates a new message when the previous one is older than 1 minute', function () {
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->user->id,
        'message' => '<p>old</p>',
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'new reply'])
        ->assertOk();

    expect($this->conversation->messages()->count())->toBe(2);
});

test('update creates a new message when the previous one is from the other party', function () {
    Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'user_id' => $this->other->id,
        'message' => '<p>their message</p>',
        'created_at' => now()->subSeconds(5),
        'updated_at' => now()->subSeconds(5),
    ]);

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'my reply'])
        ->assertOk();

    expect($this->conversation->messages()->count())->toBe(2);
});

test('update sanitizes message input via htmlspecialchars', function () {
    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", [
            'message' => '<script>alert(1)</script>',
        ])
        ->assertOk();

    $msg = $this->conversation->messages()->first();
    expect($msg->message)->toContain('&lt;script&gt;');
    expect($msg->message)->not->toContain('<script>');
});

test('update marks the receiver as having unread messages', function () {
    expect($this->other->fresh()->unread)->toBeNull();

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'check inbox'])
        ->assertOk();

    expect($this->other->fresh()->unread)->toBe('m');
});

test('update bumps the conversation updated_at timestamp', function () {
    $original = now()->subHour();
    $this->conversation->update(['updated_at' => $original]);

    $this->actingAs($this->user)
        ->postJson("/inbox/conversation/{$this->conversation->id}", ['message' => 'bump']);

    expect($this->conversation->fresh()->updated_at->greaterThan($original))->toBeTrue();
});

// ----- search() -----

test('search returns only the auth user\'s conversations', function () {
    $third = User::factory()->create();
    Conversation::factory()->create(['user_one' => $third->id, 'user_two' => $this->other->id]);

    $response = $this->actingAs($this->user)
        ->postJson('/inbox/fetch/conversations')
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.id'))->toBe($this->conversation->id);
});

test('search filters by subject when provided', function () {
    $this->conversation->update(['subject' => 'Spooky Dinner Theater']);
    Conversation::factory()->create([
        'user_one' => $this->user->id,
        'user_two' => $this->other->id,
        'subject' => 'Bowling Night',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/inbox/fetch/conversations', ['search' => 'spooky'])
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0.subject'))->toBe('Spooky Dinner Theater');
});
