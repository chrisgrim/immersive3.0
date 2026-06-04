<?php

use App\Mail\OwnershipClaimNotification;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\OwnershipClaim;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

/** A staff-owned, externally-unowned organizer — the claimable case. */
function claimableOrg(string $staffType = 'm'): Organizer
{
    $staff = User::factory()->create(['type' => $staffType]);

    return Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'p']);
}

// ---------------------------------------------------------------------------
// isClaimable() model logic
// ---------------------------------------------------------------------------

test('isClaimable is true for a staff-entered organizer with no external member', function () {
    expect(claimableOrg()->isClaimable())->toBeTrue();
});

test('isClaimable is false when an external user owns the organizer', function () {
    $external = User::factory()->create(['type' => 'u']);
    $org = Organizer::factory()->create(['user_id' => $external->id]);

    expect($org->isClaimable())->toBeFalse();
});

test('isClaimable is false when an external user is attached as a member', function () {
    $org = claimableOrg();
    $external = User::factory()->create(['type' => 'u']);
    $org->users()->attach($external->id, ['role' => 'member']);

    expect($org->fresh()->isClaimable())->toBeFalse();
});

test('isClaimable is false for a deactivated organizer', function () {
    $staff = User::factory()->create(['type' => 'a']);
    $org = Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'd']);

    expect($org->isClaimable())->toBeFalse();
});

// ---------------------------------------------------------------------------
// check-name surface (auth-gated claimable flag)
// ---------------------------------------------------------------------------

test('check-name exposes claimable=true to an authenticated user for a claimable org', function () {
    $org = claimableOrg();
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson('/api/organizers/check-name', ['name' => $org->name])
        ->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('existing_organizations.0.claimable', true);
});

test('check-name never exposes claimable to an anonymous user', function () {
    $org = claimableOrg();

    $this->postJson('/api/organizers/check-name', ['name' => $org->name])
        ->assertOk()
        ->assertJsonPath('existing_organizations.0.claimable', false);
});

// ---------------------------------------------------------------------------
// Submitting a claim
// ---------------------------------------------------------------------------

test('a logged-in user can claim a claimable organizer and admins are notified', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson("/api/organizers/{$org->slug}/claim", ['message' => 'This is my company'])
        ->assertStatus(201);

    $this->assertDatabaseHas('ownership_claims', [
        'organizer_id' => $org->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'message' => 'This is my company',
    ]);

    Mail::assertSent(OwnershipClaimNotification::class, fn ($mail) => $mail->hasTo($admin->email));
});

test('claiming an externally-owned organizer is rejected', function () {
    $external = User::factory()->create(['type' => 'u']);
    $org = Organizer::factory()->create(['user_id' => $external->id]);
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->postJson("/api/organizers/{$org->slug}/claim")
        ->assertStatus(422);

    $this->assertDatabaseCount('ownership_claims', 0);
});

test('a member cannot claim their own organizer', function () {
    $org = claimableOrg();
    $member = User::factory()->create(['type' => 'u']);
    $org->users()->attach($member->id, ['role' => 'member']);

    $this->actingAs($member)
        ->postJson("/api/organizers/{$org->slug}/claim")
        ->assertStatus(422);
});

test('a user cannot file a second pending claim for the same organizer', function () {
    $org = claimableOrg();
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)->postJson("/api/organizers/{$org->slug}/claim")->assertStatus(201);
    $this->actingAs($user)->postJson("/api/organizers/{$org->slug}/claim")->assertStatus(422);

    $this->assertDatabaseCount('ownership_claims', 1);
});

test('an unauthenticated user cannot submit a claim', function () {
    $org = claimableOrg();

    $this->postJson("/api/organizers/{$org->slug}/claim")->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Admin approve / reject
// ---------------------------------------------------------------------------

test('approving a claim transfers full ownership and reassigns events', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $staff = User::factory()->create(['type' => 'm']);
    $org = Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'p']);
    $event = Event::factory()->create(['organizer_id' => $org->id, 'user_id' => $staff->id, 'status' => 'd']);

    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$claim->id}/approve")
        ->assertOk();

    // Ownership transferred.
    expect($org->fresh()->user_id)->toBe($claimant->id);
    // Claimant is the sole owner in the pivot; the old staff member is gone.
    expect($org->users()->where('users.id', $claimant->id)->wherePivot('role', 'owner')->exists())->toBeTrue();
    expect($org->users()->where('users.id', $staff->id)->exists())->toBeFalse();
    // Events follow.
    expect($event->fresh()->user_id)->toBe($claimant->id);
    // current_team_id set.
    expect($claimant->fresh()->current_team_id)->toBe($org->id);
    // Claim recorded.
    $claim->refresh();
    expect($claim->status)->toBe('approved');
    expect($claim->processed_by)->toBe($admin->id);
    expect($claim->processed_at)->not->toBeNull();

    Mail::assertSent(OwnershipClaimNotification::class, fn ($mail) => $mail->hasTo($claimant->email));
});

test('approving one claim auto-rejects sibling pending claims for the same organizer', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();

    $winner = User::factory()->create(['type' => 'u']);
    $loser = User::factory()->create(['type' => 'u']);
    $winningClaim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $winner->id]);
    $losingClaim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $loser->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$winningClaim->id}/approve")
        ->assertOk();

    expect($losingClaim->fresh()->status)->toBe('rejected');
});

test('approving an already-processed claim returns 422', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();
    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);
    // status isn't mass-assignable (by design), so set it directly to simulate a processed claim.
    $claim->status = 'approved';
    $claim->save();

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$claim->id}/approve")
        ->assertStatus(422);
});

test('approving a claim for an org that gained a real owner returns 422', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $external = User::factory()->create(['type' => 'u']);
    // Org now owned by a real external user — no longer claimable.
    $org = Organizer::factory()->create(['user_id' => $external->id]);
    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$claim->id}/approve")
        ->assertStatus(422);

    expect($org->fresh()->user_id)->toBe($external->id);
});

test('rejecting an already-processed claim returns 422', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();
    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);
    $claim->status = 'approved';
    $claim->save();

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$claim->id}/reject", ['reason' => 'too late'])
        ->assertStatus(422);

    expect($claim->fresh()->status)->toBe('approved');
});

test('rejecting a claim records the reason and notifies the claimant', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();
    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    $this->actingAs($admin)
        ->postJson("/api/admin/approve/claims/{$claim->id}/reject", ['reason' => 'Could not verify'])
        ->assertOk();

    $claim->refresh();
    expect($claim->status)->toBe('rejected');
    expect($claim->admin_notes)->toBe('Could not verify');
    expect($claim->processed_by)->toBe($admin->id);

    Mail::assertSent(OwnershipClaimNotification::class, fn ($mail) => $mail->hasTo($claimant->email));
});

// ---------------------------------------------------------------------------
// Authorization + counts
// ---------------------------------------------------------------------------

test('a non-moderator cannot access the admin claims endpoints', function () {
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)->getJson('/api/admin/approve/claims')->assertStatus(403);
});

test('approval counts fold pending ownership claims into the Requests total', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $org = claimableOrg();
    $claimant = User::factory()->create(['type' => 'u']);
    OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    // "Requests" is the catch-all (name changes + ownership claims); there is no separate claims count.
    $this->actingAs($admin)
        ->getJson('/api/admin/approval-counts')
        ->assertOk()
        ->assertJsonPath('requests', 1)
        ->assertJsonMissingPath('claims');
});

test('the org page shows a pending banner to the user who filed the claim', function () {
    $org = claimableOrg(); // published, staff-owned
    $claimant = User::factory()->create(['type' => 'u']);
    OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    $this->actingAs($claimant)
        ->get("/organizers/{$org->slug}")
        ->assertOk()
        ->assertSee('claim ownership of this organization is pending review');
});

test('the org page does not show the pending banner to users without a claim', function () {
    $org = claimableOrg();
    $other = User::factory()->create(['type' => 'u']);

    $this->actingAs($other)
        ->get("/organizers/{$org->slug}")
        ->assertOk()
        ->assertDontSee('claim ownership of this organization is pending review');
});

test('the ownership-claim email renders for every variant', function () {
    // Mail::fake() asserts a mailable was sent but never renders the view, so render each
    // variant explicitly — this is what catches Blade-level errors like the reserved-$message clash.
    $org = claimableOrg();
    $claimant = User::factory()->create(['type' => 'u']);
    $claim = OwnershipClaim::create([
        'organizer_id' => $org->id,
        'user_id' => $claimant->id,
        'message' => 'This really is my company',
    ]);

    // Admin variant — references the claimant's message (the spot that broke).
    expect((new OwnershipClaimNotification($claim, true))->render())
        ->toContain('This really is my company');

    // Approved variant — assert on stable copy (org name could contain HTML-escaped chars).
    $claim->status = 'approved';
    $claim->save();
    expect((new OwnershipClaimNotification($claim->fresh(), false))->render())
        ->toContain('full ownership');

    // Rejected variant — shows the admin's reason.
    $claim->status = 'rejected';
    $claim->admin_notes = 'Could not verify ownership';
    $claim->save();
    expect((new OwnershipClaimNotification($claim->fresh(), false))->render())
        ->toContain('Could not verify ownership');
});

test('the admin claims index lists pending claims with requester and entered-by details', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $staff = User::factory()->create(['type' => 'm', 'name' => 'EI Staffer']);
    $org = Organizer::factory()->create(['user_id' => $staff->id, 'status' => 'p']);
    $claimant = User::factory()->create(['type' => 'u', 'name' => 'Real Owner']);
    OwnershipClaim::create(['organizer_id' => $org->id, 'user_id' => $claimant->id]);

    $this->actingAs($admin)
        ->getJson('/api/admin/approve/claims')
        ->assertOk()
        ->assertJsonPath('claims.0.user.name', 'Real Owner')
        ->assertJsonPath('claims.0.entered_by.name', 'EI Staffer')
        ->assertJsonPath('claims.0.organizer.name', $org->name);
});
