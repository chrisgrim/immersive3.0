<?php

use App\Models\AttendanceType;
use App\Models\Category;
use App\Models\Event;
use App\Models\Genre;
use App\Models\User;

// ===================================================================
// Category::getHasEventAttribute()
// ===================================================================

test('hasEvent returns false when the category has no events', function () {
    $category = Category::factory()->create();

    expect($category->hasEvent)->toBeFalse();
});

test('hasEvent runs a count query and returns true when events exist', function () {
    $category = Category::factory()->create();
    Event::factory()->create(['category_id' => $category->id]);

    // No withCount() here, so the accessor falls back to events()->count().
    expect($category->hasEvent)->toBeTrue();
});

test('hasEvent prefers an eager-loaded events_count over a count query', function () {
    // Create a category with one real event, but force events_count to 0 via
    // withCount. The accessor should trust the eager-loaded attribute (0 -> false)
    // rather than re-running events()->count().
    $category = Category::factory()->create();
    Event::factory()->create(['category_id' => $category->id]);

    // Re-fetch with withCount: events_count will be 1.
    $withCount = Category::withCount('events')->find($category->id);
    expect($withCount->getAttributes()['events_count'])->toBe(1);
    expect($withCount->hasEvent)->toBeTrue();
});

test('hasEvent reads events_count of zero as false even when an event exists', function () {
    // note: getHasEventAttribute prefers $this->attributes['events_count'] when
    // present. We inject a stale 0 to prove it short-circuits the count() query.
    $category = Category::factory()->create();
    Event::factory()->create(['category_id' => $category->id]);

    $category->setAttribute('events_count', 0);

    expect($category->hasEvent)->toBeFalse();
});

// ===================================================================
// Category::supportsAttendanceType()
// ===================================================================

test('supportsAttendanceType returns true for any id when applicable types is null', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => null]);

    expect($category->supportsAttendanceType(1))->toBeTrue();
    expect($category->supportsAttendanceType(2))->toBeTrue();
    expect($category->supportsAttendanceType(999))->toBeTrue();
});

test('supportsAttendanceType returns true for any id when applicable types is empty array', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => []]);

    expect($category->supportsAttendanceType(1))->toBeTrue();
    expect($category->supportsAttendanceType(2))->toBeTrue();
});

test('supportsAttendanceType filters by the specific ids when set', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => [1]]);

    expect($category->supportsAttendanceType(1))->toBeTrue();
    expect($category->supportsAttendanceType(2))->toBeFalse();
});

test('supportsAttendanceType supports multiple configured ids', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => [1, 2]]);

    expect($category->supportsAttendanceType(1))->toBeTrue();
    expect($category->supportsAttendanceType(2))->toBeTrue();
    expect($category->supportsAttendanceType(3))->toBeFalse();
});

test('the supportsAttendanceType appended accessor returns a callable closure', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => [1]]);

    $closure = $category->supportsAttendanceType;

    expect($closure)->toBeCallable();
    expect($closure(1))->toBeTrue();
    expect($closure(2))->toBeFalse();
});

// ===================================================================
// Category::attendanceTypes()
// ===================================================================

test('attendanceTypes returns all attendance types when none are configured', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => null]);

    // The attendance_types table is seeded by migration with In Person (1) + Remote (2).
    expect($category->attendanceTypes()->pluck('id')->sort()->values()->all())
        ->toBe(AttendanceType::all()->pluck('id')->sort()->values()->all());
});

test('attendanceTypes returns only the configured ids when set', function () {
    $category = Category::factory()->create(['applicable_attendance_types' => [1]]);

    $types = $category->attendanceTypes();

    expect($types)->toHaveCount(1);
    expect($types->first()->id)->toBe(1);
});

// ===================================================================
// Genre::saveGenres()
// ===================================================================

test('saveGenres creates new genres and syncs them onto the event', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    Genre::saveGenres($event, ['Horror', 'Comedy']);

    expect(Genre::where('slug', 'horror')->exists())->toBeTrue();
    expect(Genre::where('slug', 'comedy')->exists())->toBeTrue();
    expect($event->genres()->pluck('slug')->sort()->values()->all())->toBe(['comedy', 'horror']);
});

test('saveGenres normalizes case so duplicates collapse to one genre', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    // "HORROR" and "horror" normalize to slug "horror" -> a single genre.
    Genre::saveGenres($event, ['HORROR', 'horror']);

    expect(Genre::where('slug', 'horror')->count())->toBe(1);
    // note: the created genre's name is normalized to ucfirst(strtolower()) => "Horror".
    expect(Genre::where('slug', 'horror')->first()->name)->toBe('Horror');
    expect($event->genres()->count())->toBe(1);
});

test('saveGenres reuses an existing genre rather than creating a duplicate', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    $existing = Genre::factory()->create(['name' => 'Horror', 'slug' => 'horror']);

    Genre::saveGenres($event, ['Horror']);

    expect(Genre::where('slug', 'horror')->count())->toBe(1);
    expect($event->genres()->first()->id)->toBe($existing->id);
});

test('saveGenres removes genres that are no longer present on a re-sync', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    Genre::saveGenres($event, ['Horror', 'Comedy']);
    expect($event->genres()->count())->toBe(2);

    // Re-sync with only one of the originals: sync detaches the missing one.
    Genre::saveGenres($event, ['Horror']);

    expect($event->genres()->pluck('slug')->all())->toBe(['horror']);
    // Both genre rows still exist; only the pivot link to Comedy is removed.
    expect(Genre::where('slug', 'comedy')->exists())->toBeTrue();
});

test('saveGenres accepts array entries with a name key', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    Genre::saveGenres($event, [['name' => 'Thriller'], ['name' => 'Drama']]);

    expect($event->genres()->pluck('slug')->sort()->values()->all())->toBe(['drama', 'thriller']);
});

test('saveGenres stamps the acting user id on newly created genres', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $event = Event::factory()->create();

    Genre::saveGenres($event, ['Mystery']);

    expect(Genre::where('slug', 'mystery')->first()->user_id)->toBe($user->id);
});
