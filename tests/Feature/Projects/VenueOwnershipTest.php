<?php

use App\Models\Organizer;
use App\Models\User;
use App\Models\Venue;

/** A user on an organizer team, working as that team. */
function venueTeamMember(Organizer $organizer): User
{
    $user = User::factory()->create(['type' => 'u', 'current_team_id' => $organizer->id]);
    $organizer->users()->attach($user->id, ['role' => 'member']);

    return $user;
}

beforeEach(function () {
    $this->keeper = Organizer::factory()->create(['status' => 'p', 'name' => 'Pony Cam']);
    $this->venue = Venue::factory()->create(['name' => 'Albert Theatre', 'organizer_id' => $this->keeper->id]);
});

test('the team that adds a venue keeps it', function () {
    $team = Organizer::factory()->create(['status' => 'p']);

    $this->actingAs(venueTeamMember($team))
        ->postJson('/api/venues', ['name' => 'New Hall', 'street' => '1 Main St', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6])
        ->assertCreated();

    expect(Venue::where('name', 'New Hall')->value('organizer_id'))->toBe($team->id);
});

test('the keeping team edits the venue and sees the edit button', function () {
    $member = venueTeamMember($this->keeper);

    $this->actingAs($member)->get($this->venue->publicPath())->assertSee('Edit this venue');
    $this->actingAs($member)->get("/venues/{$this->venue->slug}/edit")->assertOk();
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['description' => 'Park on Dearborn.', 'directions' => 'Side door.'])
        ->assertOk()->assertJsonPath('description', 'Park on Dearborn.');

    expect($this->venue->fresh()->directions)->toBe('Side door.');
});

test('another team cannot edit it or see the button', function () {
    $stranger = venueTeamMember(Organizer::factory()->create(['status' => 'p']));

    $this->actingAs($stranger)->get($this->venue->publicPath())->assertDontSee('Edit this venue');
    $this->actingAs($stranger)->get("/venues/{$this->venue->slug}/edit")->assertForbidden();
    $this->actingAs($stranger)->postJson("/venues/{$this->venue->slug}", ['name' => 'Mine Now'])->assertForbidden();

    expect($this->venue->fresh()->name)->toBe('Albert Theatre');
});

test('a guest sees no edit button', function () {
    $this->get($this->venue->publicPath())->assertOk()->assertDontSee('Edit this venue');
});

test('admins and moderators always edit, even a venue nobody keeps', function () {
    $loose = Venue::factory()->create(['name' => 'Loose Hall', 'organizer_id' => null]);

    foreach (['m', 'a'] as $type) {
        $staff = User::factory()->create(['type' => $type]);
        $this->actingAs($staff)->get($loose->publicPath())->assertSee('Edit this venue');
        $this->actingAs($staff)->postJson("/venues/{$loose->slug}", ['room' => 'Upstairs '.$type])->assertOk();
    }

    expect($loose->fresh()->room)->toBe('Upstairs a');
});

test('a merged venue is no longer edited by its old team', function () {
    $winner = Venue::factory()->create();
    $this->venue->update(['merged_into_id' => $winner->id]);

    expect(venueTeamMember($this->keeper)->can('update', $this->venue->fresh()))->toBeFalse();
});

test('the request cannot hand the venue to another team', function () {
    $other = Organizer::factory()->create(['status' => 'p']);

    $this->actingAs(venueTeamMember($this->keeper))
        ->postJson("/venues/{$this->venue->slug}", ['organizer_id' => $other->id, 'status' => 'pending'])->assertOk();

    expect($this->venue->fresh())->organizer_id->toBe($this->keeper->id)->status->toBe(Venue::APPROVED);
});

test('the keeping team saves the address and contact links, which show on the page', function () {
    $member = venueTeamMember($this->keeper);

    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", [
        'home' => 'Albert Theatre', 'street' => '170 N Dearborn St', 'city' => 'Chicago', 'region' => 'IL',
        'postal_code' => '60601', 'country' => 'US', 'latitude' => 41.8851, 'longitude' => -87.6297,
        'website' => 'https://albert.example', 'email' => 'box@albert.example', 'phone' => '+1 312 555 0100',
        'instagramHandle' => '@albert', 'tiktokHandle' => '@alberttok',
    ])->assertOk()->assertJsonPath('instagramHandle', 'albert')->assertJsonPath('city', 'Chicago');

    $this->get($this->venue->fresh()->publicPath())
        ->assertSee('170 N Dearborn St')
        ->assertSee('albert.example')
        ->assertSee('mailto:box@albert.example', false)
        ->assertSee('tel:+13125550100', false)
        ->assertSee('https://instagram.com/albert', false)
        ->assertSee('https://www.tiktok.com/@alberttok', false);
});

test('a website must be https and an address needs its map point', function () {
    $member = venueTeamMember($this->keeper);

    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['website' => 'http://albert.example'])
        ->assertUnprocessable()->assertJsonValidationErrors('website');
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['city' => 'Chicago', 'latitude' => 200, 'longitude' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    // A new city with no map point at all is refused too.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['city' => 'Chicago'])
        ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    // Words alone (no address) still save without one.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['description' => 'Fine.'])->assertOk();
});

test('the keeping team adds, reorders and removes up to four photos, as on an event', function () {
    Illuminate\Support\Facades\Storage::fake('digitalocean');
    $member = venueTeamMember($this->keeper);
    $file = fn () => Illuminate\Http\UploadedFile::fake()->image('room.jpg', 1200, 800);

    $this->actingAs($member)->post("/venues/{$this->venue->slug}/photos", [
        'images' => [$file(), $file(), $file()], 'ranks' => [0, 1, 2],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(3, 'photos');

    $photos = $this->venue->images()->orderBy('rank')->get();
    expect($this->venue->fresh()->largeImagePath)->toBe($photos[0]->large_image_path);

    // The third becomes the main one, the first is removed.
    $this->actingAs($member)->post("/venues/{$this->venue->slug}/photos", [
        'currentImages' => json_encode([['id' => $photos[2]->id, 'rank' => 0], ['id' => $photos[1]->id, 'rank' => 1]]),
        'deletedImages' => json_encode([$photos[0]->large_image_path]),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'photos')
        ->assertJsonPath('photos.0.id', $photos[2]->id);

    expect($this->venue->fresh()->largeImagePath)->toBe($photos[2]->large_image_path);

    // No more than four in all.
    $this->actingAs($member)->post("/venues/{$this->venue->slug}/photos", [
        'images' => [$file(), $file(), $file()], 'ranks' => [2, 3, 3],
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});

test('another team cannot touch the photos', function () {
    $stranger = venueTeamMember(Organizer::factory()->create(['status' => 'p']));

    $this->actingAs($stranger)->post("/venues/{$this->venue->slug}/photos", ['deletedImages' => '[]'], ['Accept' => 'application/json'])->assertForbidden();
});

test('your venues lists the ones you keep, and the menu points at them', function () {
    $member = venueTeamMember($this->keeper);
    $owned = Venue::factory()->create(['name' => 'Bellwether Hall', 'user_id' => $member->id]);
    Venue::factory()->create(['name' => 'Claimed Elsewhere', 'organizer_id' => $this->keeper->id, 'user_id' => User::factory()->create()->id]);
    Venue::factory()->create(['name' => 'Some Other Team', 'organizer_id' => Organizer::factory()->create()->id]);

    $this->actingAs($member)->get('/hosting/venues')->assertOk()
        ->assertSee('Your venues')
        ->assertSee('Albert Theatre')
        ->assertSee('Bellwether Hall')
        ->assertDontSee('Claimed Elsewhere')
        ->assertDontSee('Some Other Team');

    expect($member->venuesForMenu())->toBe(2);

    $loner = User::factory()->create(['type' => 'u']);
    $owned->forceFill(["user_id" => $loner->id])->save();
    expect($loner->venuesForMenu())->toBe(1)
        ->and(User::factory()->create(['type' => 'u'])->venuesForMenu())->toBe(0);
});
