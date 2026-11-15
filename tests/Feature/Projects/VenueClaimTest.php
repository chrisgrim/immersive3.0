<?php

use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueClaim;

function claimTeamMember(Organizer $organizer): User
{
    $user = User::factory()->create(['type' => 'u', 'current_team_id' => $organizer->id]);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    return $user;
}

beforeEach(function () {
    $this->adder = Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $this->venue = Venue::factory()->create(['name' => 'Albert Theatre', 'organizer_id' => $this->adder->id]);
    // A person, with no organization of their own.
    $this->claimant = User::factory()->create(['type' => 'u']);
    $this->staff = User::factory()->create(['type' => 'a']);
});

test('the venue page offers a claim to everyone but its keepers', function () {
    $this->get($this->venue->publicPath())->assertSee('vue-venue-claim', false);
    $this->actingAs($this->claimant)->get($this->venue->publicPath())->assertSee('vue-venue-claim', false);
    $this->actingAs(claimTeamMember($this->adder))->get($this->venue->publicPath())->assertDontSee('vue-venue-claim', false);
});

test('a person claims without an organization, and approving makes them the owner for good', function () {
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim", ['message' => 'I run it.'])->assertCreated();

    $claim = VenueClaim::sole();
    expect($claim->status)->toBe(VenueClaim::PENDING)
        ->and($this->claimant->can('update', $this->venue->fresh()))->toBeFalse();

    $this->actingAs($this->staff)->getJson('/api/admin/approve/venue-claims')
        ->assertJsonPath('claims.0.venue.name', 'Albert Theatre')
        ->assertJsonPath('claims.0.venue.kept_by', 'Pony Cam');
    $this->actingAs($this->staff)->getJson('/api/admin/approval-counts')->assertJsonPath('requests', 1);

    $this->actingAs($this->staff)->postJson("/api/admin/approve/venue-claims/{$claim->id}/approve")->assertOk();

    $venue = $this->venue->fresh();
    expect($venue->user_id)->toBe($this->claimant->id)
        ->and($venue->organizer_id)->toBeNull()
        ->and($venue->claimed_at)->not->toBeNull()
        ->and($this->claimant->fresh()->can('update', $venue))->toBeTrue()
        ->and(claimTeamMember($this->adder)->can('update', $venue))->toBeFalse()
        ->and($this->staff->can('update', $venue))->toBeTrue();

    $this->get($venue->publicPath())->assertDontSee('vue-venue-claim', false);
    $this->actingAs(User::factory()->create())->postJson("/api/venues/{$venue->slug}/claim")->assertUnprocessable();
});

test('approving one claim turns down the others; a rejected claim changes nothing', function () {
    $rival = User::factory()->create(['type' => 'u']);
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    $this->actingAs($rival)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();

    [$first, $second] = VenueClaim::orderBy('id')->get();
    $this->actingAs($this->staff)->postJson("/api/admin/approve/venue-claims/{$second->id}/reject", ['reason' => 'Could not verify.'])->assertOk();
    expect($this->venue->fresh())->organizer_id->toBe($this->adder->id)->user_id->toBeNull();

    $this->actingAs($this->staff)->postJson("/api/admin/approve/venue-claims/{$first->id}/approve")->assertOk();
    $this->actingAs($this->staff)->postJson("/api/admin/approve/venue-claims/{$second->id}/approve")->assertUnprocessable();
});

test('one pending claim per person, and only staff decide', function () {
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertUnprocessable();

    $claim = VenueClaim::sole();
    $this->actingAs($this->claimant)->postJson("/api/admin/approve/venue-claims/{$claim->id}/approve")->assertForbidden();
});

test('a guest cannot claim', function () {
    $this->postJson("/api/venues/{$this->venue->slug}/claim")->assertUnauthorized();
});

test('a pending or merged venue cannot be claimed through the endpoint', function () {
    $pending = Venue::factory()->create(['status' => Venue::PENDING]);
    $merged = Venue::factory()->create(['merged_into_id' => $this->venue->id]);

    $this->actingAs($this->claimant)->postJson("/api/venues/{$pending->slug}/claim")->assertUnprocessable();
    $this->actingAs($this->claimant)->postJson("/api/venues/{$merged->slug}/claim")->assertUnprocessable();

    expect(VenueClaim::count())->toBe(0);
});

test('approving one claim turns down every other waiting one, and tells them', function () {
    $rival = User::factory()->create(['type' => 'u']);
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    $this->actingAs($rival)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    [$first, $second] = VenueClaim::orderBy('id')->get();

    $this->actingAs($this->staff)->postJson("/api/admin/approve/venue-claims/{$first->id}/approve")->assertOk();

    expect($second->fresh()->status)->toBe(VenueClaim::REJECTED)
        ->and($second->fresh()->admin_notes)->toBe('The venue went to another claim.')
        ->and(App\Models\Messaging\Message::where('message', 'like', '%was not approved: The venue went to another claim.%')->exists())->toBeTrue();
});

test('a venue whose owner deleted their account can be claimed again', function () {
    $owner = User::factory()->create(['type' => 'u']);
    $this->venue->forceFill(['user_id' => $owner->id, 'organizer_id' => null, 'claimed_at' => now()])->save();
    expect($this->venue->fresh()->isClaimable())->toBeFalse();

    // The account really goes: the foreign key clears user_id, and the venue is claimable again.
    $owner->forceDelete();
    expect($this->venue->fresh()->user_id)->toBeNull()
        ->and($this->venue->fresh()->isClaimable())->toBeTrue();
});

test('merging a venue turns down the claims waiting on it', function () {
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    $winner = Venue::factory()->create(['name' => 'Albert Hall']);

    app(App\Actions\Projects\MergeVenues::class)->handle($this->venue, $winner, $this->staff);

    expect(VenueClaim::sole()->status)->toBe(VenueClaim::REJECTED)
        ->and(VenueClaim::sole()->admin_notes)->toContain('merged into Albert Hall');
});

test('staff open a waiting venue for review and can ask its adder a question', function () {
    $owner = User::factory()->create();
    $this->adder->update(['user_id' => $owner->id]);
    $waiting = Venue::factory()->create(['name' => 'Back Room', 'status' => Venue::PENDING, 'organizer_id' => $this->adder->id]);

    $this->actingAs($this->staff)->getJson("/api/admin/venues/{$waiting->slug}")
        ->assertOk()->assertJsonPath('name', 'Back Room')->assertJsonPath('added_by.organizer.name', 'Pony Cam');

    $this->actingAs($this->staff)->postJson("/api/admin/venues/{$waiting->slug}/question", ['question' => 'Is the address right?'])->assertOk();
    expect($waiting->fresh()->status)->toBe(Venue::PENDING);
    expect(App\Models\Messaging\Message::where('message', 'like', '%Is the address right?%')->exists())->toBeTrue();

    $this->actingAs($this->claimant)->getJson("/api/admin/venues/{$waiting->slug}")->assertForbidden();
});
