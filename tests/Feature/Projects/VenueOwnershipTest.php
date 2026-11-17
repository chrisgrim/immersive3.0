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
    // Typed without its start, as people do: https:// goes in front.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['website' => ' google.com '])->assertOk();
    expect($this->venue->fresh()->website)->toBe('https://google.com');
    // A phone of ten plain digits gets its dashes; anything else stays as typed.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['phone' => '8324438072'])->assertOk();
    expect($this->venue->fresh()->phone)->toBe('832-443-8072');
    // Numbers from elsewhere, written their own way.
    foreach (['912345678', '020-7946-0958', '612-345-67'] as $phone) {
        $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['phone' => $phone])->assertOk();
    }
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['phone' => '+44 20 7946 0958'])->assertOk();
    expect($this->venue->fresh()->phone)->toBe('+44 20 7946 0958');
    // Each contact field is checked, not only its length.
    $refused = [
        'phone' => ['832-443-03034', '832-443-807', '12345', 'call me', '1234567890123456'],
        'website' => ['google', 'https://not a site.com'],
        'email' => ['bob@aol', 'bob'],
        'instagramHandle' => ['bad name!', 'a/b'],
        'twitterHandle' => ['dots.are.not.ok'],
        'tiktokHandle' => ['has space'],
        'facebookHandle' => ['no spaces here please', 'https://facebook.com/profile.php?id=123', 'facebook.com/pages/Albert/1'],
    ];
    foreach ($refused as $field => $values) {
        foreach ($values as $value) {
            $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", [$field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    // Pasted profile links and a typed "@" become the name alone.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", [
        'instagramHandle' => 'https://www.instagram.com/albert.theatre/',
        'twitterHandle' => 'x.com/@albert_hall',
        'tiktokHandle' => '@albert.live',
        'facebookHandle' => 'https://facebook.com/AlbertTheatre?ref=1',
        'phone' => '1-832-443-8072',
        'email' => 'hello@albert.example',
    ])->assertOk();
    expect($this->venue->fresh()->only(['instagramHandle', 'twitterHandle', 'tiktokHandle', 'facebookHandle', 'phone']))->toBe([
        'instagramHandle' => 'albert.theatre', 'twitterHandle' => 'albert_hall', 'tiktokHandle' => 'albert.live', 'facebookHandle' => 'AlbertTheatre', 'phone' => '1-832-443-8072',
    ]);
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
    $owned->forceFill(['user_id' => $loner->id])->save();
    expect($loner->venuesForMenu())->toBe(1)
        ->and(User::factory()->create(['type' => 'u'])->venuesForMenu())->toBe(0);
});

test('the phone menu shows your venue(s) only when you keep one', function () {
    $loner = User::factory()->create(['type' => 'u']);
    $this->actingAs($loner)->get('/menu')->assertOk()->assertDontSee('data-menu-venues', false);

    Venue::factory()->create(['name' => 'Bellwether Hall', 'user_id' => $loner->id]);
    $this->actingAs($loner)->get('/menu')->assertOk()
        ->assertSee('href="/hosting/venues"', false)
        ->assertSee('Your venue')
        ->assertDontSee('Your venues');

    $this->actingAs(venueTeamMember($this->keeper))->get('/menu')->assertOk()->assertSee('Your venue');
});

test('what a venue has: known tiles only, one age at most, and they show on its page', function () {
    $member = venueTeamMember($this->keeper);
    $this->venue->update(['status' => Venue::APPROVED]);

    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['amenities' => ['bar', 'made_up', 'age_18', 'age_21', 'wheelchair_accessible']])
        ->assertOk()->assertJsonPath('amenities', ['wheelchair_accessible', 'bar', 'age_18']);

    $this->get($this->venue->publicPath())->assertSee("What's there", false)->assertSee('wheelchair_accessible');

    // The venue's own words, per group: trimmed, once each, a known label becomes its key, never in Age.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['amenities' => [
        'quiet_room',
        ['group' => 'access', 'label' => '  Ramp at   the side door '],
        ['group' => 'access', 'label' => 'ramp at the side door'],
        ['group' => 'comfort', 'label' => 'Seating'],
        ['group' => 'age', 'label' => '16+'],
        ['group' => 'nowhere', 'label' => 'Moat'],
        ['group' => 'food_drink', 'label' => str_repeat('x', 60)],
    ]])->assertOk()->assertJsonPath('amenities', [
        'quiet_room',
        'seating',
        ['group' => 'access', 'label' => 'Ramp at the side door'],
        ['group' => 'food_drink', 'label' => str_repeat('x', 40)],
    ]);
    $this->get($this->venue->publicPath())->assertSee('Ramp at the side door');

    // None picked: nothing kept, and no section.
    $this->actingAs($member)->postJson("/venues/{$this->venue->slug}", ['amenities' => []])->assertOk();
    expect($this->venue->fresh()->amenities)->toBeNull();
    $this->get($this->venue->publicPath())->assertDontSee("What's there", false);
});

test('Add a venue: the steps open for someone who may add one, and carry on for its keeper', function () {
    $member = venueTeamMember($this->keeper);

    $this->actingAs($member)->get('/hosting/venues')->assertOk()->assertSee('Add a venue');
    $this->actingAs($member)->get('/hosting/venues/new')->assertOk()->assertSee('vue-venue-create', false);
    $this->actingAs($member)->get("/venues/{$this->venue->slug}/setup")->assertOk();

    // Someone with no organization and no venue may not; someone else's venue is not theirs to carry on.
    $nobody = User::factory()->create(['type' => 'u']);
    $this->actingAs($nobody)->get('/hosting/venues/new')->assertForbidden();
    $this->actingAs($nobody)->get("/venues/{$this->venue->slug}/setup")->assertForbidden();
});

test('a person with no organization who keeps a venue may add another, and keeps it themselves', function () {
    $person = User::factory()->create(['type' => 'u']);
    Venue::factory()->create(['user_id' => $person->id]);

    $made = $this->actingAs($person)->postJson('/api/venues', ['name' => 'Second Space', 'street' => '2 Side St', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6])
        ->assertCreated()->json('venue');

    $venue = Venue::find($made['id']);
    expect($venue->user_id)->toBe($person->id)
        ->and($venue->organizer_id)->toBeNull()
        ->and($venue->status)->toBe(Venue::PENDING);
});

test('the address check names the known venue\'s page, for "go to its page"', function () {
    $member = venueTeamMember($this->keeper);
    $this->venue->update(['status' => Venue::APPROVED, 'street' => '50 Dearmans Place', 'city' => 'Salford']);

    $this->actingAs($member)->getJson('/api/venues/match?street=50+Dearmans+Place&city=Salford')
        ->assertOk()->assertJsonPath('venue.slug', $this->venue->slug)->assertJsonPath('venue.url', $this->venue->publicPath());
});

test('Add a venue makes a draft from its name: in Your venues, nowhere else, until it is sent', function () {
    $member = venueTeamMember($this->keeper);

    $draft = $this->actingAs($member)->postJson('/hosting/venues', ['name' => 'Temp one'])->assertCreated()->json();
    $venue = Venue::find($draft['id']);
    expect($venue->status)->toBe(Venue::DRAFT)->and($venue->organizer_id)->toBe($this->keeper->id);

    // In Your venues, as a draft to finish.
    $this->actingAs($member)->get('/hosting/venues')->assertSee('Temp one')->assertSee('"draft":true', false)
        ->assertSee(json_encode("/venues/{$venue->slug}/setup"), false);
    // Not offered to anyone picking a venue, not public, not in EI's queue.
    $this->actingAs(User::factory()->create(['type' => 'm']))->getJson('/api/venues/search?keywords=Temp')->assertJsonCount(0);
    $this->actingAs(User::factory()->create(['type' => 'u']))->get($venue->publicPath())->assertNotFound();
    $this->actingAs(User::factory()->create(['type' => 'a']))->getJson('/api/admin/approval-counts')->assertJsonPath('venues', 0);

    // Sending it needs an address.
    $this->actingAs($member)->postJson("/venues/{$venue->slug}/submit")->assertUnprocessable();
    $this->actingAs($member)->postJson("/venues/{$venue->slug}", ['street' => '1 Main St', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6])->assertOk();
    $this->actingAs($member)->postJson("/venues/{$venue->slug}/submit")->assertOk()->assertJsonPath('status', Venue::PENDING);
    expect($venue->fresh()->status)->toBe(Venue::PENDING);
});

test('a venue waiting for EI, or a draft, shows to its keeper and staff as a preview, and to nobody else', function () {
    $member = venueTeamMember($this->keeper);
    $this->venue->update(['status' => Venue::PENDING]);

    $this->actingAs($member)->get($this->venue->publicPath())->assertOk()->assertSee('Waiting for EI to approve it');
    $this->actingAs(User::factory()->create(['type' => 'm']))->get($this->venue->publicPath())->assertOk();

    $this->actingAs(venueTeamMember(Organizer::factory()->create(['status' => 'p'])))->get($this->venue->publicPath())->assertNotFound();
    auth()->logout();
    $this->get($this->venue->publicPath())->assertNotFound();

    $this->venue->update(['status' => Venue::DRAFT]);
    $this->actingAs($member)->get($this->venue->publicPath())->assertOk()->assertSee('Finish adding it');
});

test('a venue of your own shows as yours, with where to open it, and only your own drafts show', function () {
    $member = venueTeamMember($this->keeper);
    $this->venue->update(['status' => Venue::APPROVED, 'street' => '1 Main St', 'city' => 'Chicago']);
    $draft = $this->actingAs($member)->postJson('/hosting/venues', ['name' => 'Albert again'])->json();

    $found = $this->actingAs($member)->getJson("/api/venues/known?street=1+Main+St&city=Chicago&except={$draft['slug']}")->json();
    expect($found[0])->yours->toBeTrue()->edit_url->toBe("/venues/{$this->venue->slug}/edit");

    // Someone else's drafts never show; their own do.
    $other = venueTeamMember(Organizer::factory()->create(['status' => 'p']));
    expect(collect($this->actingAs($other)->getJson('/api/venues/known?name=Albert+again')->json())->pluck('name'))->not->toContain('Albert again');
    expect(collect($this->actingAs($member)->getJson('/api/venues/known?name=Albert+again')->json())->pluck('name'))->toContain('Albert again');

});

test('a draft can be deleted by whoever is adding it; a venue sent to EI cannot', function () {
    $member = venueTeamMember($this->keeper);
    $draft = $this->actingAs($member)->postJson('/hosting/venues', ['name' => 'Scratch'])->json();

    $this->actingAs(venueTeamMember(Organizer::factory()->create(['status' => 'p'])))->deleteJson("/venues/{$draft['slug']}")->assertForbidden();
    $this->actingAs($member)->deleteJson("/venues/{$this->venue->slug}")->assertStatus(422);

    // Your venues hands its list a delete address for the draft only (venues-list.vue asks, then deletes).
    $page = $this->actingAs($member)->get('/hosting/venues')->assertOk()->getContent();
    expect($page)->toContain('"delete_url":'.json_encode("/venues/{$draft['slug']}"))
        ->and(substr_count($page, '"delete_url":null'))->toBeGreaterThanOrEqual(1);
    $this->actingAs($member)->delete("/venues/{$draft['slug']}")->assertRedirect(route('hosting.venues'));

    expect(Venue::find($draft['id']))->toBeNull()->and($this->venue->fresh())->not->toBeNull();
});

test('a live venue renamed by its team waits for EI; approved, the old address redirects', function () {
    Illuminate\Support\Facades\Mail::fake();
    $venue = Venue::factory()->create(['name' => 'Albert Hall', 'status' => Venue::APPROVED, 'organizer_id' => $this->keeper->id]);

    $this->actingAs(venueTeamMember($this->keeper))->postJson("/venues/{$venue->slug}", ['name' => 'Spam Hall'])->assertOk()->assertJsonPath('pending_name', 'Spam Hall');
    $this->postJson("/venues/{$venue->slug}", ['name' => 'Grand Hall'])->assertOk();
    expect($venue->fresh()->name)->toBe('Albert Hall');
    $request = App\Models\NameChangeRequest::sole();
    expect($request->requested_name)->toBe('Grand Hall');

    $this->actingAs(staffModerator())->postJson("/api/admin/approve/requests/{$request->id}/approve")->assertOk();
    $venue = $venue->fresh();
    expect($venue->name)->toBe('Grand Hall')->and($venue->slug)->toBe('grand-hall');
    $this->get('/venues/albert-hall')->assertRedirect('/venues/grand-hall');
});

test('an email EI found for a venue nobody has claimed is not printed on its page; once claimed it is', function () {
    $venue = App\Models\Venue::factory()->create(['status' => App\Models\Venue::APPROVED, 'email' => 'hello@venue.example', 'phone' => '+1 415 555 0100', 'organizer_id' => null, 'user_id' => App\Models\User::factory()->create(['type' => 'a'])->id, 'claimed_at' => null]);

    $this->get($venue->publicPath())->assertOk()->assertDontSee('hello@venue.example')->assertSee('+1 415 555 0100');

    $venue->forceFill(['claimed_at' => now()])->save();
    $this->get($venue->publicPath())->assertOk()->assertSee('hello@venue.example');
});
