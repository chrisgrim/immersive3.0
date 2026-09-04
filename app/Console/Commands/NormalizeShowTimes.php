<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Show;
use Illuminate\Console\Command;

/**
 * One-off repair for show rows written before Show::targetDatesFor()
 * normalised every dated show to noon of its local day — curtain times were
 * stored as sent, so an evening US show sat on the next UTC day and read a
 * day late. Moves rows in place (same ids, tickets kept), merges same-day
 * duplicates into the oldest row, fixes a closingDate the old UTC-day rule
 * derived wrongly, and re-indexes after the commit.
 *
 * By default only rows that READ wrong are moved; --all also moves rows
 * whose day is right but whose time is off the convention (~61,000
 * pre-2025-11 midnight rows), which Show::localDay() reads correctly either
 * way. Dry run unless --apply.
 */
class NormalizeShowTimes extends Command
{
    protected $signature = 'ei:normalize-show-times
                            {--apply : Write the changes; without it this only reports}
                            {--all : Also move rows whose day already reads right but whose time is off the convention}
                            {--event= : Limit to one event, by id or slug}';

    protected $description = 'Move every dated show onto noon of its local day (in place), merge same-day duplicates, recompute closingDate. Dry run unless --apply.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $onlyShifted = ! $this->option('all');

        // Every status: a draft or in-review event normalised now cannot
        // publish wrong later. Soft-deleted rows stay deleted.
        $events = Event::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('showtype', ['s', 'o']);

        if ($only = $this->option('event')) {
            $events->where(is_numeric($only) ? 'id' : 'slug', $only);
        }

        $touched = 0;
        $updated = 0;
        $merged = 0;

        $events->orderBy('id')->chunkById(200, function ($chunk) use ($apply, $onlyShifted, &$touched, &$updated, &$merged) {
            foreach ($chunk as $event) {
                $report = Show::normalizeToLocalNoon($event, $apply, $onlyShifted);

                $closingChanged = $report['closing_before'] !== $report['closing_after'];
                if ($report['updated'] === 0 && $report['merged'] === 0 && ! $closingChanged) {
                    continue;
                }

                $touched++;
                $updated += $report['updated'];
                $merged += $report['merged'];

                // One line per event as we go — a table would hold every row
                // in memory until the end, which under --all is thousands.
                $this->line(sprintf(
                    '%d %s [%s, %s] moved %d, merged %d, closing %s -> %s',
                    $event->id,
                    $event->slug,
                    $event->status,
                    $event->timezone ?: 'UTC',
                    $report['updated'],
                    $report['merged'],
                    $report['closing_before'] ?? '—',
                    $report['closing_after'] ?? '—',
                ));
            }
        });

        $this->info(sprintf(
            '%s: %d events, %d shows moved to local noon, %d duplicate rows merged.',
            $apply ? 'Applied' : 'Dry run (nothing written; add --apply)',
            $touched,
            $updated,
            $merged,
        ));

        return self::SUCCESS;
    }
}
