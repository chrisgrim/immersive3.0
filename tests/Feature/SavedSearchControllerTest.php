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

test('saving different criteria creates a separate row, up to the cap — recent searches is a real history', function () {
    // Regression: this used to collapse every ordinary search into a single
    // rotating "recent" slot, so the dropdown only ever showed the latest
    // one. A user can have up to MAX_SAVED_SEARCHES total, so under the cap
    // each distinct search should get its own row.
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'New York, NY', 'criteria' => ['city' => 'New York, NY'],
    ])->assertCreated()->json('search');

    $second = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Los Angeles, CA', 'criteria' => ['city' => 'Los Angeles, CA'],
    ])->assertCreated()->json('search');

    $this->assertDatabaseCount('saved_searches', 2);
    expect($second['id'])->not->toBe($first['id']);
    $this->assertDatabaseHas('saved_searches', ['id' => $first['id'], 'name' => 'New York, NY']);
    $this->assertDatabaseHas('saved_searches', ['id' => $second['id'], 'name' => 'Los Angeles, CA']);
});

test('once at the cap, a new distinct search evicts the least-recently-touched scratch row', function () {
    $user = User::factory()->create();

    $oldest = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Search A', 'criteria' => ['city' => 'City A'],
    ])->assertCreated()->json('search');

    for ($i = 1; $i <= 4; $i++) {
        $this->actingAs($user)->postJson('/api/hub/saved-searches', [
            'name' => "Search {$i}", 'criteria' => ['city' => "City {$i}"],
        ])->assertCreated();
    }

    $newest = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Search E', 'criteria' => ['city' => 'City E'],
    ])->assertCreated()->json('search');

    $this->assertDatabaseCount('saved_searches', App\Actions\Search\SaveSearchAction::MAX_SAVED_SEARCHES);

    // The 7th distinct search evicts "Search A" (the oldest, least-recently
    // touched row) rather than any of the more recently created ones.
    $seventh = $this->actingAs($user)->postJson('/api/hub/saved-searches', [
        'name' => 'Search F', 'criteria' => ['city' => 'City F'],
    ])->assertCreated()->json('search');

    expect($seventh['id'])->toBe($oldest['id']);
    $this->assertDatabaseHas('saved_searches', ['id' => $oldest['id'], 'name' => 'Search F']);
    $this->assertDatabaseHas('saved_searches', ['id' => $newest['id'], 'name' => 'Search E']);
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

test('unpinning a row never deletes another unpinned row — a genuine multi-row history, not a single slot', function () {
    // SaveSearchAction keeps up to MAX_SAVED_SEARCHES distinct recent
    // searches, not one rotating slot — unpinning one of them must not
    // wipe out an unrelated, legitimately distinct recent search.
    $user = User::factory()->create();
    $alreadyUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Boston, MA', 'pinned' => false]);
    $toUnpin = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Los Angeles, CA', 'pinned' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$toUnpin->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', false);

    $this->assertDatabaseCount('saved_searches', 2);
    $this->assertDatabaseHas('saved_searches', ['id' => $toUnpin->id, 'pinned' => false]);
    $this->assertDatabaseHas('saved_searches', ['id' => $alreadyUnpinned->id, 'name' => 'Boston, MA']);
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

test('listing sorts by updated_at, not created_at — an evicted rows new content sorts to the top', function () {
    // Regression (caught in review): index() used to sort by created_at,
    // but SaveSearchAction's eviction overwrites a scratch row's content in
    // place and bumps updated_at without touching created_at — so a row
    // created long ago but just repurposed into the newest search would
    // sort to the bottom under the old created_at ordering.
    $user = User::factory()->create();

    $oldRow = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Ancient row', 'is_scratch' => true]);
    $this->travel(1)->minutes();
    SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Created after, never touched again', 'is_scratch' => true]);
    $this->travel(1)->minutes();
    // Simulate SaveSearchAction's eviction: overwrite the OLD row's content
    // and bump its updated_at, exactly what the real eviction does.
    $oldRow->update(['name' => 'Just repurposed']);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches');

    $names = collect($response->json('searches'))->pluck('name');
    expect($names->all())->toBe(['Just repurposed', 'Created after, never touched again']);
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

// ---------------------------------------------------------------------------
// GET /api/hub/saved-searches?dropdown=1 — the nav search bar's "Recent
// searches" quick-access dropdown, a deliberately reduced view (spelled out
// directly by the user): every pinned search, plus at most one more — the
// single most-recently-touched search that isn't pinned. Without ?dropdown,
// the full list still comes back unreduced (the Saved Search Preferences
// page's own fetch).
// ---------------------------------------------------------------------------

test('dropdown view shows every pinned search plus only the single most recent unpinned one', function () {
    $user = User::factory()->create();

    $oldestUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Oldest unpinned', 'pinned' => false]);
    $this->travel(1)->minutes();
    $middleUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Middle unpinned', 'pinned' => false]);
    $this->travel(1)->minutes();
    $newestUnpinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Newest unpinned', 'pinned' => false]);
    $pinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'A pinned search', 'pinned' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches?dropdown=1')->assertOk();

    $names = collect($response->json('searches'))->pluck('name');
    expect($names->all())->toBe(['A pinned search', 'Newest unpinned']);
    expect($names)->not->toContain('Middle unpinned');
    expect($names)->not->toContain('Oldest unpinned');
});

test('dropdown view without ?dropdown returns the full unreduced list', function () {
    $user = User::factory()->create();
    SavedSearch::factory()->count(3)->create(['user_id' => $user->id, 'pinned' => false]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches')->assertOk();

    expect($response->json('searches'))->toHaveCount(3);
});

test('dropdown view with every search pinned shows all of them, no unpinned slot added', function () {
    $user = User::factory()->create();
    SavedSearch::factory()->count(3)->create(['user_id' => $user->id, 'pinned' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches?dropdown=1')->assertOk();

    expect($response->json('searches'))->toHaveCount(3);
});

test('dropdown view does not duplicate a pinned search that also happens to be the most recent', function () {
    $user = User::factory()->create();
    SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Older pinned', 'pinned' => true]);
    $this->travel(1)->minutes();
    $newestIsPinned = SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Newest, also pinned', 'pinned' => true]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches?dropdown=1')->assertOk();

    // 2 pinned searches, nothing unpinned exists at all — still just 2, not 3.
    expect($response->json('searches'))->toHaveCount(2);
});

test('dropdown view with nothing pinned shows just the single most recent search', function () {
    $user = User::factory()->create();
    SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Older', 'pinned' => false]);
    $this->travel(1)->minutes();
    SavedSearch::factory()->create(['user_id' => $user->id, 'name' => 'Newer', 'pinned' => false]);

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches?dropdown=1')->assertOk();

    $names = collect($response->json('searches'))->pluck('name');
    expect($names->all())->toBe(['Newer']);
});

test('dropdown view with nothing saved at all returns an empty list, not an error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/hub/saved-searches?dropdown=1')->assertOk();

    expect($response->json('searches'))->toBe([]);
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

test('updating an unpinned row changes that exact row without pinning it, but protects it from overwrite', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'pinned' => false,
        'is_scratch' => true,
        'criteria' => ['city' => 'Old City', 'searchType' => 'inPerson'],
    ]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'New name',
        'criteria' => ['city' => 'New City', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ]);

    $response->assertOk();
    // Editing is not pinning — a user who just wants to save changes
    // without pinning to the top of the list shouldn't have that decision
    // made for them (regression: this used to force pinned=true on every
    // edit with no way to opt out).
    expect($response->json('search.pinned'))->toBeFalse();
    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'pinned' => false, 'is_scratch' => false]);
});

test('the next auto-save after an edit creates a fresh row, leaving the edited one untouched', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false, 'is_scratch' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'Edited', 'criteria' => ['city' => 'Edited City', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson'],
    ])->assertOk();

    $action = app(App\Actions\Search\SaveSearchAction::class);
    $newRow = $action->handle($user->id, 'Auto search', ['city' => 'Auto City', 'searchType' => 'inPerson']);

    expect($newRow->id)->not->toBe($search->id);
    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => 'Edited', 'pinned' => false, 'is_scratch' => false]);
    $this->assertDatabaseCount('saved_searches', 2);
});

test('unpinning a deliberately-edited row never lets a later ordinary search overwrite it', function () {
    // The exact bug reported live: edit a search (adds a price limit),
    // don't pin it, then later unpin it (it got force-pinned by mistake at
    // the time) — the next ordinary nav search must NOT silently cannibalize
    // it, and a week of further searching must not make it disappear.
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'pinned' => false, 'is_scratch' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'New York, NY (under $5)',
        'criteria' => ['city' => 'New York, NY', 'lat' => 1.0, 'lng' => 2.0, 'searchType' => 'inPerson', 'price' => [0, 5]],
    ])->assertOk();

    // Simulate the user pinning it, then changing their mind and unpinning.
    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/pin")->assertOk();
    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/pin")
        ->assertOk()->assertJsonPath('search.pinned', false);

    $action = app(App\Actions\Search\SaveSearchAction::class);
    $action->handle($user->id, 'Chicago, IL', ['city' => 'Chicago, IL']);
    $action->handle($user->id, 'Boston, MA', ['city' => 'Boston, MA']);

    $this->assertDatabaseHas('saved_searches', ['id' => $search->id, 'name' => 'New York, NY (under $5)']);
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

// ---------------------------------------------------------------------------
// PATCH /api/hub/saved-searches/{savedSearch}/notify — the "notify me about
// new events" pilot toggle (see NotifySavedSearchMatchesCommand's docblock
// and config('features.saved_search_notifications_user')).
// ---------------------------------------------------------------------------

test('the pilot user can enable notify on their own search', function () {
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => false, 'last_checked_at' => null, 'is_scratch' => true]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertOk();

    expect($response->json('search.notifyNewEvents'))->toBeTrue();
    expect($search->fresh()->notify_new_events)->toBeTrue();
    // Enabling sets a fresh cursor — see the endpoint's own comment on why
    // (avoids the first scheduled run emailing a backlog).
    expect($search->fresh()->last_checked_at)->not->toBeNull();
    // Regression (Codex caught this in review): enabling notify must clear
    // is_scratch, same as pinning/editing — otherwise this row could later
    // be silently repurposed by SaveSearchAction's eviction into an
    // unrelated search while notify_new_events stays on, quietly emailing
    // the user about a search they never opted into.
    expect($search->fresh()->is_scratch)->toBeFalse();
});

test('a non-pilot user cannot enable notify on their own search', function () {
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    $user = User::factory()->create(['email' => 'someone-else@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => false]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertStatus(403);

    expect($search->fresh()->notify_new_events)->toBeFalse();
});

test('a non-pilot user CAN disable notify if it was somehow already on', function () {
    // Turning something off should never be blocked, even for a row that
    // ended up enabled outside the pilot (e.g. the pilot email config
    // changed after the fact).
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    $user = User::factory()->create(['email' => 'someone-else@example.com']);
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true]);

    $response = $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertOk();

    expect($response->json('search.notifyNewEvents'))->toBeFalse();
});

test('disabling notify does not touch the existing cursor', function () {
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    $user = User::factory()->create(['email' => 'pilot@example.com']);
    // Whole seconds — a MySQL datetime column truncates sub-second
    // precision, so comparing to a microsecond-precision now() would be a
    // false negative on an otherwise-untouched value.
    $checkedAt = now()->subDays(3)->startOfSecond();
    $search = SavedSearch::factory()->create(['user_id' => $user->id, 'notify_new_events' => true, 'last_checked_at' => $checkedAt, 'is_scratch' => true]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertOk();

    expect($search->fresh()->last_checked_at->eq($checkedAt))->toBeTrue();
    // Disabling is never what protects a row, so it shouldn't be what
    // un-protects one either — is_scratch is left exactly as it was.
    expect($search->fresh()->is_scratch)->toBeTrue();
});

test('a user cannot toggle notify on another users saved search', function () {
    config(['features.saved_search_notifications_user' => 'pilot@example.com']);
    $owner = User::factory()->create(['email' => 'pilot@example.com']);
    $other = User::factory()->create(); // ownership check, not pilot status — any other user
    $search = SavedSearch::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertStatus(404);
});

test('a guest cannot toggle notify', function () {
    $search = SavedSearch::factory()->create();

    $this->patchJson("/api/hub/saved-searches/{$search->id}/notify")->assertStatus(401);
});

// ---------------------------------------------------------------------------
// UpdateSavedSearchAction — cursor reset when criteria changes while
// notifications are enabled (see that action's own comment for the why).
// ---------------------------------------------------------------------------

test('editing criteria on a notify-enabled search resets the cursor', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notify_new_events' => true,
        'last_checked_at' => now()->subDays(5),
        'criteria' => ['city' => 'Old City', 'categories' => [], 'tags' => [], 'price' => null],
    ]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => $search->name,
        'criteria' => ['city' => 'New City', 'lat' => 1.0, 'lng' => 1.0, 'searchType' => 'inPerson'],
    ])->assertOk();

    expect($search->fresh()->last_checked_at->greaterThan(now()->subMinute()))->toBeTrue();
});

test('editing only the name (criteria unchanged) does not reset the cursor', function () {
    // SavedSearch::factory()'s own fingerprint is a random placeholder (see
    // its own comment) — never equal to what NormalizeSavedSearchCriteriaAction
    // actually computes, so "criteria unchanged" can't be tested through it.
    // A real, matching fingerprint is required here specifically because
    // this test needs the false branch of the changed/unchanged check.
    $user = User::factory()->create();
    $checkedAt = now()->subDays(5)->startOfSecond();
    $rawCriteria = ['city' => 'Same City', 'lat' => 1.0, 'lng' => 1.0, 'searchType' => 'inPerson'];
    $normalized = (new App\Actions\Search\NormalizeSavedSearchCriteriaAction)->handle($rawCriteria);
    $search = SavedSearch::create([
        'user_id' => $user->id,
        'name' => 'Old name',
        'criteria' => $normalized,
        'fingerprint' => hash('sha256', json_encode($normalized)),
        'notify_new_events' => true,
        'last_checked_at' => $checkedAt,
    ]);

    // Submitting the exact same raw criteria — UpdateSavedSearchAction
    // normalizes it the same way, producing the same fingerprint.
    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => 'A brand new name',
        'criteria' => $rawCriteria,
    ])->assertOk();

    expect($search->fresh()->last_checked_at->eq($checkedAt))->toBeTrue();
});

test('editing criteria on a notify-disabled search does not touch the (null) cursor', function () {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notify_new_events' => false,
        'last_checked_at' => null,
        'criteria' => ['city' => 'Old City', 'categories' => [], 'tags' => [], 'price' => null],
    ]);

    $this->actingAs($user)->patchJson("/api/hub/saved-searches/{$search->id}", [
        'name' => $search->name,
        'criteria' => ['city' => 'New City', 'lat' => 1.0, 'lng' => 1.0, 'searchType' => 'inPerson'],
    ])->assertOk();

    expect($search->fresh()->last_checked_at)->toBeNull();
});
