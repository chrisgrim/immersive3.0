<?php

use App\Models\Event;
use Illuminate\Support\Facades\DB;

// A draft "New event" made and nobody touched again.
function blankDraft(int $daysOld): Event
{
    $event = Event::factory()->create(['name' => null, 'status' => '0', 'description' => null]);
    $event->location()->create([]);
    $event->advisories()->create([]);
    DB::table('events')->where('id', $event->id)->update(['updated_at' => now()->subDays($daysOld)]);

    return $event;
}

test('a blank draft untouched for more than three days is removed with its rows', function () {
    $event = blankDraft(4);

    $this->artisan('ei:prune-blank-drafts')->assertSuccessful();

    expect(Event::withTrashed()->find($event->id))->toBeNull()
        ->and(DB::table('locations')->where('event_id', $event->id)->exists())->toBeFalse()
        ->and(DB::table('advisories')->where('event_id', $event->id)->exists())->toBeFalse();
});

test('a blank draft from the last few days stays', function () {
    $event = blankDraft(2);

    $this->artisan('ei:prune-blank-drafts')->assertSuccessful();

    expect(Event::find($event->id))->not->toBeNull();
});

test('a draft with a name, a picture or a later status stays', function () {
    $named = blankDraft(10);
    $named->forceFill(['name' => 'Something'])->saveQuietly();
    DB::table('events')->where('id', $named->id)->update(['updated_at' => now()->subDays(10)]);

    $pictured = blankDraft(10);
    $pictured->images()->create(['large_image_path' => 'x.webp', 'thumb_image_path' => 'x-thumb.webp', 'rank' => 0]);
    DB::table('events')->where('id', $pictured->id)->update(['updated_at' => now()->subDays(10)]);

    $submitted = blankDraft(10);
    DB::table('events')->where('id', $submitted->id)->update(['status' => 'r']);

    $this->artisan('ei:prune-blank-drafts')->assertSuccessful();

    expect(Event::whereKey([$named->id, $pictured->id, $submitted->id])->count())->toBe(3);
});

test('a dry run deletes nothing', function () {
    $event = blankDraft(10);

    $this->artisan('ei:prune-blank-drafts', ['--dry-run' => true])->expectsOutputToContain("1 blank drafts would be removed: {$event->id}")->assertSuccessful();

    expect(Event::find($event->id))->not->toBeNull();
});

test('anything typed in keeps a draft: tagline, description, category or an old-style picture', function () {
    $categorized = blankDraft(10);
    DB::table('events')->where('id', $categorized->id)->update(['category_id' => App\Models\Category::factory()->create()->id]);
    $oldPicture = blankDraft(10);
    DB::table('events')->where('id', $oldPicture->id)->update(['largeImagePath' => 'event-images/old/old.webp', 'thumbImagePath' => 'event-images/old/old-thumb.webp']);
    $tagged = blankDraft(10);
    DB::table('events')->where('id', $tagged->id)->update(['tag_line' => 'Soon']);
    $described = blankDraft(10);
    DB::table('events')->where('id', $described->id)->update(['description' => 'Something']);
    $held = blankDraft(10);
    DB::table('events')->where('id', $held->id)->update(['held_for_staff' => true]);

    $this->artisan('ei:prune-blank-drafts')->assertSuccessful();

    expect(Event::withTrashed()->whereKey([$categorized->id, $oldPicture->id, $tagged->id, $described->id, $held->id])->count())->toBe(5);
});

test('it refuses a too-short or nonsense --days and deletes nothing', function () {
    $event = blankDraft(10);

    $this->artisan('ei:prune-blank-drafts', ['--days' => 0])->assertFailed();
    $this->artisan('ei:prune-blank-drafts', ['--days' => 'abc'])->assertFailed();
    $this->artisan('ei:prune-blank-drafts', ['--days' => 2])->assertFailed();

    expect(Event::find($event->id))->not->toBeNull();
});

test('it removes at most --limit a run', function () {
    $first = blankDraft(10);
    $second = blankDraft(10);

    $this->artisan('ei:prune-blank-drafts', ['--limit' => 1])->assertSuccessful();

    expect(Event::withTrashed()->whereKey([$first->id, $second->id])->count())->toBe(1);
});

test('a venue draft with only a name goes after three untouched days; anything more keeps it', function () {
    $old = fn (array $attributes = []) => tap(App\Models\Venue::factory()->create(['status' => 'draft', 'street' => null, 'city' => null, 'latitude' => null, 'longitude' => null, 'description' => null, 'directions' => null, ...$attributes]), fn ($venue) => DB::table('venues')->where('id', $venue->id)->update(['updated_at' => now()->subDays(5)]));

    $nameOnly = $old();
    $withAddress = $old(['street' => '1 Main St', 'city' => 'Chicago', 'latitude' => 41.8, 'longitude' => -87.6]);
    $withWords = $old(['description' => 'A room.']);
    $withRoom = $old(['room' => 'Studio 2']);
    $sent = $old(['status' => 'pending']);
    $fresh = App\Models\Venue::factory()->create(['status' => 'draft', 'street' => null, 'city' => null, 'latitude' => null, 'longitude' => null]);

    $this->artisan('ei:prune-blank-drafts')->expectsOutputToContain('1 blank venue drafts removed')->assertSuccessful();

    expect(App\Models\Venue::find($nameOnly->id))->toBeNull()
        ->and(App\Models\Venue::whereKey([$withAddress->id, $withWords->id, $withRoom->id, $sent->id, $fresh->id])->count())->toBe(5);
});
