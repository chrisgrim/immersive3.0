<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The undo of ei:fold-show-history: turns every event's compact show history
 * back into one shows row per day and clears the column (Show::unfoldHistory).
 * Needed before the show_history column may be dropped (the migration's
 * down() refuses otherwise), or to back the feature out. Rows come back at
 * noon of their day in the event's timezone. Dry run unless --apply, with
 * the same roll-back-and-report approach as the fold.
 */
class UnfoldShowHistory extends Command
{
    protected $signature = 'ei:unfold-show-history
                            {--apply : Write the changes; without it this only reports}
                            {--event= : Limit to one event, by id or slug}';

    protected $description = 'Turn every event\'s compact show history back into shows rows and clear it. Dry run unless --apply.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $events = Event::withoutGlobalScopes()->whereNotNull('show_history');
        if ($only = $this->option('event')) {
            $events->where(is_numeric($only) ? 'id' : 'slug', $only);
        }

        $touched = 0;
        $written = 0;
        $failed = 0;

        // By id, not chunkById: clearing the column takes each event out of
        // the query, so re-reading "the next ones" from the top is exact.
        $ids = $events->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $event = Event::withoutGlobalScopes()->find($id);
            try {
                if ($apply) {
                    $count = Show::unfoldHistory($event);
                } else {
                    DB::beginTransaction();
                    try {
                        $count = Show::unfoldHistory($event);
                    } finally {
                        DB::rollBack();
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $this->error("{$event->id} {$event->slug}: not unfolded ({$e->getMessage()})");

                continue;
            }

            $touched++;
            $written += $count;
            $this->line(sprintf('%d %s [%s] %d rows back', $event->id, $event->slug, $event->status, $count));
        }

        $this->info(sprintf(
            '%s: %d events, %d rows written back from the show history, %d failed.',
            $apply ? 'Applied' : 'Dry run (nothing written; add --apply)',
            $touched,
            $written,
            $failed,
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
