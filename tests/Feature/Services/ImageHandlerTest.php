<?php

use App\Models\Event;
use App\Models\Image;
use App\Services\ImageHandler;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// note: ImageHandler writes to the 'digitalocean' disk under a leading
// "/public/" segment. Storage::fake normalizes the leading slash, so the
// on-disk path is "public/<type>/<slug>/...". We assert path SHAPES (with
// regex on the uniqid filename) and Storage existence, never exact filenames.

beforeEach(function () {
    Storage::fake('digitalocean');
});

// ----- saveImage() -----

test('saveImage writes large and thumb webp and jpg variants', function () {
    $event = Event::factory()->create(['slug' => 'my-show']);
    $file = UploadedFile::fake()->image('upload.jpg', 800, 600);

    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);

    // The stored large_image_path shape: event-images/my-show/my-show-<uniqid>.webp
    expect($image->large_image_path)->toMatch('#^event-images/my-show/my-show-[0-9a-f]+\.webp$#');
    expect($image->thumb_image_path)->toMatch('#^event-images/my-show/my-show-[0-9a-f]+-thumb\.webp$#');

    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    $thumbBase = preg_replace('/\.webp$/', '', $image->thumb_image_path);

    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$base}.jpg");
    Storage::disk('digitalocean')->assertExists("public/{$thumbBase}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$thumbBase}.jpg");
});

test('saveImage sets the model image columns when rank is 0', function () {
    $event = Event::factory()->create(['slug' => 'cover-show', 'largeImagePath' => null, 'thumbImagePath' => null]);
    $file = UploadedFile::fake()->image('upload.jpg', 800, 600);

    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);

    $event->refresh();
    expect($event->largeImagePath)->toBe($image->large_image_path);
    expect($event->thumbImagePath)->toBe($image->thumb_image_path);
});

test('saveImage does not touch model image columns when rank is greater than 0', function () {
    $event = Event::factory()->create(['slug' => 'extra-show', 'largeImagePath' => null, 'thumbImagePath' => null]);
    $file = UploadedFile::fake()->image('upload.jpg', 800, 600);

    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 2);

    expect($image->rank)->toBe(2);
    $event->refresh();
    expect($event->largeImagePath)->toBeNull();
    expect($event->thumbImagePath)->toBeNull();
});

test('saveImage creates the polymorphic image row attached to the model', function () {
    $event = Event::factory()->create(['slug' => 'row-show']);
    $file = UploadedFile::fake()->image('upload.jpg', 800, 600);

    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);

    expect($image->imageable_id)->toBe($event->id);
    expect($image->imageable_type)->toBe(Event::class);
    expect($event->images()->count())->toBe(1);
});

test('saveImage rejects a non-image upload via mime validation', function () {
    $event = Event::factory()->create(['slug' => 'bad-show']);
    $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

    expect(fn () => ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0))
        ->toThrow(\Exception::class, 'The file is not an image.');

    expect($event->images()->count())->toBe(0);
});

// ----- deleteImage() -----

test('deleteImage removes all four variants and clears the model columns for rank 0', function () {
    $event = Event::factory()->create(['slug' => 'del-show']);
    $file = UploadedFile::fake()->image('upload.jpg', 800, 600);
    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);

    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    $thumbBase = preg_replace('/\.webp$/', '', $image->thumb_image_path);

    ImageHandler::deleteImage($image);

    Storage::disk('digitalocean')->assertMissing("public/{$base}.webp");
    Storage::disk('digitalocean')->assertMissing("public/{$base}.jpg");
    Storage::disk('digitalocean')->assertMissing("public/{$thumbBase}.webp");
    Storage::disk('digitalocean')->assertMissing("public/{$thumbBase}.jpg");

    expect(Image::find($image->id))->toBeNull();

    $event->refresh();
    expect($event->largeImagePath)->toBeNull();
    expect($event->thumbImagePath)->toBeNull();
});

test('deleteImage keeps the model columns when the image is not rank 0', function () {
    $event = Event::factory()->create(['slug' => 'keep-show', 'largeImagePath' => 'kept/path.webp', 'thumbImagePath' => 'kept/path-thumb.webp']);
    // Build a rank-2 image directly so the columns are independent of it.
    $image = Image::factory()->for($event, 'imageable')->create([
        'large_image_path' => 'event-images/keep-show/keep-show-abc123.webp',
        'thumb_image_path' => 'event-images/keep-show/keep-show-abc123-thumb.webp',
        'rank' => 2,
    ]);

    ImageHandler::deleteImage($image);

    expect(Image::find($image->id))->toBeNull();
    $event->refresh();
    // Columns untouched because rank !== 0.
    expect($event->largeImagePath)->toBe('kept/path.webp');
    expect($event->thumbImagePath)->toBe('kept/path-thumb.webp');
});

test('deleteImage refuses a path missing the -images type segment (traversal guard)', function () {
    $event = Event::factory()->create(['slug' => 'guard-show']);
    // A path whose first segment is not "<type>-images" must be refused.
    $image = Image::factory()->for($event, 'imageable')->create([
        'large_image_path' => '../../etc/passwd.webp',
        'thumb_image_path' => '../../etc/passwd-thumb.webp',
        'rank' => 0,
    ]);

    expect(fn () => ImageHandler::deleteImage($image))
        ->toThrow(\Exception::class, 'Invalid image path structure');

    // The row is still present because the guard threw before deletion.
    expect(Image::find($image->id))->not->toBeNull();
});

test('deleteImage refuses a foreign two-segment path', function () {
    $event = Event::factory()->create(['slug' => 'guard2-show']);
    // Only two segments => count($pathParts) < 3 => rejected.
    $image = Image::factory()->for($event, 'imageable')->create([
        'large_image_path' => 'storage/file.webp',
        'thumb_image_path' => 'storage/file-thumb.webp',
        'rank' => 0,
    ]);

    expect(fn () => ImageHandler::deleteImage($image))
        ->toThrow(\Exception::class, 'Invalid image path structure');
});

// ----- updateImages() -----

test('updateImages deletes images no longer present and updates ranks of the rest', function () {
    $event = Event::factory()->create(['slug' => 'upd-show']);
    $fileA = UploadedFile::fake()->image('a.jpg', 800, 600);
    $fileB = UploadedFile::fake()->image('b.jpg', 800, 600);
    $imageA = ImageHandler::saveImage($fileA, $event, 800, 600, 'event-images', 0);
    $imageB = ImageHandler::saveImage($fileB, $event, 800, 600, 'event-images', 1);

    $event->load('images');

    // Keep only A but bump it to rank 5; B is missing from the payload => delete.
    ImageHandler::updateImages($event, [
        ['url' => $imageA->large_image_path, 'rank' => 5],
    ]);

    expect(Image::find($imageB->id))->toBeNull();
    expect(Image::find($imageA->id)->rank)->toBe(5);

    // B's variants were physically deleted.
    $bBase = preg_replace('/\.webp$/', '', $imageB->large_image_path);
    Storage::disk('digitalocean')->assertMissing("public/{$bBase}.webp");
});

test('updateImages is a no-op when given an empty current set', function () {
    $event = Event::factory()->create(['slug' => 'noop-show']);
    $file = UploadedFile::fake()->image('a.jpg', 800, 600);
    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);

    // note: a falsy ($currentImages == null/empty array) short-circuits the
    // whole method, so nothing is deleted even though the payload is empty.
    ImageHandler::updateImages($event, []);

    expect(Image::find($image->id))->not->toBeNull();
});

// ----- finalize() -----

test('finalize copies variants into the slug-final directory and updates paths', function () {
    $event = Event::factory()->create(['slug' => 'fin-show']);
    $file = UploadedFile::fake()->image('a.jpg', 800, 600);
    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);
    $originalDir = dirname($image->large_image_path);

    $event->load('images');
    ImageHandler::finalize($event, 'fin-show', 'event');

    $image->refresh();
    expect($image->large_image_path)->toMatch('#^event-images/fin-show-final/fin-show-[0-9a-f]+\.webp$#');
    expect($image->thumb_image_path)->toMatch('#^event-images/fin-show-final/fin-show-[0-9a-f]+-thumb\.webp$#');

    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$base}.jpg");

    // Model columns updated for rank 0.
    $event->refresh();
    expect($event->largeImagePath)->toBe($image->large_image_path);

    // Original directory cleaned up.
    Storage::disk('digitalocean')->assertMissing("public/{$originalDir}");
});

// ----- duplicateImages() -----

test('duplicateImages copies all variants and creates new image rows on the target', function () {
    $source = Event::factory()->create(['slug' => 'src-show']);
    $file = UploadedFile::fake()->image('a.jpg', 800, 600);
    $sourceImage = ImageHandler::saveImage($file, $source, 800, 600, 'event-images', 0);

    $target = Event::factory()->create(['slug' => 'tgt-show', 'largeImagePath' => null, 'thumbImagePath' => null]);
    $source->load('images');

    ImageHandler::duplicateImages($source, $target, 'event');

    expect($target->images()->count())->toBe(1);
    $newImage = $target->images()->first();
    expect($newImage->large_image_path)->toMatch('#^event-images/tgt-show/tgt-show-[0-9a-f]+\.webp$#');

    $base = preg_replace('/\.webp$/', '', $newImage->large_image_path);
    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$base}.jpg");

    // Source still intact.
    expect(Image::find($sourceImage->id))->not->toBeNull();
    Storage::disk('digitalocean')->assertExists("public/{$sourceImage->large_image_path}");

    // Target rank 0 columns populated.
    $target->refresh();
    expect($target->largeImagePath)->toBe($newImage->large_image_path);
});

test('duplicateImages skips an image whose files are missing instead of saving a broken one', function () {
    $source = Event::factory()->create(['slug' => 'ghost-show']);
    // Image row exists in DB but the underlying files were never written to disk.
    Image::factory()->for($source, 'imageable')->create([
        'large_image_path' => 'event-images/ghost-show/ghost-show-deadbeef.webp',
        'thumb_image_path' => 'event-images/ghost-show/ghost-show-deadbeef-thumb.webp',
        'rank' => 0,
    ]);
    // Event::duplicate starts the copy with the original's picture columns.
    $target = Event::factory()->create(['slug' => 'ghost-target', 'largeImagePath' => 'event-images/ghost-show/ghost-show-deadbeef.webp', 'thumbImagePath' => 'event-images/ghost-show/ghost-show-deadbeef-thumb.webp']);
    $source->load('images');

    ImageHandler::duplicateImages($source, $target, 'event');

    expect($target->images()->count())->toBe(0);
    expect($target->refresh()->largeImagePath)->toBeNull();
});

test('duplicateImages saves nothing and cleans up when a copy quietly fails', function () {
    $source = Event::factory()->create(['slug' => 'flaky-src']);
    ImageHandler::saveImage(UploadedFile::fake()->image('a.jpg', 800, 600), $source, 800, 600, 'event-images', 0);
    $source->load('images');
    $target = Event::factory()->create(['slug' => 'flaky-tgt', 'largeImagePath' => $source->largeImagePath, 'thumbImagePath' => $source->thumbImagePath]);

    // The large WebP's copy reports false (as some S3 setups do) instead of throwing.
    $real = Storage::disk('digitalocean');
    $disk = Mockery::mock($real)->makePartial();
    $disk->shouldReceive('copy')->andReturnUsing(
        fn ($from, $to) => str_ends_with($to, '-thumb.webp') || str_ends_with($to, '.jpg') ? $real->copy($from, $to) : false
    );
    Storage::set('digitalocean', $disk);

    ImageHandler::duplicateImages($source, $target, 'event');

    expect($target->images()->count())->toBe(0);
    expect($target->refresh()->largeImagePath)->toBeNull();
    // The copies that did land were removed again.
    expect($real->allFiles('public/event-images/flaky-tgt'))->toBe([]);
});

test('duplicateImages returns early when the source has no images', function () {
    $source = Event::factory()->create(['slug' => 'empty-src']);
    $target = Event::factory()->create(['slug' => 'empty-tgt']);

    ImageHandler::duplicateImages($source, $target, 'event');

    expect($target->images()->count())->toBe(0);
});

// ----- moveImagesForNewSlug() -----

test('moveImagesForNewSlug rewrites paths to the new slug and removes the old directory', function () {
    $event = Event::factory()->create(['slug' => 'old-slug']);
    $file = UploadedFile::fake()->image('a.jpg', 800, 600);
    $image = ImageHandler::saveImage($file, $event, 800, 600, 'event-images', 0);
    $oldDir = dirname($image->large_image_path); // event-images/old-slug

    $event->load('images');
    ImageHandler::moveImagesForNewSlug($event, 'old-slug', 'new-slug', 'event');

    $image->refresh();
    expect($image->large_image_path)->toMatch('#^event-images/new-slug/new-slug-[0-9a-f]+\.webp$#');
    expect($image->thumb_image_path)->toMatch('#^event-images/new-slug/new-slug-[0-9a-f]+-thumb\.webp$#');

    $base = preg_replace('/\.webp$/', '', $image->large_image_path);
    Storage::disk('digitalocean')->assertExists("public/{$base}.webp");
    Storage::disk('digitalocean')->assertExists("public/{$base}.jpg");

    // Old directory cleaned up.
    Storage::disk('digitalocean')->assertMissing("public/{$oldDir}");

    // Model rank-0 columns rewritten to the new slug path.
    $event->refresh();
    expect($event->largeImagePath)->toBe($image->large_image_path);
});

test('moveImagesForNewSlug is a no-op when the model has no images', function () {
    $event = Event::factory()->create(['slug' => 'no-images']);

    // Should simply do nothing and not throw.
    ImageHandler::moveImagesForNewSlug($event, 'no-images', 'renamed', 'event');

    expect($event->images()->count())->toBe(0);
});

// ----- nothing in use is ever deleted -----

function putImageFiles(string $base): void
{
    foreach (['.webp', '.jpg', '-thumb.webp', '-thumb.jpg'] as $suffix) {
        Storage::disk('digitalocean')->put("public/{$base}{$suffix}", 'x');
    }
}

test('a copy that fails leaves the row and its original files where they are', function () {
    $event = Event::factory()->create(['slug' => 'my-show']);
    $image = $event->images()->create([
        'large_image_path' => 'event-images/new-event-abc123/a.webp',
        'thumb_image_path' => 'event-images/new-event-abc123/a-thumb.webp',
        'rank' => 0,
    ]);
    putImageFiles('event-images/new-event-abc123/a');

    // The DigitalOcean disk reports a failed copy by returning false.
    $failing = Mockery::mock(Storage::disk('digitalocean'))->makePartial();
    $failing->shouldReceive('copy')->andReturn(false);
    Storage::set('digitalocean', $failing);

    ImageHandler::finalize($event->fresh(), 'my-show', 'event');

    expect($image->fresh()->large_image_path)->toBe('event-images/new-event-abc123/a.webp');
    Storage::disk('digitalocean')->assertExists('public/event-images/new-event-abc123/a.webp');
    Storage::disk('digitalocean')->assertExists('public/event-images/new-event-abc123/a-thumb.jpg');
});

test('the folder guard refuses anything but an owner folder', function () {
    putImageFiles('event-images/someone-else/b');

    foreach (['.', '', '/', 'event-images', 'event-images/..', 'public', 'event-images/x/y'] as $directory) {
        ImageHandler::deleteDirectoryIfUnused($directory);
    }

    Storage::disk('digitalocean')->assertExists('public/event-images/someone-else/b.webp');
});

test('the folder guard keeps a folder any row still points into, and deletes one nobody uses', function () {
    $other = Event::factory()->create();
    $other->delete();
    $other->images()->create([
        'large_image_path' => 'event-images/shared-final/c.webp',
        'thumb_image_path' => 'event-images/shared-final/c-thumb.webp',
        'rank' => 0,
    ]);
    putImageFiles('event-images/shared-final/c');
    putImageFiles('event-images/abandoned/d');

    ImageHandler::deleteDirectoryIfUnused('event-images/shared-final');
    ImageHandler::deleteDirectoryIfUnused('event-images/abandoned');

    Storage::disk('digitalocean')->assertExists('public/event-images/shared-final/c.webp');
    Storage::disk('digitalocean')->assertMissing('public/event-images/abandoned/d.webp');
});

test('removing an image keeps its files when another listing uses the very same file', function () {
    // Production has this: a deleted copy's image row and the live listing's
    // cover are the same file.
    $live = Event::factory()->published()->create([
        'largeImagePath' => 'event-images/the-hunt/e.webp',
        'thumbImagePath' => 'event-images/the-hunt/e-thumb.webp',
    ]);
    $copy = Event::factory()->create();
    $shared = $copy->images()->create([
        'large_image_path' => 'event-images/the-hunt/e.webp',
        'thumb_image_path' => 'event-images/the-hunt/e-thumb.webp',
        'rank' => 0,
    ]);
    putImageFiles('event-images/the-hunt/e');

    ImageHandler::deleteImage($shared);

    expect(Image::find($shared->id))->toBeNull();
    Storage::disk('digitalocean')->assertExists('public/event-images/the-hunt/e.webp');
    Storage::disk('digitalocean')->assertExists('public/event-images/the-hunt/e-thumb.jpg');
});

test('removing an image nobody else uses still deletes its files', function () {
    $event = Event::factory()->create();
    $image = $event->images()->create([
        'large_image_path' => 'event-images/only-mine/f.webp',
        'thumb_image_path' => 'event-images/only-mine/f-thumb.webp',
        'rank' => 1,
    ]);
    putImageFiles('event-images/only-mine/f');

    ImageHandler::deleteImage($image);

    Storage::disk('digitalocean')->assertMissing('public/event-images/only-mine/f.webp');
});
