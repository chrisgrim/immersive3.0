<?php

namespace App\Support;

use App\Models\Image;
use App\Services\ImageHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Several photos on a page, saved the way an event's are (UpdateEventAction):
 * new files come as images[] with ranks[], the kept ones as currentImages
 * (JSON [{id, rank}]), the removed ones as deletedImages (JSON [path]).
 * Rank 0 is the main photo. Used by venue and show pages.
 */
class PhotoGallery
{
    /** @var array<string, bool> whether a table has the main-photo columns, asked once per process */
    private static array $hasMainColumns = [];

    /** @return array<string, mixed> */
    public static function rules(int $max): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.$max],
            'images.*' => ['file', 'mimes:jpeg,png,jpg,webp,avif', 'max:10240'],
            'ranks' => ['nullable', 'array'],
            'ranks.*' => ['integer', 'min:0', 'max:'.($max - 1)],
            'currentImages' => ['nullable', 'json'],
            'deletedImages' => ['nullable', 'json'],
        ];
    }

    public static function touched(Request $request): bool
    {
        return $request->hasFile('images') || $request->has('currentImages') || $request->has('deletedImages');
    }

    /**
     * Apply one save. Files go up first with no rows at all, so nothing a
     * reader or another save can see changes until the rows are written. Then,
     * in one transaction locked on the page's own row, the photos are read
     * again, the limit is checked against what is really there, removed rows
     * go, kept ones take their ranks, new rows are added and the order is
     * closed up. Only after the commit are the removed files deleted. If
     * anything fails before the commit, the uploaded files are removed and the
     * page is exactly as it was, so the same save can be retried.
     *
     * @param  callable(int): array{0: int, 1: int}  $size  width and height for a rank
     */
    public static function sync(Model $model, Request $request, string $type, callable $size, int $max): void
    {
        [$deleted, $kept] = self::decode($request, $max);
        $uploads = array_values($request->file('images', []));
        $ranks = $request->input('ranks', []);

        // A quick early refusal, before any upload (checked again under the lock).
        $remaining = $model->images()->get()->reject(fn ($image) => $deleted->contains($image->large_image_path));
        if ($remaining->count() + count($uploads) > $max) {
            throw ValidationException::withMessages(['images' => "Up to {$max} photos."]);
        }

        // 1. Files only.
        $stored = [];
        try {
            foreach ($uploads as $index => $file) {
                $rank = (int) ($ranks[$index] ?? 0);
                [$width, $height] = $size($rank);
                $stored[] = ['paths' => ImageHandler::storeFiles($file, $model, $width, $height, $type), 'rank' => $rank];
            }

            // 2. Every row change at once, under the lock.
            $leaving = DB::transaction(function () use ($model, $deleted, $kept, $stored, $max) {
                // The page's row, fresh and locked: its main-photo columns are written
                // from this copy, never from the one this request loaded before
                // another save may have changed it. Gone meanwhile: nothing is written.
                $locked = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

                $existing = $model->images()->get();
                $leaving = $existing->filter(fn ($image) => $deleted->contains($image->large_image_path))->values();
                if ($existing->count() - $leaving->count() + count($stored) > $max) {
                    throw ValidationException::withMessages(['images' => "Up to {$max} photos."]);
                }

                foreach ($leaving as $image) {
                    $image->delete();
                }

                // Kept photos take the ranks the page sent; a new photo wins a tied
                // rank (doubled, it sorts just before a kept one doubled plus one).
                $ranked = $kept->pluck('rank', 'id');
                foreach ($existing->diff($leaving) as $image) {
                    $rank = $ranked->get($image->id, $image->rank);
                    $image->update(['rank' => $rank * 2 + 1]);
                }
                foreach ($stored as $item) {
                    $model->images()->create([...$item['paths'], 'rank' => $item['rank'] * 2]);
                }

                self::renumber($model, $locked);

                return $leaving;
            });
        } catch (\Throwable $e) {
            // Remove what was uploaded, unless a committed row already points at
            // it (an error that came after the commit, such as a lost reply to it).
            foreach ($stored as $item) {
                if (! Image::where('large_image_path', $item['paths']['large_image_path'])->exists()) {
                    ImageHandler::deleteStoredFiles($item['paths']);
                }
            }
            throw $e;
        }

        // 3. The removed files, now that nothing points at them. The rows are
        //    already gone; deleteImage still keeps a file another page shares.
        foreach ($leaving as $image) {
            try {
                ImageHandler::deleteImage($image);
            } catch (\Throwable $e) {
                report($e); // only clutter: the photo is already off the page
            }
        }
    }

    /**
     * The removed paths and the kept {id, rank} pairs, checked before anything
     * is uploaded: a rank outside 0..max-1 or a non-number id is refused.
     *
     * @return array{0: \Illuminate\Support\Collection<int, string>, 1: \Illuminate\Support\Collection<int, array{id: int, rank: int}>}
     */
    private static function decode(Request $request, int $max): array
    {
        $deleted = json_decode((string) $request->input('deletedImages', '[]'), true);
        $kept = json_decode((string) $request->input('currentImages', '[]'), true);

        $valid = is_array($deleted) && collect($deleted)->every(fn ($path) => is_string($path))
            && is_array($kept) && collect($kept)->every(fn ($item) => is_array($item)
                && filter_var($item['id'] ?? null, FILTER_VALIDATE_INT) !== false
                && filter_var($item['rank'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => max(0, $max - 1)]]) !== false);

        if (! $valid) {
            throw ValidationException::withMessages(['images' => 'Those photo changes could not be read. Please reload the page and try again.']);
        }

        return [
            collect($deleted),
            collect($kept)->map(fn ($item) => ['id' => (int) $item['id'], 'rank' => (int) $item['rank']]),
        ];
    }

    /**
     * Ranks 0, 1, 2... in their order, and the page's main-photo columns on rank 0,
     * written through the freshly locked copy of the page's row.
     */
    private static function renumber(Model $model, Model $locked): void
    {
        $images = $model->images()->orderBy('rank')->orderBy('id')->get();

        foreach ($images->values() as $rank => $image) {
            if ($image->rank !== $rank) {
                $image->update(['rank' => $rank]);
            }
        }

        if (self::$hasMainColumns[$model->getTable()] ??= Schema::hasColumns($model->getTable(), ['largeImagePath', 'thumbImagePath'])) {
            $main = $images->first();
            $columns = ['largeImagePath' => $main?->large_image_path, 'thumbImagePath' => $main?->thumb_image_path];
            $locked->forceFill($columns)->save();
            // The caller's copy shows the same, without counting as a change of its own.
            $model->forceFill($columns)->syncOriginalAttributes(array_keys($columns));
        }
    }

    /** @return array<int, array{id: int, url: string, rank: int}> */
    public static function present(Model $model): array
    {
        return $model->images()->orderBy('rank')->get()
            ->map(fn ($image) => ['id' => $image->id, 'url' => $image->large_image_path, 'rank' => (int) $image->rank])
            ->values()->all();
    }
}
