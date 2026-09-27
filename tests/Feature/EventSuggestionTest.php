<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Http\Controllers\EventSuggestionController;
use App\Models\EventSuggestion;
use App\Models\User;
use App\Support\Spam\ProofOfWork;

/**
 * A solved proof-of-work payload, as the browser would send it. Solving costs a
 * second or so, so it's done once per run; each test gets a fresh cache, so the
 * one-use replay guard never sees it twice within a test unless the test says so.
 */
function solvedPow(): string
{
    static $payload = null;

    if ($payload === null) {
        $challenge = Challenge::fromArray(app(ProofOfWork::class)->challenge());
        $solution = (new Altcha)->solveChallenge(new SolveChallengeOptions(
            challenge: $challenge,
            algorithm: new Pbkdf2,
        ));
        $payload = (new Payload($challenge, $solution))->toBase64();
    }

    return $payload;
}

function suggestion(array $overrides = []): array
{
    return array_merge([
        'message' => "The Haunted Library, Portland OR\nhttps://example.com/haunted-library",
        'altcha' => solvedPow(),
    ], $overrides);
}

beforeEach(function () {
    // The challenge is stamped with its issue time; a form sent back within
    // ProofOfWork::MIN_SECONDS is refused as a script. Step past that.
    solvedPow();
    $this->travel(ProofOfWork::MIN_SECONDS + 5)->seconds();
});

// ---------------------------------------------------------------------------
// Public form
// ---------------------------------------------------------------------------

test('the challenge endpoint returns a signed challenge', function () {
    $this->getJson('/api/event-suggestions/challenge')
        ->assertOk()
        ->assertJsonStructure(['parameters' => ['algorithm', 'nonce', 'salt', 'cost', 'keyPrefix', 'data'], 'signature']);
});

test('a guest can suggest an event', function () {
    $this->postJson('/api/event-suggestions', suggestion())->assertCreated();

    $row = EventSuggestion::sole();
    expect($row->message)->toBe("The Haunted Library, Portland OR\nhttps://example.com/haunted-library")
        ->and($row->user_id)->toBeNull()
        ->and($row->status)->toBe('pending');
});

test('a logged-in suggestion is tied to the user, whose email the admin list shows', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $this->actingAs($user)
        ->postJson('/api/event-suggestions', suggestion())
        ->assertCreated();

    expect(EventSuggestion::sole()->user_id)->toBe($user->id);

    $this->actingAs(User::factory()->create(['type' => 'm']))
        ->getJson('/api/admin/approve/suggestions')
        ->assertJsonPath('suggestions.0.user.email', 'member@example.com');
});

test('deleting the account leaves no personal data on the suggestion', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/event-suggestions', suggestion())->assertCreated();

    $user->delete();

    expect(EventSuggestion::sole()->user_id)->toBeNull();
});

test('a message is required and capped in length', function () {
    $this->postJson('/api/event-suggestions', suggestion(['message' => '   ']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    $this->postJson('/api/event-suggestions', suggestion(['message' => str_repeat('a', EventSuggestion::MAX_LENGTH + 1)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    expect(EventSuggestion::count())->toBe(0);
});

test('a filled honeypot looks like success but saves nothing', function () {
    $this->postJson('/api/event-suggestions', suggestion([EventSuggestionController::HONEYPOT => 'http://spam.example']))
        ->assertCreated();

    expect(EventSuggestion::count())->toBe(0);
});

test('a missing or forged proof of work is refused', function () {
    $this->postJson('/api/event-suggestions', suggestion(['altcha' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['altcha']);

    $this->postJson('/api/event-suggestions', suggestion(['altcha' => base64_encode('{"nope":1}')]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['altcha']);

    // Right shape, wrong answer.
    $data = json_decode(base64_decode(solvedPow()), true);
    $data['solution']['derivedKey'] = str_repeat('0', strlen($data['solution']['derivedKey']));
    $this->postJson('/api/event-suggestions', suggestion(['altcha' => base64_encode(json_encode($data))]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['altcha']);

    expect(EventSuggestion::count())->toBe(0);
});

test('a solved challenge can only be used once', function () {
    $this->postJson('/api/event-suggestions', suggestion())->assertCreated();
    $this->postJson('/api/event-suggestions', suggestion())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['altcha']);

    expect(EventSuggestion::count())->toBe(1);
});

test('a form sent back too soon after the challenge is refused', function () {
    $this->travelBack();
    // Solving in PHP is slower than a browser; freeze the clock so it can't eat the window.
    $this->freezeTime();
    $pow = app(ProofOfWork::class);
    $challenge = Challenge::fromArray($pow->challenge());
    $solution = (new Altcha)->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2));
    $payload = (new Payload($challenge, $solution))->toBase64();

    expect($pow->verify($payload))->toBeFalse();
});

test('fetching challenges does not use up the send allowance', function () {
    for ($i = 0; $i < 15; $i++) {
        $this->getJson('/api/event-suggestions/challenge')->assertOk();
    }

    $this->postJson('/api/event-suggestions', suggestion())->assertCreated();
});

test('the hourly send limit is not reset by other requests', function () {
    // With the old shared counter, a challenge fetch opened a one-minute
    // window that the sends then counted into, so a minute later it reset.
    $this->getJson('/api/event-suggestions/challenge')->assertOk();
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/event-suggestions', suggestion(['message' => '']))->assertUnprocessable();
    }

    $this->travel(61)->seconds();
    $this->getJson('/api/event-suggestions/challenge')->assertOk();
    $this->postJson('/api/event-suggestions', suggestion())->assertStatus(429);
});

test('submissions are throttled per hour', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/event-suggestions', suggestion(['message' => '']))->assertUnprocessable();
    }

    $this->postJson('/api/event-suggestions', suggestion())->assertStatus(429);
});

// ---------------------------------------------------------------------------
// Admin queue
// ---------------------------------------------------------------------------

function pendingSuggestion(): EventSuggestion
{
    return EventSuggestion::create([
        'message' => 'Sleep No More https://example.com/snm',
    ]);
}

test('moderators see pending suggestions and they count toward the dashboard badge', function () {
    $pending = pendingSuggestion();
    $handled = pendingSuggestion();
    $handled->status = 'done';
    $handled->save();

    $mod = User::factory()->create(['type' => 'm']);

    $this->actingAs($mod)->getJson('/api/admin/approve/suggestions')
        ->assertOk()
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonPath('suggestions.0.id', $pending->id);

    $this->actingAs($mod)->getJson('/api/admin/approval-counts')
        ->assertOk()
        ->assertJsonPath('suggestions', 1);
});

test('marking a suggestion done takes it off the queue and records who did it', function () {
    $suggestion = pendingSuggestion();
    $mod = User::factory()->create(['type' => 'm']);

    $this->actingAs($mod)->postJson("/api/admin/approve/suggestions/{$suggestion->id}/done")->assertOk();

    $suggestion->refresh();
    expect($suggestion->status)->toBe('done')
        ->and($suggestion->processed_by)->toBe($mod->id)
        ->and($suggestion->processed_at)->not->toBeNull();
});

test('deleting a suggestion removes it', function () {
    $suggestion = pendingSuggestion();
    $mod = User::factory()->create(['type' => 'm']);

    $this->actingAs($mod)->deleteJson("/api/admin/approve/suggestions/{$suggestion->id}")->assertOk();

    expect(EventSuggestion::count())->toBe(0);
});

test('regular users cannot see or act on the queue', function () {
    $suggestion = pendingSuggestion();
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)->getJson('/api/admin/approve/suggestions')->assertForbidden();
    $this->actingAs($user)->postJson("/api/admin/approve/suggestions/{$suggestion->id}/done")->assertForbidden();
    $this->actingAs($user)->deleteJson("/api/admin/approve/suggestions/{$suggestion->id}")->assertForbidden();

    expect($suggestion->fresh()->status)->toBe('pending');
});
