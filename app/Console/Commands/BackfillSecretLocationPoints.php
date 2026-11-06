<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Console\Command;

/**
 * Puts the public point on secret listings saved before there was one, and
 * queues their reindex. Reports only, unless told to --apply. Safe to run
 * again: every published secret listing is reindexed even when its row is
 * already right, so a second run finishes what a first one left undone.
 */
class BackfillSecretLocationPoints extends Command
{
    protected $signature = 'ei:secret-location-points {--apply : Rewrite the points and queue the reindex}';

    protected $description = 'Give secret listings their public map point, and reindex them';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rewrite = 0;
        $reindex = 0;

        Event::withTrashed()
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('hasLocation', true)
            ->whereHas('location', fn ($query) => $query->where('hiddenLocationToggle', true))
            ->with('location')
            ->chunkById(200, function ($events) use ($apply, &$rewrite, &$reindex) {
                foreach ($events as $event) {
                    $point = $event->location->publicPoint();

                    if ($event->location_latlon != $point) {
                        $rewrite++;

                        if ($apply) {
                            // Quietly, and leaving updated_at: this is no content
                            // change, and the sitemap's lastmod reads that column.
                            $event->timestamps = false;
                            $event->forceFill(['location_latlon' => $point])->saveQuietly();
                        }
                    }

                    if ($event->shouldBeSearchable()) {
                        $reindex++;

                        if ($apply) {
                            $event->syncSearchIndex();
                        }
                    }
                }
            });

        $this->info($apply
            ? "Rewrote {$rewrite} secret listing(s), queued {$reindex} for reindex."
            : "Would rewrite {$rewrite} secret listing(s) and queue {$reindex} for reindex. Nothing changed: run with --apply.");

        return self::SUCCESS;
    }
}
