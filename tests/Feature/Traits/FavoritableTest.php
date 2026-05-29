<?php

use App\Models\Components\Favorite;
use App\Models\Event;
use App\Models\User;

// note: Favoritable is currently applied to ONLY the Event model (grep of
// app/Models confirms no other model `use`s the trait). All assertions below
// exercise it through Event.

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ----- favorite() -----

test('favorite creates a Favorite row for the auth user', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();

    expect(Favorite::count())->toBe(1);
    $favorite = Favorite::first();
    expect($favorite->user_id)->toBe($this->user->id);
    expect($favorite->favorited_id)->toBe($event->id);
    expect($favorite->favorited_type)->toBe(Event::class);
});

test('favorite is idempotent — calling twice creates only one Favorite', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();
    $event->favorite();

    expect($event->favorites()->where('user_id', $this->user->id)->count())->toBe(1);
    expect(Favorite::count())->toBe(1);
});

test('favorite returns null when the user already favorited the model', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $first = $event->favorite();
    $second = $event->favorite();

    expect($first)->toBeInstanceOf(Favorite::class);
    // note: favorite() has no explicit return on the "already exists" branch,
    // so the second call returns null rather than the existing Favorite.
    expect($second)->toBeNull();
});

test('different users each get their own Favorite for the same event', function () {
    $event = Event::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();

    $this->actingAs($other);
    $event->favorite();

    expect(Favorite::count())->toBe(2);
});

// ----- unfavorite() -----

test('unfavorite removes the auth user favorite', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();
    expect(Favorite::count())->toBe(1);

    $event->unfavorite();

    expect(Favorite::count())->toBe(0);
});

test('unfavorite only removes the auth user favorite, leaving others intact', function () {
    $event = Event::factory()->create();
    $other = User::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);
    Favorite::factory()->create([
        'user_id' => $other->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);

    $this->actingAs($this->user);
    $event->unfavorite();

    expect(Favorite::count())->toBe(1);
    expect(Favorite::first()->user_id)->toBe($other->id);
});

test('unfavorite is a no-op when the user has not favorited', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->unfavorite();

    expect(Favorite::count())->toBe(0);
});

// ----- isFavorited() -----

test('isFavorited is true for the favoriting auth user', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();

    expect($event->fresh()->isFavorited())->toBeTrue();
});

test('isFavorited is false for a different auth user', function () {
    $event = Event::factory()->create();
    $other = User::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);

    $this->actingAs($other);

    expect($event->fresh()->isFavorited())->toBeFalse();
});

test('isFavorited is false when unauthenticated', function () {
    $event = Event::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);

    // No actingAs — guest. The early `! $userId` guard returns false.
    expect($event->fresh()->isFavorited())->toBeFalse();
});

// ----- getIsFavoritedAttribute -----

test('is_favorited attribute mirrors isFavorited for the auth user', function () {
    $event = Event::factory()->create();

    $this->actingAs($this->user);
    $event->favorite();

    expect($event->fresh()->is_favorited)->toBeTrue();
});

test('is_favorited attribute is false when unauthenticated', function () {
    $event = Event::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);

    expect($event->fresh()->is_favorited)->toBeFalse();
});

// ----- getFavoritesCountAttribute -----

test('favorites_count counts from the loaded favorites relation', function () {
    $event = Event::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    foreach ([$this->user, $userA, $userB] as $u) {
        Favorite::factory()->create([
            'user_id' => $u->id,
            'favorited_id' => $event->id,
            'favorited_type' => Event::class,
        ]);
    }

    expect($event->fresh()->favorites_count)->toBe(3);
});

test('favorites_count is zero when there are no favorites', function () {
    $event = Event::factory()->create();

    expect($event->fresh()->favorites_count)->toBe(0);
});

// ----- bootFavoritable cascade delete -----

test('deleting the model deletes its favorites', function () {
    $event = Event::factory()->create();
    $userA = User::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);
    Favorite::factory()->create([
        'user_id' => $userA->id,
        'favorited_id' => $event->id,
        'favorited_type' => Event::class,
    ]);

    expect(Favorite::count())->toBe(2);

    // note: Event uses SoftDeletes; the `deleting` event still fires on a soft
    // delete, so bootFavoritable cascades and removes the favorites even though
    // the event row itself is only soft-deleted.
    $event->delete();

    expect(Favorite::count())->toBe(0);
});

test('deleting a model does not delete another models favorites', function () {
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();

    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $eventA->id,
        'favorited_type' => Event::class,
    ]);
    Favorite::factory()->create([
        'user_id' => $this->user->id,
        'favorited_id' => $eventB->id,
        'favorited_type' => Event::class,
    ]);

    $eventA->delete();

    expect(Favorite::count())->toBe(1);
    expect(Favorite::first()->favorited_id)->toBe($eventB->id);
});
