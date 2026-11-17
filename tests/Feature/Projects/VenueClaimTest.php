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

test('a draft or merged venue cannot be claimed through the endpoint; one in review can', function () {
    $draft = Venue::factory()->create(['status' => Venue::DRAFT]);
    $merged = Venue::factory()->create(['merged_into_id' => $this->venue->id]);
    $inReview = Venue::factory()->create(['status' => Venue::PENDING]);

    $this->actingAs($this->claimant)->postJson("/api/venues/{$draft->slug}/claim")->assertUnprocessable();
    $this->actingAs($this->claimant)->postJson("/api/venues/{$merged->slug}/claim")->assertUnprocessable();
    $this->actingAs($this->claimant)->postJson("/api/venues/{$inReview->slug}/claim")->assertCreated();

    expect(VenueClaim::sole()->venue_id)->toBe($inReview->id);
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

test('adding a place EI already has: found by name or address, and its in-review page opens from the link with Claim', function () {
    $this->venue->update(['status' => Venue::PENDING, 'home' => '600', 'street' => 'E D St', 'city' => 'Petaluma']);
    $adder = Organizer::factory()->create(['status' => 'p']);
    $member = claimTeamMember($adder);

    // The draft this person is adding for the same place.
    $draft = $this->actingAs($member)->postJson('/hosting/venues', ['name' => 'Albert'])->assertCreated()->json();

    $byName = $this->actingAs($member)->getJson("/api/venues/known?name=Albert&except={$draft['slug']}")->assertOk()->json();
    expect(collect($byName)->pluck('name')->all())->toBe(['Albert Theatre'])
        // In review and someone else's: its name and city only, no address, map point or page link.
        ->and($byName[0])->not->toHaveKeys(['street', 'home', 'latitude', 'longitude', 'postal_code'])
        ->and($byName[0]['claim_url'])->toBeNull()
        ->and($byName[0]['address'])->not->toContain('E D St');

    // Street and city alone are not its address: no exact place or link without the number.
    $noNumber = $this->actingAs($member)->getJson('/api/venues/known?street=E+D+St&city=Petaluma')->assertOk()->json();
    expect($noNumber[0])->not->toHaveKeys(['street', 'latitude'])
        ->and($noNumber[0]['claim_url'])->toBeNull();

    $byAddress = $this->actingAs($member)->getJson('/api/venues/known?home=600&street=e+d+st&city=Petaluma')->assertOk()->json();
    expect($byAddress[0]['at_address'])->toBeTrue()->and($byAddress[0]['claim_url'])->not->toBeNull();

    // In review: not public, but the link opens it, with a Claim button.
    $this->actingAs($member)->get($this->venue->publicPath())->assertNotFound();
    $this->actingAs($member)->get($byAddress[0]['claim_url'])
        ->assertOk()->assertSee('In review')->assertSee('vue-venue-claim', false);

    // Claiming leaves the draft being added alone (Back still finds it); the nightly cleanup clears an empty one.
    $this->actingAs($member)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    expect(Venue::find($draft['id']))->not->toBeNull()
        ->and(VenueClaim::sole()->venue_id)->toBe($this->venue->id);
});

test('staff see Claim on a venue they do not keep, though they can edit every venue', function () {
    $this->actingAs($this->staff)->get($this->venue->publicPath())->assertSee('vue-venue-claim', false);
});

test('a claimed venue in review shows its owner to staff, and questions go to them', function () {
    $this->venue->update(['status' => Venue::PENDING]);
    $this->actingAs($this->claimant)->postJson("/api/venues/{$this->venue->slug}/claim")->assertCreated();
    $this->actingAs($this->staff)->postJson('/api/admin/approve/venue-claims/'.VenueClaim::sole()->id.'/approve')->assertOk();

    $this->actingAs($this->staff)->getJson("/api/admin/venues/{$this->venue->slug}")
        ->assertJsonPath('claimed_by.name', $this->claimant->name)->assertJsonPath('added_by', null);

    $this->actingAs($this->staff)->postJson("/api/admin/venues/{$this->venue->slug}/question", ['question' => 'Is the address right?'])->assertOk();
    expect(App\Models\Messaging\Conversation::where('user_two', $this->claimant->id)->exists())->toBeTrue();
});

test('staff remove a junk venue waiting for approval; events keep their address and the adder hears why', function () {
    $this->venue->update(['status' => Venue::PENDING]);
    $event = App\Models\Event::factory()->create();
    $location = $event->location()->create(['venue_id' => $this->venue->id, 'venue' => $this->venue->name, 'street' => '1 Main St', 'city' => 'Chicago']);

    $this->actingAs($this->claimant)->postJson("/api/admin/venues/{$this->venue->slug}/remove", ['reason' => 'Junk'])->assertForbidden();
    $this->actingAs($this->staff)->postJson("/api/admin/venues/{$this->venue->slug}/remove", ['reason' => 'Not a real place'])->assertOk();

    expect(Venue::find($this->venue->id))->toBeNull()
        ->and($location->fresh()->venue_id)->toBeNull()
        ->and($location->fresh()->street)->toBe('1 Main St');
});

test('a live venue cannot be removed, only merged', function () {
    $this->actingAs($this->staff)->postJson("/api/admin/venues/{$this->venue->slug}/remove", ['reason' => 'x'])->assertStatus(422);
    expect(Venue::find($this->venue->id))->not->toBeNull();
});

test('the website way (number in the street, home the place name or empty) still finds the venue at that address', function () {
    $this->venue->update(['status' => Venue::PENDING, 'home' => null, 'street' => '340 W 46th St', 'city' => 'New York']);
    $member = claimTeamMember(Organizer::factory()->create(['status' => 'p']));

    $found = $this->actingAs($member)->getJson('/api/venues/known?home=340+W+46th+St&street=340+W+46th+St&city=New+York')->assertOk()->json();
    expect($found[0]['at_address'])->toBeTrue()->and($found[0]['claim_url'])->not->toBeNull();

    $this->venue->update(['status' => Venue::APPROVED, 'home' => 'Phoenix Theater']);
    $approved = $this->actingAs($member)->getJson('/api/venues/known?home=340+W+46th+St&street=340+W+46th+St&city=New+York')->assertOk()->json();
    expect($approved[0]['at_address'])->toBeTrue();
});

test('a numbered street is not a house number: 5th Ave alone opens nothing private', function () {
    $this->venue->update(['status' => Venue::PENDING, 'home' => '350', 'street' => '5th Ave', 'city' => 'New York']);
    $member = claimTeamMember(Organizer::factory()->create(['status' => 'p']));

    $guess = $this->actingAs($member)->getJson('/api/venues/known?street=5th+Ave&city=New+York')->assertOk()->json();
    expect($guess[0])->not->toHaveKeys(['home', 'street', 'latitude', 'postal_code'])
        ->and($guess[0]['claim_url'])->toBeNull();

    $whole = $this->actingAs($member)->getJson('/api/venues/known?home=350&street=5th+Ave&city=New+York')->assertOk()->json();
    expect($whole[0]['claim_url'])->not->toBeNull();
});

test('a short house number inside a numbered street does not count as in it', function () {
    $this->venue->update(['status' => Venue::PENDING, 'home' => '4', 'street' => 'W 46th St', 'city' => 'New York']);
    $member = claimTeamMember(Organizer::factory()->create(['status' => 'p']));

    $guess = $this->actingAs($member)->getJson('/api/venues/known?street=W+46th+St&city=New+York')->assertOk()->json();
    expect($guess[0])->not->toHaveKeys(['home', 'street', 'latitude'])
        ->and($guess[0]['claim_url'])->toBeNull();

    $whole = $this->actingAs($member)->getJson('/api/venues/known?home=4&street=W+46th+St&city=New+York')->assertOk()->json();
    expect($whole[0]['claim_url'])->not->toBeNull();
});
