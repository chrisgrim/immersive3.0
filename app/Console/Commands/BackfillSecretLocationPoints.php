<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Location;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts the public point on secret locations saved before there was one, on
 * any listing that holds one, online ones too, and queues their reindex. Open
 * in-person listings with no stored point get theirs, since nearby similar
 * events read that column. Reports only, unless told to --apply. Safe to run
 * again: every published secret listing is reindexed even when its row is
 * already right, so a second run finishes what a first one left undone.
 */
class BackfillSecretLocationPoints extends Command
{
    protected $signature = 'ei:secret-location-points {--apply : Rewrite the points and queue the reindex}';

    protected $description = 'Give secret listings their public map point, and reindex them';

    private const ATTEMPTS = 3;

    private int $changing = 0;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rewrite = 0;
        $fill = 0;
        $reindex = 0;

        $this->listings()
            ->whereHas('location', fn ($query) => $query->where('hiddenLocationToggle', true))
            ->chunkById(200, function ($events) use ($apply, &$rewrite, &$reindex) {
                foreach ($events as $event) {
                    $rewrite += (int) $this->repair($event, $apply);

                    if ($event->shouldBeSearchable()) {
                        $reindex++;

                        if ($apply) {
                            $event->syncSearchIndex();
                        }
                    }
                }
            });

        // No reindex for these: the document reads the location, not this column.
        $this->listings()
            ->where('hasLocation', true)
            ->whereNull('location_latlon')
            ->whereHas('location', fn ($query) => $query->where('hiddenLocationToggle', false)
                ->whereNotNull('latitude')->where('latitude', '!=', 0)
                ->whereNotNull('longitude')->where('longitude', '!=', 0))
            ->chunkById(200, function ($events) use ($apply, &$fill) {
                foreach ($events as $event) {
                    $fill += (int) $this->repair($event, $apply);
                }
            });

        $this->info($apply
            ? "Rewrote {$rewrite} secret listing(s), filled {$fill} open listing(s) with no point, ".(config('scout.queue') ? "queued {$reindex} for reindex." : "reindexed {$reindex}.")
            : "Would rewrite {$rewrite} secret listing(s), fill {$fill} open listing(s) with no point, and reindex {$reindex}. Nothing changed: run with --apply.");

        if ($this->changing > 0) {
            $this->warn("{$this->changing} listing(s) kept changing and were left: run this again.");
        }

        return self::SUCCESS;
    }

    private function listings()
    {
        return Event::withTrashed()
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->with('location');
    }

    /** Whether the stored point is, or with --apply was, out of step with the location. */
    private function repair(Event $event, bool $apply): bool
    {
        if (! $apply) {
            return $event->location_latlon != $event->location->publicPoint();
        }

        // A live edit can land between the read and the write: its location
        // wins, and the row is read again.
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $location = $event->location;

            if ($location === null || $event->location_latlon == $location->publicPoint()) {
                return false;
            }

            if ($this->writeIfUnchanged($event, $location)) {
                return true;
            }

            $event = $this->listings()->find($event->id);
        }

        $this->changing++;

        return false;
    }

    /** Leaves updated_at: this is no content change, and the sitemap's lastmod reads it. */
    private function writeIfUnchanged(Event $event, Location $location): bool
    {
        $point = $location->publicPoint();

        return DB::table('events')
            ->where('id', $event->id)
            ->whereExists(fn ($query) => $query->from('locations')
                ->whereColumn('locations.event_id', 'events.id')
                ->where('hiddenLocationToggle', (bool) $location->hiddenLocationToggle)
                ->where('latitude', $location->latitude)
                ->where('longitude', $location->longitude))
            ->update(['location_latlon' => $point === null ? null : json_encode($point)]) > 0;
    }
}
