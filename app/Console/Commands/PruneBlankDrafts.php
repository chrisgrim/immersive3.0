<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Venue;
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

    protected $description = 'Remove event drafts nobody filled in at all, and venue drafts with only a name, after a few untouched days.';

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
            $this->pruneVenueDrafts($cutoff, $limit);

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

        $this->pruneVenueDrafts($cutoff, $limit);

        return self::SUCCESS;
    }

    /**
     * Venue drafts too (Add a venue): one with nothing but a name, never sent to
     * EI and untouched since $cutoff, goes, as an empty event draft does. Left
     * behind when someone found they already have that venue, or stopped at the
     * first step. Anything more than a name (a room, an address, words, a link,
     * a tile, a photo) keeps it.
     */
    private function pruneVenueDrafts(\DateTimeInterface $cutoff, int $limit): void
    {
        $ids = $this->blankVenue(Venue::query(), $cutoff)->orderBy('id')->limit($limit)->pluck('id');

        if ($this->option('dry-run')) {
            $this->info($ids->count().' blank venue drafts would be removed'.($ids->isEmpty() ? '.' : ': '.$ids->implode(', ')));

            return;
        }

        $removed = [];
        foreach ($ids as $id) {
            try {
                $gone = DB::transaction(function () use ($id, $cutoff) {
                    $venue = $this->blankVenue(Venue::whereKey($id), $cutoff)->lockForUpdate()->first();

                    return $venue ? $venue->delete() : false;
                });
                if ($gone) {
                    $removed[] = $id;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Could not remove venue draft {$id}: {$e->getMessage()}");
            }
        }

        $this->info(count($removed).' blank venue drafts removed'.($removed ? ': '.implode(', ', $removed) : '.'));
    }

    private function blankVenue(Builder $query, \DateTimeInterface $cutoff): Builder
    {
        $empty = fn (string $column) => fn (Builder $q) => $q->whereNull($column)->orWhere($column, '');
        $query->where('status', Venue::DRAFT)->where('updated_at', '<', $cutoff)->whereNull('latitude')->whereNull('amenities');
        foreach (['room', 'home', 'street', 'city', 'description', 'directions', 'website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'twitterHandle', 'tiktokHandle'] as $column) {
            $query->where($empty($column));
        }

        // Never one an event points at, or that has claims against it.
        return $query->whereDoesntHave('images')->whereDoesntHave('locations')->whereDoesntHave('claims');
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
            // Anything else someone saved (an address, a link, advisories, a
            // show it joined...) keeps the draft: only untouched ones go.
            ->where($empty('remote_description'))
            ->where($empty('websiteUrl'))
            ->where($empty('ticketUrl'))
            ->where($empty('show_times'))
            ->where($empty('special_thanks'))
            ->where($empty('video'))
            ->whereNull('location_latlon')
            ->whereNull('closingDate')
            ->whereNull('embargo_date')
            ->whereNull('show_history')
            ->whereNull('attendance_type_id')
            ->whereNull('interactive_level_id')
            ->whereNull('advisories_id')
            ->whereNull('age_limits_id')
            ->whereNull('project_id')
            // Every draft starts with an empty address row and advisory row:
            // only one somebody filled in keeps it.
            ->whereDoesntHave('location', fn (Builder $l) => $l->whereNotNull('latitude')
                ->orWhereNotNull('venue_id')
                ->orWhere(fn ($q) => $q->whereNotNull('street')->where('street', '!=', ''))
                ->orWhere(fn ($q) => $q->whereNotNull('city')->where('city', '!=', ''))
                ->orWhere(fn ($q) => $q->whereNotNull('home')->where('home', '!=', ''))
                ->orWhere(fn ($q) => $q->whereNotNull('venue')->where('venue', '!=', ''))
                ->orWhere(fn ($q) => $q->whereNotNull('hiddenLocation')->where('hiddenLocation', '!=', '')))
            ->whereDoesntHave('advisories', fn (Builder $a) => $a->whereNotNull('wheelchairReady')
                ->orWhereNotNull('wheelchairAccess')->orWhereNotNull('sexual')
                ->orWhereNotNull('audience')->orWhereNotNull('ageRestriction')->orWhereNotNull('contactAdvisories'))
            ->whereDoesntHave('remotelocations')
            ->whereDoesntHave('credits')
            ->whereDoesntHave('contactlevels')
            ->whereDoesntHave('contentadvisories')
            ->whereDoesntHave('mobilityadvisories')
            ->whereDoesntHave('videos')
            ->whereDoesntHave('images')
            ->whereDoesntHave('shows')
            ->whereDoesntHave('tickets')
            ->whereDoesntHave('genres');
    }
}
