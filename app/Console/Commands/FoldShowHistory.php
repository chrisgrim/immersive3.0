<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Moves show rows more than a year old into each event's compact show
 * history (Show::HISTORY_AFTER_YEARS, App\Support\ShowHistory), so a run
 * that keeps going never outgrows the shows table's per-event ceiling.
 *
 * A save already does this for the event being saved; this catches the runs
 * nobody edits. By default only events still running (closingDate from now
 * on) are touched: a finished run stops growing, so its rows can stay as
 * they are, and every reader handles both. Dry run unless --apply; a dry run
 * really folds inside a transaction and rolls it back, so its numbers are
 * exactly what --apply would do. Show::foldHistory() checks that every event
 * keeps exactly the same show days and rolls back if not.
 */
class FoldShowHistory extends Command
{
    protected $signature = 'ei:fold-show-history
                            {--apply : Write the changes; without it this only reports}
                            {--all : Also fold events whose run has already ended}
                            {--event= : Limit to one event, by id or slug}';

    protected $description = 'Move show rows more than a year old into the event\'s compact show history. Dry run unless --apply.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // A day past the cutoff in UTC, so no timezone's old rows are missed;
        // Show::foldHistory() applies the exact cutoff in the event's own
        // timezone and leaves anything younger alone.
        $roughCutoff = now()->subYears(Show::HISTORY_AFTER_YEARS)->addDay();

        $events = Event::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('showtype', ['s', 'o'])
            ->whereHas('shows', fn ($q) => $q->where('date', '<', $roughCutoff));

        if (! $this->option('all')) {
            $events->where('closingDate', '>=', now());
        }

        if ($only = $this->option('event')) {
            $events->where(is_numeric($only) ? 'id' : 'slug', $only);
        }

        $touched = 0;
        $folded = 0;
        $failed = 0;

        $events->orderBy('id')->chunkById(100, function ($chunk) use ($apply, &$touched, &$folded, &$failed) {
            foreach ($chunk as $event) {
                try {
                    if ($apply) {
                        $count = Show::foldHistory($event);
                    } else {
                        DB::beginTransaction();
                        try {
                            $count = Show::foldHistory($event);
                        } finally {
                            DB::rollBack();
                        }
                    }
                } catch (\Throwable $e) {
                    // One bad event must not stop the rest; it is left as it was.
                    report($e);
                    $failed++;
                    $this->error("{$event->id} {$event->slug}: not folded ({$e->getMessage()})");

                    continue;
                }

                if ($count === 0) {
                    continue;
                }

                $touched++;
                $folded += $count;
                $this->line(sprintf('%d %s [%s] folded %d rows', $event->id, $event->slug, $event->status, $count));
            }
        });

        $this->info(sprintf(
            '%s: %d events, %d rows moved into the show history, %d failed.',
            $apply ? 'Applied' : 'Dry run (nothing written; add --apply)',
            $touched,
            $folded,
            $failed,
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
