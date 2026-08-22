<?php

use App\Models\Category;
use App\Models\SavedSearch;
use App\Models\User;

test('a user can save a search', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'NYC escape rooms',
        'criteria' => [
            'city' => 'New York, NY',
            'lat' => 40.7128,
            'lng' => -74.006,
            'searchType' => 'inPerson',
            'live' => false,
            'categories' => [$category->id],
            'tags' => [],
            'price' => null,
        ],
    ]);

    $response->assertCreated();
    expect($response->json('search.name'))->toBe('NYC escape rooms');
    expect($response->json('search.url'))->toContain('/index/search?');
    expect($response->json('search.url'))->toContain('city=New+York%2C+NY');
    expect($response->json('search.url'))->toContain('searchType=inPerson');
    expect($response->json('search.url'))->toContain("category={$category->id}");
    $this->assertDatabaseCount('saved_searches', 1);
});

test('saving the same criteria twice is idempotent, not a duplicate', function () {
    $user = User::factory()->create();
    $categories = Category::factory()->count(2)->create()->pluck('id')->all();
    $payload = ['name' => 'My search', 'criteria' => ['city' => 'Chicago, IL', 'categories' => $categories]];

    $this->actingAs($user)->postJson('/api/hub/saved-searches', $payload)->assertCreated();
    $this->actingAs($user)->postJson('/api/hub/saved-searches', $payload)->assertCreated();

    $this->assertDatabaseCount('saved_searches', 1);
});

test('category order does not affect the fingerprint', function () {
    $user = User::factory()->create();
    [$categoryA, $categoryB] = Category::factory()->count(2)->create();

    $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'A', 'criteria' => ['categories' => [$categoryA->id, $categoryB->id]],
    ])->assertCreated();

    $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'B', 'criteria' => ['categories' => [$categoryB->id, $categoryA->id]],
    ])->assertCreated();

    $this->assertDatabaseCount('saved_searches', 1);
});

test('saving different criteria overwrites the existing unpinned row instead of creating a new one', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY', 'criteria' => ['city' => 'New York, NY'],
    ])->assertCreated()->json('search');

    $second = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Los Angeles, CA', 'criteria' => ['city' => 'Los Angeles, CA'],
    ])->assertCreated()->json('search');

    $this->assertDatabaseCount('saved_searches', 1);
    expect($second['id'])->toBe($first['id']);
    expect($second['name'])->toBe('Los Angeles, CA');
    $this->assertDatabaseHas('saved_searches', ['id' => $first['id'], 'name' => 'Los Angeles, CA']);
});

test('pinning the current row protects it, and the next save creates a fresh unpinned row', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY', 'criteria' => ['city' => 'New York, NY'],
    ])->assertCreated()->json('search');

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$first['id']}/pin")
        ->assertOk()
        ->assertJsonPath('search.pinned', true);

    $second = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Los Angeles, CA', 'criteria' => ['city' => 'Los Angeles, CA'],
    ])->assertCreated()->json('search');

    $this->assertDatabaseCount('saved_searches', 2);
    expect($second['id'])->not->toBe($first['id']);
    $this->assertDatabaseHas('saved_searches', ['id' => $first['id'], 'name' => 'New York, NY', 'pinned' => true]);
    $this->assertDatabaseHas('saved_searches', ['id' => $second['id'], 'name' => 'Los Angeles, CA', 'pinned' => false]);
});

test('re-searching the same criteria as an already-pinned row does not 500', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY', 'criteria' => ['city' => 'New York, NY'],
    ])->assertCreated()->json('search');

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$first['id']}/pin")->assertOk();

    // Same criteria as the now-pinned row, submitted again as an ordinary
    // search (not an edit) — this used to attempt a duplicate-fingerprint
    // insert and throw a raw QueryException.
    $again = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY', 'criteria' => ['city' => 'New York, NY'],
    ])->assertCreated()->json('search');

    expect($again['id'])->toBe($first['id']);
    $this->assertDatabaseCount('saved_searches', 1);
    $this->assertDatabaseHas('saved_searches', ['id' => $first['id'], 'pinned' => true]);
});

test('replaying a plain city search includes live=false so it lands on the map view', function () {
    // ListingsController::index() picks search.location (the map view) via
    // isset($request->live), not its value — a replay URL that dropped the
    // param entirely for a false value would silently lose the map.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY',
        'criteria' => ['city' => 'New York, NY', 'searchType' => 'inPerson', 'live' => false],
    ])->assertCreated();

    expect($response->json('search.url'))->toContain('live=false');
});

test('a remoteLocation slug round-trips through save and the replay URL', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Telephone',
        'criteria' => ['searchType' => 'atHome', 'remoteLocation' => 'telephone'],
    ])->assertCreated();

    expect($response->json('search.criteria.remoteLocation'))->toBe('telephone');
    expect($response->json('search.url'))->toContain('remoteLocation=telephone');
});

test('toggling pin flips the flag and is idempotent to call again', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', true);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', false);
});

test('unpinning a row deletes whatever other unpinned row already existed, leaving exactly one', function () {
    // Regression test: SaveSearchAction only ever reclaims the single most
    // recent unpinned row on the next search — if unpinning left a SECOND
    // unpinned row sitting around, the next auto-save would only overwrite
    // one of them, silently orphaning the other forever (reported live:
    // "unpinned a search, then did a new search and I still see 2 searches").
    $user = User::factory()->create();
    $alreadyUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Boston, MA', 'pinned' => false]);
    $toUnpin = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Los Angeles, CA', 'pinned' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$toUnpin->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', false);

    $this->assertDatabaseCount('saved_searches', 1);
    $this->assertDatabaseHas('saved_searches', ['id' => $toUnpin->id, 'pinned' => false]);
    $this->assertDatabaseMissing('saved_searches', ['id' => $alreadyUnpinned->id]);
});

test('unpinning when no other unpinned row exists just flips the flag, nothing deleted', function () {
    $user = User::factory()->create();
    $pinned = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => true]);
    $otherPinned = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$pinned->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', false);

    $this->assertDatabaseCount('saved_searches', 2);
    $this->assertDatabaseHas('saved_searches', ['id' => $pinned->id, 'pinned' => false]);
    $this->assertDatabaseHas('saved_searches', ['id' => $otherPinned->id, 'pinned' => true]);
});

test('a user cannot pin another users saved search', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $owner->id, 'pinned' => false]);

    $this->actingAs($otherUser)
        ->patchJson("/api/hub/saved-searches/{$search->id}/pin")
        ->assertStatus(404);

    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'pinned' => false]);
});

test('a different user saving the same criteria gets their own row', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $payload = ['name' => 'Same search', 'criteria' => ['city' => 'Austin, TX']];

    $this->actingAs($userA)->postJson('/api/hub/saved-searches', $payload)->assertCreated();
    $this->actingAs($userB)->postJson('/api/hub/saved-searches', $payload)->assertCreated();

    $this->assertDatabaseCount('saved_searches', 2);
});

test('saving requires a name and criteria', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/hub/saved-searches', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'criteria']);
});

test('saving rejects an invalid searchType', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Bad', 'criteria' => ['searchType' => 'not-a-real-type'],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.searchType']);
});

test('a user is blocked from saving a 7th search once every existing row is pinned', function () {
    // The cap only bites once there's no unpinned slot left to reuse — see
    // the "still succeeds" tests below for the cases that must NOT be
    // blocked even while at the cap.
    $user = User::factory()->create();
    SavedSearch::factory()->count(6)->create(['user_id' => $user->id, 'pinned' => true]);

    $this->actingAs($user)
        ->postJson('/api/hub/saved-searches', ['name' => 'One too many', 'criteria' => ['city' => 'Denver, CO']])
        ->assertStatus(422);

    $this->assertDatabaseCount('saved_searches', 6);
});

test('re-searching an exact match still succeeds even while at the cap', function () {
    // Regression test (Codex caught this): the cap used to be an upfront
    // count() check in the controller, before SaveSearchAction ever got a
    // chance to recognize this as a fingerprint match that reuses an
    // existing row rather than creating a new one — so a user at the cap
    // got a 422 just for searching something they'd already saved.
    $user = User::factory()->create();
    $this->actingAs($user);

    $created = $this->postJson('/api/hub/saved-searches', ['name' => 'Denver', 'criteria' => ['city' => 'Denver, CO']])
        ->assertCreated()
        ->json('search');
    SavedSearch::whereKey($created['id'])->update(['pinned' => true]);
    SavedSearch::factory()->count(5)->create(['user_id' => $user->id, 'pinned' => true]);
    $this->assertDatabaseCount('saved_searches', 6);

    $this->postJson('/api/hub/saved-searches', ['name' => 'Denver again', 'criteria' => ['city' => 'Denver, CO']])
        ->assertCreated()
        ->assertJsonPath('search.id', $created['id']);

    $this->assertDatabaseCount('saved_searches', 6);
});

test('a search that reuses the unpinned slot still succeeds even while at the cap', function () {
    $user = User::factory()->create();
    $unpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false]);
    SavedSearch::factory()->count(5)->create(['user_id' => $user->id, 'pinned' => true]);

    $this->actingAs($user)
        ->postJson('/api/hub/saved-searches', ['name' => 'A new search', 'criteria' => ['city' => 'Denver, CO']])
        ->assertCreated()
        ->assertJsonPath('search.id', $unpinned->id);

    $this->assertDatabaseCount('saved_searches', 6);
});

test('a concurrent duplicate insert falls back to the winning row instead of a 500', function () {
    // Regression test (Codex caught this): two auto-saves for the same user
    // with identical criteria can both pass SaveSearchAction's $existing/
    // $current lookups (finding nothing) before either has inserted — the
    // loser then hits this table's (user_id, fingerprint) unique constraint
    // on its own create() call instead of the row it should have reused.
    //
    // Real concurrency isn't reproducible in a synchronous test process, and
    // pre-creating the "other" row before calling store() doesn't exercise
    // this at all — SaveSearchAction's own $existing lookup would just find
    // it immediately and return early, never reaching create(). A
    // model 'creating' event is the only way to inject the competing insert
    // at the exact moment this test needs it: after this request's own
    // $existing/$current checks have already run and found nothing, but
    // before its create() call actually hits the database.
    $user = User::factory()->create();
    $criteria = ['city' => 'Denver, CO'];
    $normalized = (new App\Actions\Search\NormalizeSavedSearchCriteriaAction)->handle($criteria);
    $fingerprint = hash('sha256', json_encode($normalized));

    $winner = null;
    $injected = false;
    SavedSearch::creating(function () use ($user, $fingerprint, &$winner, &$injected) {
        // Guard against re-entry: the winner row's own factory create() below
        // fires this same 'creating' event — only inject once.
        if ($injected) {
            return;
        }
        $injected = true;

        $winner = SavedSearch::factory()->create([
            'user_id' => $user->id,
            'pinned' => true, // pinned, so it can't collide with the $current (unpinned) slot too
            'fingerprint' => $fingerprint,
        ]);
    });

    try {
        $this->actingAs($user)
            ->postJson('/api/hub/saved-searches', ['name' => 'Denver', 'criteria' => $criteria])
            ->assertCreated()
            ->assertJsonPath('search.id', $winner->id);
    } finally {
        SavedSearch::flushEventListeners();
    }

    $this->assertDatabaseCount('saved_searches', 1);
});

test('a user can list only their own saved searches, most recent first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $older = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Older']);
    $this->travel(1)->minutes();
    $newer = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Newer']);
    SavedSearch::factory()->create(['user_id' => $other->id, 'name' => 'Not mine']);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches');

    $response->assertOk();
    $names = collect($response->json('searches'))->pluck('name');
    expect($names->all())->toBe(['Newer', 'Older']);
});

test('pinned searches are always listed before unpinned ones, regardless of recency', function () {
    $user = User::factory()->create();

    $newerUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Newer unpinned', 'pinned' => false]);
    $this->travel(1)->minutes();
    $olderPinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Older pinned', 'pinned' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches');

    $names = collect($response->json('searches'))->pluck('name');
    expect($names->all())->toBe(['Older pinned', 'Newer unpinned']);
});

test('a user can delete their own saved search', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/hub/saved-searches/{$search->id}")
        ->assertOk();

    $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
});

test('a user cannot delete another users saved search', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->deleteJson("/api/hub/saved-searches/{$search->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('saved_searches', ['id' => $search->id]);
});

test('a guest is blocked from all saved search endpoints', function () {
    $search = SavedSearch::factory()->create();

    $this->getJson('/api/hub/saved-searches')->assertStatus(401);
    $this->postJson('/api/hub/saved-searches', ['name' => 'x', 'criteria' => []])->assertStatus(401);
    $this->deleteJson("/api/hub/saved-searches/{$search->id}")->assertStatus(401);
    $this->patchJson("/api/hub/saved-searches/{$search->id}/pin")->assertStatus(401);
    $this->patchJson("/api/hub/saved-searches/{$search->id}", ['name' => 'x', 'criteria' => []])->assertStatus(401);
});

// ============================================================
// update — deliberate edit of one exact row (Hub editor)
// ============================================================

test('updating a pinned row changes that exact row', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old name',
        'pinned' => true,
        'criteria' => ['city' => 'Old City', 'searchType' => 'inPerson'],
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'New name',
        'criteria' => ['city' => 'New City', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ]);

    $response->assertOk();
    expect($response->json('search.id'))->toBe($search->id);
    expect($response->json('search.name'))->toBe('New name');
    expect($response->json('search.criteria.city'))->toBe('New City');
    expect($response->json('search.pinned'))->toBeTrue();
    $this->assertDatabaseCount('saved_searches', 1);
});

test('updating an unpinned row changes that exact row and pins it', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'pinned' => false,
        'criteria' => ['city' => 'Old City', 'searchType' => 'inPerson'],
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'New name',
        'criteria' => ['city' => 'New City', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ]);

    $response->assertOk();
    expect($response->json('search.pinned'))->toBeTrue();
    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'pinned' => true]);
});

test('the next auto-save after an edit creates a fresh row, leaving the edited one untouched', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Edited', 'criteria' => ['city' => 'Edited City', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ])->assertOk();

    $action = app(App\Actions\Search\SaveSearchAction::class);
    $newRow = $action->handle($user->id, 'Auto search', ['city' => 'Auto City', 'searchType' => 'inPerson']);

    expect($newRow->id)->not->toBe($search->id);
    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => 'Edited', 'pinned' => true]);
    $this->assertDatabaseCount('saved_searches', 2);
});

test('editing one row never overwrites another unpinned row', function () {
    $user = User::factory()->create();
    $rotatingSlot = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false, 'name' => 'Rotating']);
    $toEdit = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => true, 'name' => 'Pinned target']);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$toEdit->id}", [
        'name' => 'Edited pinned', 'criteria' => ['city' => 'X', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ])->assertOk();

    $this->assertDatabaseHas('saved_searches', ['id' => $rotatingSlot->id, 'name' => 'Rotating']);
    $this->assertDatabaseHas('saved_searches', ['id' => $toEdit->id, 'name' => 'Edited pinned']);
});

test('a user cannot update another users saved search', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $owner->id, 'name' => 'Original']);

    $this->actingAs($otherUser)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Hijacked', 'criteria' => ['city' => 'X', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ])->assertStatus(404);

    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => 'Original']);
});

test('updating to inPerson requires city, lat, and lng', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'X', 'criteria' => ['searchType' => 'inPerson'],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.city', 'criteria.lat', 'criteria.lng']);
});

test('updating to atHome requires a remoteLocation slug', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'X', 'criteria' => ['searchType' => 'atHome'],
    ])->assertStatus(422)->assertJsonValidationErrors(['criteria.remoteLocation']);
});

test('updating clears the other modes fields server-side regardless of what was submitted', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    // Switches to atHome but still sends stale location fields — the
    // server must null them, not trust the client to have cleaned up.
    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'X',
        'criteria' => [
            'searchType' => 'atHome',
            'remoteLocation' => 'telephone',
            'city' => 'Stale City',
            'lat' => 1.0,
            'lng' => 2.0,
        ],
    ]);

    $response->assertOk();
    expect($response->json('search.criteria.city'))->toBeNull();
    expect($response->json('search.criteria.lat'))->toBeNull();
    expect($response->json('search.criteria.lng'))->toBeNull();
});

test('editing a search into an exact duplicate of another returns a 409, not a crash', function () {
    $user = User::factory()->create();
    $other = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Los Angeles, CA']);
    $toEdit = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'New York, NY']);

    $action = app(App\Actions\Search\SaveSearchAction::class);
    // Give $other a real, known fingerprint via the same normalization path.
    $normalized = app(App\Actions\Search\NormalizeSavedSearchCriteriaAction::class)
        ->handle(['city' => 'Los Angeles, CA', 'lat' => 34.0, 'lng' => -118.0, 'searchType' => 'inPerson']);
    $other->update(['criteria' => $normalized, 'fingerprint' => hash('sha256', json_encode($normalized))]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$toEdit->id}", [
        'name' => 'New York, NY',
        'criteria' => ['city' => 'Los Angeles, CA', 'lat' => 34.0, 'lng' => -118.0, 'searchType' => 'inPerson'],
    ]);

    $response->assertStatus(409);
    expect($response->json('existing_search_id'))->toBe($other->id);
    $this->assertDatabaseHas('saved_searches', ['id' => $toEdit->id, 'name' => 'New York, NY']);
});

test('the replay URL for an updated search reflects the new criteria', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Chicago, IL',
        'criteria' => ['city' => 'Chicago, IL', 'lat' => 41.8, 'lng' => -87.6, 'searchType' => 'inPerson', 'live' => false],
    ]);

    $response->assertOk();
    expect($response->json('search.url'))->toContain('city=Chicago%2C+IL');
    expect($response->json('search.url'))->toContain('live=false');
});

test('a custom map search (dragging the map) persists its bounds and replays with a bounding-box URL', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Custom Map Search',
        'criteria' => [
            'city' => 'Custom Map Search',
            'lat' => 41.8,
            'lng' => -87.6,
            'searchType' => 'inPerson',
            'live' => true,
            'NElat' => 42.0,
            'NElng' => -87.5,
            'SWlat' => 41.6,
            'SWlng' => -87.7,
        ],
    ]);

    $response->assertOk();
    // toEqual, not toBe — JSON round-trips 42.0 as the integer 42 (same
    // numeric value, not the same PHP type), which toBe's strict comparison
    // would otherwise fail on.
    expect($response->json('search.criteria.NElat'))->toEqual(42.0);
    expect($response->json('search.criteria.SWlng'))->toEqual(-87.7);

    $url = $response->json('search.url');
    expect($url)->toContain('live=true');
    expect($url)->toContain('NElat=42');
    expect($url)->toContain('NElng=-87.5');
    expect($url)->toContain('SWlat=41.6');
    expect($url)->toContain('SWlng=-87.7');
});

test('switching a custom map search to At Home clears its bounds server-side', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'criteria' => [
        'searchType' => 'inPerson', 'city' => 'Custom Map Search', 'lat' => 41.8, 'lng' => -87.6,
        'live' => true, 'NElat' => 42.0, 'NElng' => -87.5, 'SWlat' => 41.6, 'SWlng' => -87.7,
        'remoteLocation' => null, 'start' => null, 'end' => null, 'categories' => [], 'tags' => [], 'price' => [0, null],
    ]]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Zoom Events',
        'criteria' => ['searchType' => 'atHome', 'remoteLocation' => 'zoom', 'NElat' => 42.0, 'NElng' => -87.5, 'SWlat' => 41.6, 'SWlng' => -87.7],
    ]);

    $response->assertOk();
    expect($response->json('search.criteria.NElat'))->toBeNull();
    expect($response->json('search.criteria.NElng'))->toBeNull();
    expect($response->json('search.criteria.SWlat'))->toBeNull();
    expect($response->json('search.criteria.SWlng'))->toBeNull();
    expect($response->json('search.criteria.live'))->toBeFalse();
});
