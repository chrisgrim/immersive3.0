<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\UserDeletionService;

test('blockingReason is null for a plain user', function () {
    $user = User::factory()->create();

    expect((new UserDeletionService)->blockingReason($user))->toBeNull();
});

test('blockingReason names owning an organizer', function () {
    $user = User::factory()->create();
    Organizer::factory()->create(['user_id' => $user->id]);

    expect((new UserDeletionService)->blockingReason($user))->toContain('organizer');
});

/**
 * Proves delete()'s internal re-check is a real safety net, not decorative —
 * calling delete() directly (skipping the caller-side blockingReason() check
 * both controllers normally do first) on a user who owns an Organizer must
 * still refuse to delete them and must leave the Organizer intact. This is
 * what actually protects against the TOCTOU window between a caller's check
 * and the transaction (see the class doc comment).
 */
test('delete refuses and throws even when called without a prior blockingReason check, and nothing is touched', function () {
    $user = User::factory()->create();
    $organizer = Organizer::factory()->create(['user_id' => $user->id]);

    expect(fn () => (new UserDeletionService)->delete($user))
        ->toThrow(RuntimeException::class);

    expect(User::find($user->id))->not->toBeNull();
    expect(Organizer::find($organizer->id))->not->toBeNull();
});

test('delete refuses for a user who owns an event even without a prior check', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['user_id' => $user->id]);

    expect(fn () => (new UserDeletionService)->delete($user))
        ->toThrow(RuntimeException::class);

    expect(Event::find($event->id))->not->toBeNull();
});

test('a failed deletion attempt leaves unrelated cleanup untouched (transactional)', function () {
    // If the internal re-check throws AFTER some cleanup queries would have
    // run, the whole transaction must roll back — nothing partially deleted.
    $user = User::factory()->create();
    Organizer::factory()->create(['user_id' => $user->id]);
    $event = Event::factory()->published()->create();
    $this->actingAs($user);
    $event->favorite();

    expect(fn () => (new UserDeletionService)->delete($user))->toThrow(RuntimeException::class);

    expect($user->fresh()->favorites()->count())->toBe(1);
});
