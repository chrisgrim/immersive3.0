<?php

namespace App\Console\Commands;

use Aws\S3\Exception\S3Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-off: give images uploaded before 2026-10-01 the long-lived
 * Cache-Control header that new uploads get from the disk config
 * (config/filesystems.php). Safe because no file is ever overwritten:
 * ImageHandler writes every image under a fresh uniqid() name, so a
 * changed picture is always a new URL, older names included.
 *
 * The file list comes from the database, never from listing the bucket.
 * Each object is copied onto itself with its own Content-Type, metadata and
 * a public-read ACL kept, so only the Cache-Control header changes; files
 * that already have the header are skipped, so a rerun is cheap. Dry run
 * unless --apply.
 */
class AddImageCacheHeaders extends Command
{
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /** Every column that stores a path inside the images bucket. */
    private const COLUMNS = [
        ['images', 'large_image_path'], ['images', 'thumb_image_path'],
        ['events', 'largeImagePath'], ['events', 'thumbImagePath'],
        ['organizers', 'largeImagePath'], ['organizers', 'thumbImagePath'],
        ['communities', 'largeImagePath'], ['communities', 'thumbImagePath'],
        ['posts', 'largeImagePath'], ['posts', 'thumbImagePath'],
        ['cards', 'largeImagePath'], ['cards', 'thumbImagePath'],
        ['categories', 'largeImagePath'], ['categories', 'thumbImagePath'],
        ['users', 'largeImagePath'], ['users', 'thumbImagePath'],
    ];

    protected $signature = 'ei:image-cache-headers
                            {--apply : Write the header; without it this only reports}
                            {--limit= : Stop after this many files (for a trial run)}
                            {--path= : Only files whose key contains this text}';

    protected $description = 'Add the long-lived Cache-Control header to existing images. Dry run unless --apply.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $client = Storage::disk('digitalocean')->getClient();
        $bucket = config('filesystems.disks.digitalocean.bucket');

        $keys = $this->keys();
        if ($only = $this->option('path')) {
            $keys = array_values(array_filter($keys, fn ($key) => str_contains($key, $only)));
        }
        if ($limit !== null) {
            $keys = array_slice($keys, 0, $limit);
        }

        $this->info(($apply ? 'Applying to ' : 'Dry run over ').count($keys)." files in bucket {$bucket}.");

        $counts = ['updated' => 0, 'would update' => 0, 'already set' => 0, 'missing' => 0, 'failed' => 0];
        $bar = $this->output->createProgressBar(count($keys));

        foreach ($keys as $key) {
            $bar->advance();

            try {
                $head = $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
            } catch (S3Exception $e) {
                $counts[$e->getStatusCode() === 404 ? 'missing' : 'failed']++;

                continue;
            }

            if (($head['CacheControl'] ?? null) === self::CACHE_CONTROL) {
                $counts['already set']++;

                continue;
            }

            if (! $apply) {
                $counts['would update']++;

                continue;
            }

            try {
                $client->copyObject(array_filter([
                    'Bucket' => $bucket,
                    'Key' => $key,
                    'CopySource' => $bucket.'/'.str_replace('%2F', '/', rawurlencode($key)),
                    'MetadataDirective' => 'REPLACE',
                    'ContentType' => $head['ContentType'] ?? null,
                    'Metadata' => $head['Metadata'] ?? [],
                    'CacheControl' => self::CACHE_CONTROL,
                    'ACL' => 'public-read',
                ], fn ($value) => $value !== null));
                $counts['updated']++;
            } catch (S3Exception $e) {
                $counts['failed']++;
                $this->newLine();
                $this->warn("{$key}: {$e->getAwsErrorMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->table(['result', 'files'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every distinct object key the database points at, with the .jpg twin
     * ImageHandler writes beside each .webp. External URLs (Google avatars)
     * and files served from the app's own /storage are not in the bucket.
     *
     * @return string[]
     */
    private function keys(): array
    {
        $keys = [];

        foreach (self::COLUMNS as [$table, $column]) {
            DB::table($table)->whereNotNull($column)->where($column, '!=', '')
                ->orderBy('id')->pluck($column)
                ->each(function (string $path) use (&$keys) {
                    if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/storage/')) {
                        return;
                    }
                    $key = 'public/'.ltrim($path, '/');
                    $keys[$key] = true;
                    if (str_ends_with($key, '.webp')) {
                        $keys[substr($key, 0, -5).'.jpg'] = true;
                    }
                });
        }

        return array_keys($keys);
    }
}
