<?php

use App\Models\Venue;
use App\Support\PhotoGallery;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('digitalocean');
    $this->venue = Venue::factory()->create(['name' => 'The Hall']);
    foreach ([0, 1, 2] as $rank) {
        $this->venue->images()->create(['large_image_path' => "venue-images/the-hall/p{$rank}.webp", 'thumb_image_path' => "venue-images/the-hall/p{$rank}-thumb.webp", 'rank' => $rank]);
    }
    $this->venue->forceFill(['largeImagePath' => 'venue-images/the-hall/p0.webp', 'thumbImagePath' => 'venue-images/the-hall/p0-thumb.webp'])->save();
});

function gallerySave(Venue $venue, array $uploads, array $ranks, array $deleted = [], string $kept = '[]'): void
{
    $request = Request::create('/', 'POST', ['ranks' => $ranks, 'deletedImages' => json_encode($deleted), 'currentImages' => $kept], [], ['images' => $uploads]);
    PhotoGallery::sync($venue, $request, 'venue-images', fn () => [1200, 800], 4);
}

function snapshot(Venue $venue): array
{
    return $venue->images()->orderBy('rank')->get(['large_image_path', 'rank'])->map(fn ($image) => [$image->large_image_path, $image->rank])->all();
}

test('a failed upload, first or later, leaves the photos exactly as they were, and the save can be retried', function () {
    $before = snapshot($this->venue);
    $good = fn () => UploadedFile::fake()->image('new.jpg', 1200, 800);
    $bad = fn () => UploadedFile::fake()->create('notes.txt', 1, 'text/plain');

    // The second of two fails, after the first went up: its files are taken down again too.
    expect(fn () => gallerySave($this->venue, [$good(), $bad()], [0, 3], ['venue-images/the-hall/p2.webp']))->toThrow(Exception::class);
    expect(snapshot($this->venue))->toBe($before)
        ->and($this->venue->fresh()->largeImagePath)->toBe('venue-images/the-hall/p0.webp')
        ->and(Storage::disk('digitalocean')->allFiles())->toBe([]);

    // The first one fails.
    expect(fn () => gallerySave($this->venue, [$bad()], [0]))->toThrow(Exception::class);
    expect(snapshot($this->venue))->toBe($before);

    // The same save, with good files, goes through: p2 out, two new in, four in all.
    gallerySave($this->venue, [$good(), $good()], [0, 3], ['venue-images/the-hall/p2.webp']);

    $after = $this->venue->images()->orderBy('rank')->get();
    expect($after)->toHaveCount(4)
        ->and($after->pluck('rank')->all())->toBe([0, 1, 2, 3])
        ->and($after->pluck('large_image_path')->contains('venue-images/the-hall/p2.webp'))->toBeFalse()
        ->and($after->first()->large_image_path)->not->toStartWith('venue-images/the-hall/p') // the new one leads
        ->and($this->venue->fresh()->largeImagePath)->toBe($after->first()->large_image_path);
});

test('changes that cannot be read are refused before anything is uploaded', function () {
    $before = snapshot($this->venue);
    $good = fn () => UploadedFile::fake()->image('new.jpg', 1200, 800);

    foreach (['[{"id": 1, "rank": -1}]', '[{"id": "x", "rank": 0}]', '[{"id": 1, "rank": 9}]', '"nope"'] as $kept) {
        expect(fn () => gallerySave($this->venue, [$good()], [0], [], $kept))->toThrow(Illuminate\Validation\ValidationException::class);
    }

    expect(snapshot($this->venue))->toBe($before)
        ->and(Storage::disk('digitalocean')->allFiles())->toBe([]);
});

test('kept photos take the order the page sends, and a removed one is gone with its files', function () {
    Storage::disk('digitalocean')->put('/public/venue-images/the-hall/p1.webp', 'x');
    $ids = $this->venue->images()->orderBy('rank')->pluck('id');

    // p2 first, then p0; p1 removed.
    gallerySave($this->venue, [], [], ['venue-images/the-hall/p1.webp'], json_encode([['id' => $ids[2], 'rank' => 0], ['id' => $ids[0], 'rank' => 1]]));

    expect(snapshot($this->venue))->toBe([['venue-images/the-hall/p2.webp', 0], ['venue-images/the-hall/p0.webp', 1]])
        ->and($this->venue->fresh()->largeImagePath)->toBe('venue-images/the-hall/p2.webp');
    Storage::disk('digitalocean')->assertMissing('/public/venue-images/the-hall/p1.webp');
});

test('a save working from an out-of-date copy of the page still leaves its main picture right', function () {
    // This request loaded the venue earlier; since then another save changed its main picture.
    $stale = Venue::find($this->venue->id);
    Illuminate\Support\Facades\DB::table('venues')->where('id', $this->venue->id)->update(['largeImagePath' => 'venue-images/the-hall/elsewhere.webp']);

    $ids = $this->venue->images()->orderBy('rank')->pluck('id');
    gallerySave($stale, [], [], ['venue-images/the-hall/p1.webp'], json_encode([['id' => $ids[0], 'rank' => 0], ['id' => $ids[2], 'rank' => 1]]));

    expect($this->venue->fresh()->largeImagePath)->toBe('venue-images/the-hall/p0.webp')
        ->and($stale->largeImagePath)->toBe('venue-images/the-hall/p0.webp');
});
