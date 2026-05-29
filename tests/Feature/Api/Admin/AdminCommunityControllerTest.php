<?php

use App\Mail\Comments;
use App\Models\Curated\Community;
use App\Models\Messaging\Message;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->moderator = User::factory()->create(['type' => 'm']);
});

// ----- getPending() -----

test('getPending returns only communities with status r', function () {
    Community::factory()->count(3)->create(['status' => 'r']);
    Community::factory()->count(2)->create(['status' => 'p']);
    Community::factory()->count(1)->create(['status' => 'n']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/communities')
        ->assertOk();

    expect($response->json('total'))->toBe(3);
    expect($response->json('data'))->toHaveCount(3);
});

test('getPending eager-loads owner, images and curators', function () {
    Community::factory()->create(['status' => 'r']);

    $response = $this->actingAs($this->moderator)
        ->getJson('/api/admin/approve/communities')
        ->assertOk();

    expect($response->json('data.0'))->toHaveKeys(['owner', 'images', 'curators']);
});

test('getPending is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $this->actingAs($user)->getJson('/api/admin/approve/communities')->assertStatus(403);
});

test('getPending requires authentication', function () {
    $this->getJson('/api/admin/approve/communities')->assertStatus(401);
});

// ----- index() -----

test('index returns a paginated list with owner and images', function () {
    Community::factory()->count(2)->create(['status' => 'p']);

    // note: index() is not wired to a route in api.php; only show/approve/reject/getPending are
    // reachable. The remaining controller methods are covered by direct controller assertions.
    $controller = app(\App\Http\Controllers\Admin\AdminCommunityController::class);
    $result = $controller->index(request());

    expect($result->total())->toBe(2);
    expect($result->first()->relationLoaded('owner'))->toBeTrue();
    expect($result->first()->relationLoaded('images'))->toBeTrue();
});

// ----- show() -----

test('show returns the community with relations', function () {
    $community = Community::factory()->create(['status' => 'p']);

    $this->actingAs($this->moderator)
        ->getJson("/api/admin/communities/{$community->slug}")
        ->assertOk()
        ->assertJsonPath('community.id', $community->id)
        ->assertJsonPath('community.slug', $community->slug);
});

test('show is denied to non-moderators', function () {
    $community = Community::factory()->create();
    $user = User::factory()->create(['type' => 'u']);

    $this->actingAs($user)
        ->getJson("/api/admin/communities/{$community->slug}")
        ->assertStatus(403);
});

// ----- approve() -----

test('approve flips a pending community to published and notifies the owner', function () {
    $owner = User::factory()->create();
    $community = Community::factory()->create(['status' => 'r', 'user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/approve")
        ->assertOk()
        ->assertJsonPath('message', 'Community approved successfully');

    expect($community->fresh()->status)->toBe('p');

    // In-app message recorded by the approving moderator.
    expect(Message::where('user_id', $this->moderator->id)->exists())->toBeTrue();

    // The approval email is addressed to the community owner (Mail::to($community->owner)).
    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
});

test('approve does not notify when moderator approves their own community', function () {
    $community = Community::factory()->create(['status' => 'r', 'user_id' => $this->moderator->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/approve")
        ->assertOk();

    expect($community->fresh()->status)->toBe('p');
    Mail::assertNothingSent();
    expect(Message::count())->toBe(0);
});

test('approve is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/communities/{$community->slug}/approve")
        ->assertStatus(403);

    expect($community->fresh()->status)->toBe('r');
});

// ----- reject() -----

test('reject sets status to n and emails the owner with the reason', function () {
    $owner = User::factory()->create();
    $community = Community::factory()->create(['status' => 'r', 'user_id' => $owner->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/reject", [
            'reason' => 'Needs more content',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Community rejected successfully');

    expect($community->fresh()->status)->toBe('n');
    // note: `rejection_reason` is neither a communities column nor in $fillable, so the
    // controller's update(['rejection_reason' => ...]) silently drops it. The reason only
    // lives in the email + in-app message.

    // The rejection email is addressed to the community owner (Mail::to($community->owner)).
    Mail::assertSent(Comments::class, fn ($mail) => $mail->hasTo($owner->email));
    expect(Message::where('user_id', $this->moderator->id)->exists())->toBeTrue();
});

test('reject requires a reason', function () {
    $community = Community::factory()->create(['status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    expect($community->fresh()->status)->toBe('r');
});

test('reject reason has a 1000 character cap', function () {
    $community = Community::factory()->create(['status' => 'r']);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/reject", [
            'reason' => str_repeat('x', 1001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('reject still sets status to n when moderator rejects their own community but sends no notification', function () {
    $community = Community::factory()->create(['status' => 'r', 'user_id' => $this->moderator->id]);

    $this->actingAs($this->moderator)
        ->postJson("/api/admin/approve/communities/{$community->slug}/reject", [
            'reason' => 'changed my mind',
        ])
        ->assertOk();

    // note: unlike approve(), reject() always runs $community->update(['status' => 'n', ...])
    // before the self-check, so even self-rejection flips the status; only the
    // notification/email are skipped.
    expect($community->fresh()->status)->toBe('n');
    Mail::assertNothingSent();
    expect(Message::count())->toBe(0);
});

test('reject is denied to non-moderators', function () {
    $user = User::factory()->create(['type' => 'u']);
    $community = Community::factory()->create(['status' => 'r']);

    $this->actingAs($user)
        ->postJson("/api/admin/approve/communities/{$community->slug}/reject", ['reason' => 'no'])
        ->assertStatus(403);

    expect($community->fresh()->status)->toBe('r');
});
