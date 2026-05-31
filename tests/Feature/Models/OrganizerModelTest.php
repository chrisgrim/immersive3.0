<?php

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['type' => 'u']);
});

// ----- generateUniqueSlug() (exercised via the creating/updating boot hooks) -----

test('a new organizer gets a slug derived from its name', function () {
    $org = Organizer::factory()->create(['name' => 'The Immersive Co']);

    expect($org->slug)->toBe('the-immersive-co');
});

test('a second organizer with the same name gets an incremented slug', function () {
    Organizer::factory()->create(['name' => 'Repeat Name']);
    $second = Organizer::factory()->create(['name' => 'Repeat Name']);
    $third = Organizer::factory()->create(['name' => 'Repeat Name']);

    expect($second->slug)->toBe('repeat-name-1');
    expect($third->slug)->toBe('repeat-name-2');
});

test('renaming an organizer regenerates its slug while excluding its own id', function () {
    $org = Organizer::factory()->create(['name' => 'Original Brand']);
    expect($org->slug)->toBe('original-brand');

    // rename to a brand-new unique name => clean slug, own row excluded so no suffix
    $org->update(['name' => 'Fresh Brand']);
    expect($org->fresh()->slug)->toBe('fresh-brand');
});

test('updating an organizer without changing its name leaves the slug untouched', function () {
    $org = Organizer::factory()->create(['name' => 'Stable Brand']);
    $original = $org->slug;

    $org->update(['description' => 'a new description']);

    expect($org->fresh()->slug)->toBe($original);
});

// ----- allUsers() -----

test('allUsers merges pivot members with the owner', function () {
    $org = Organizer::factory()->create(['user_id' => $this->owner->id]);
    $member = User::factory()->create();
    $org->users()->attach($member->id, ['role' => 'member']);

    $org->refresh();
    $all = $org->allUsers();

    expect($all->pluck('id')->sort()->values()->all())
        ->toContain($this->owner->id, $member->id);
});

test('allUsers does not duplicate the owner when the owner is also a pivot member', function () {
    // OrganizerFactory already attaches the owner to the pivot with role owner
    $org = Organizer::factory()->create(['user_id' => $this->owner->id]);

    $org->refresh();
    $all = $org->allUsers();

    // owner appears exactly once despite being in both the pivot and as owner()
    expect($all->where('id', $this->owner->id))->toHaveCount(1);
});

// ----- getHandles() -----

test('getHandles returns an empty array when no social handles are set', function () {
    $org = Organizer::factory()->create([
        'instagramHandle' => null,
        'facebookHandle' => null,
        'twitterHandle' => null,
    ]);

    expect($org->getHandles())->toBe([]);
});

test('getHandles builds full URLs for each handle in instagram facebook twitter order', function () {
    $org = Organizer::factory()->create([
        'instagramHandle' => 'insta_user',
        'facebookHandle' => 'fb_user',
        'twitterHandle' => 'tw_user',
    ]);

    expect($org->getHandles())->toBe([
        'https://www.instagram.com/insta_user',
        'https://www.facebook.com/fb_user',
        'https://www.twitter.com/tw_user',
    ]);
});

test('getHandles only includes the handles that are present', function () {
    $org = Organizer::factory()->create([
        'instagramHandle' => null,
        'facebookHandle' => 'just_fb',
        'twitterHandle' => null,
    ]);

    expect($org->getHandles())->toBe([
        'https://www.facebook.com/just_fb',
    ]);
});

// ----- deleteOrganizer() -----

test('deleteOrganizer soft-deletes its events detaches users and removes the organizer', function () {
    $org = Organizer::factory()->create(['user_id' => $this->owner->id]);
    $member = User::factory()->create();
    $org->users()->attach($member->id, ['role' => 'member']);

    $event = Event::factory()->create(['organizer_id' => $org->id]);

    $org->deleteOrganizer($org);

    // organizer gone
    expect(Organizer::find($org->id))->toBeNull();
    // events soft-deleted (Event uses SoftDeletes)
    expect(Event::find($event->id))->toBeNull();
    expect(Event::withTrashed()->find($event->id))->not->toBeNull();
    // pivot rows detached
    expect(\Illuminate\Support\Facades\DB::table('organizer_user')->where('organizer_id', $org->id)->count())->toBe(0);
});

// ----- scopeWithUserRole() -----

test('withUserRole returns the query unchanged for an unauthenticated user', function () {
    $org = Organizer::factory()->create();

    $result = Organizer::withUserRole()->find($org->id);

    // no user_role column is added when nobody is logged in
    expect($result->user_role ?? null)->toBeNull();
});

test('withUserRole labels the owner as owner', function () {
    $org = Organizer::factory()->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner);
    $result = Organizer::withUserRole()->find($org->id);

    expect($result->user_role)->toBe('owner');
});

test('withUserRole labels an admin as admin and a moderator as moderator', function () {
    $org = Organizer::factory()->create();

    $admin = User::factory()->create(['type' => 'a']);
    $this->actingAs($admin);
    expect(Organizer::withUserRole()->find($org->id)->user_role)->toBe('admin');

    $mod = User::factory()->create(['type' => 'm']);
    $this->actingAs($mod);
    expect(Organizer::withUserRole()->find($org->id)->user_role)->toBe('moderator');
});

test('withUserRole returns the pivot role for a plain member', function () {
    $org = Organizer::factory()->create();
    $member = User::factory()->create(['type' => 'u']);
    $org->users()->attach($member->id, ['role' => 'editor']);

    $this->actingAs($member);
    $result = Organizer::withUserRole()->find($org->id);

    expect($result->user_role)->toBe('editor');
});

test('withUserRole returns a null role for a logged-in user with no relationship', function () {
    $org = Organizer::factory()->create();
    $stranger = User::factory()->create(['type' => 'u']);

    $this->actingAs($stranger);
    $result = Organizer::withUserRole()->find($org->id);

    expect($result->user_role)->toBeNull();
});

// ----- scopeWithPaginatedEvents() -----

test('withPaginatedEvents eager-loads only published non-archived events with their relations', function () {
    $org = Organizer::factory()->create();

    $published = Event::factory()->published()->create(['organizer_id' => $org->id, 'archived' => false]);
    Event::factory()->draft()->create(['organizer_id' => $org->id]); // excluded (status)
    Event::factory()->published()->create(['organizer_id' => $org->id, 'archived' => true]); // excluded (archived)

    $result = Organizer::withPaginatedEvents()->find($org->id);

    expect($result->relationLoaded('events'))->toBeTrue();
    expect($result->events->pluck('id')->all())->toBe([$published->id]);
    // relations declared in the scope are eager-loaded on each event
    expect($result->events->first()->relationLoaded('category'))->toBeTrue();
    expect($result->events->first()->relationLoaded('genres'))->toBeTrue();
    expect($result->events->first()->relationLoaded('currentUserFavorite'))->toBeTrue();
});
