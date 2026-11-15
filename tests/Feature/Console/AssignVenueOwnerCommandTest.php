<?php

use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;

test('every unowned, unkept venue goes to the admin; team-kept, owned and merged ones stay', function () {
    $admin = User::factory()->create(['type' => 'a']);
    $loose = Venue::factory()->create(['name' => 'Loose Hall']);
    $kept = Venue::factory()->create(['name' => 'Team Hall', 'organizer_id' => Organizer::factory()->create()->id]);
    $owner = User::factory()->create();
    $owned = Venue::factory()->create(['name' => 'Owned Hall', 'user_id' => $owner->id]);
    $merged = Venue::factory()->create(['name' => 'Gone Hall', 'merged_into_id' => $loose->id]);

    // A dry run changes nothing.
    $this->artisan('ei:venues-assign-owner', ['user' => $admin->id])->expectsOutputToContain('1 venues would go to')->assertSuccessful();
    expect($loose->fresh()->user_id)->toBeNull();

    $this->artisan('ei:venues-assign-owner', ['user' => $admin->id, '--apply' => true])->assertSuccessful();

    expect($loose->fresh()->user_id)->toBe($admin->id)
        ->and($loose->fresh()->isClaimable())->toBeTrue()
        ->and($kept->fresh()->user_id)->toBeNull()
        ->and($owned->fresh()->user_id)->toBe($owner->id)
        ->and($merged->fresh()->user_id)->toBeNull();
});

test('it only gives venues to an admin that exists', function () {
    Venue::factory()->create();

    $this->artisan('ei:venues-assign-owner', ['user' => 999999, '--apply' => true])->assertFailed();
    $this->artisan('ei:venues-assign-owner', ['user' => User::factory()->create(['type' => 'u'])->id, '--apply' => true])->assertFailed();

    expect(Venue::whereNotNull('user_id')->count())->toBe(0);
});
