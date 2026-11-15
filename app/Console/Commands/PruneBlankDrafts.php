<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "New event" makes a draft at once, so someone who clicks it and leaves
 * leaves an empty listing behind. A draft counts as blank only when NOTHING was
 * filled in: no name, tagline, description or category, no picture (new or old style), dates,
 * tickets or genres, and it is not a staff-held crawler draft. After a few
 * days untouched it is removed for good (its location and advisories rows go
 * with it by cascade).
 *
 * Safety: never fewer than MIN_DAYS days, at most --limit per run, each draft
 * re-checked under a lock just before it goes (someone may have just started
 * filling it in), every removed id printed for the log, and one failure is
 * reported without stopping the rest. --dry-run lists what would go.
 */
class PruneBlankDrafts extends Command
{
    public const MIN_DAYS = 3;

    protected $signature = 'ei:prune-blank-drafts
        {--days=3 : Only drafts untouched for at least this many days (never fewer than 3)}
        {--limit=500 : At most this many per run}
        {--dry-run : List them, delete nothing}';

    protected $description = 'Remove event drafts nobody filled in at all, after a few untouched days.';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($days === false || $days < self::MIN_DAYS) {
            $this->error('--days must be a whole number of at least '.self::MIN_DAYS.'.');

            return self::FAILURE;
        }
        if ($limit === false || $limit < 1) {
            $this->error('--limit must be a whole number of at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $ids = $this->blank(Event::withTrashed(), $cutoff)->orderBy('id')->limit($limit)->pluck('id');

        if ($this->option('dry-run')) {
            $this->info($ids->count().' blank drafts would be removed'.($ids->isEmpty() ? '.' : ': '.$ids->implode(', ')));

            return self::SUCCESS;
        }

        $removed = [];
        foreach ($ids as $id) {
            try {
                $gone = DB::transaction(function () use ($id, $cutoff) {
                    // Still blank and still untouched, checked under a lock on the row.
                    $event = $this->blank(Event::withTrashed()->whereKey($id), $cutoff)->lockForUpdate()->first();

                    return $event ? $event->forceDelete() : false;
                });
                if ($gone) {
                    $removed[] = $id;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Could not remove draft {$id}: {$e->getMessage()}");
            }
        }

        $this->info(count($removed).' blank drafts removed'.($removed ? ': '.implode(', ', $removed) : '.'));

        return self::SUCCESS;
    }

    /** A brand-new draft nobody typed anything into, untouched since $cutoff. */
    private function blank(Builder $query, \DateTimeInterface $cutoff): Builder
    {
        $empty = fn (string $column) => fn (Builder $q) => $q->whereNull($column)->orWhere($column, '');

        return $query
            ->where('status', '0')
            ->where('held_for_staff', false)
            ->where('updated_at', '<', $cutoff)
            ->where($empty('name'))
            ->where($empty('tag_line'))
            ->where($empty('description'))
            ->whereNull('category_id')
            // A picture kept the old way (on the event row, no images row) counts too.
            ->where($empty('largeImagePath'))
            ->where($empty('thumbImagePath'))
            ->whereDoesntHave('images')
            ->whereDoesntHave('shows')
            ->whereDoesntHave('tickets')
            ->whereDoesntHave('genres');
    }
}
