<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Events\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off copy of every event's ticket tiers from its per-show rows onto the
 * event itself (ticket_type = Event), the first step of storing tiers once
 * per event instead of once per date.
 *
 * Safe to run while the site is live and safe to re-run: each event is
 * handled in its own small transaction under the same row lock
 * Ticket::handleTickets takes, and an event that already has event-level
 * rows is skipped (a save since the new code shipped wrote them, and that
 * save is newer than any show copy).
 *
 * The source is the latest show, exactly the copy the editor loads and
 * re-saves and the one Show::saveShows used to copy onto new dates. If that
 * show has no tiers the event is left alone, so it keeps behaving as before.
 * Every status is included, soft-deleted events too, so a restored event
 * comes back with its tiers. The per-show rows are left alone.
 *
 * Dry run unless --apply. Both modes report events whose shows hold
 * DIFFERENT tier sets, which the move assumes never happens.
 */
class BackfillEventTickets extends Command
{
    protected $signature = 'ei:backfill-event-tickets
                            {--apply : Write the event-level rows; without it this only reports}
                            {--event= : Limit to one event id}';

    protected $description = 'Copy each event\'s ticket tiers from its shows onto the event itself. Dry run unless --apply.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $stats = [
            'events_with_show_tiers' => 0,
            'already_backfilled' => 0,
            'backfilled' => 0,
            'rows_written' => 0,
            'latest_show_has_no_tiers' => 0,
            'differing_tier_sets' => 0,
            'events_with_tierless_shows' => 0,
            'failed' => 0,
        ];
        $differing = [];
        $failed = [];

        $legacy = DB::table('tickets')->where('ticket_type', 'App\Models\Show')->count();
        if ($legacy > 0) {
            $this->warn("{$legacy} ticket rows still use the legacy type App\\Models\\Show and are NOT copied.");
        }

        // Only events that have at least one per-show tier are candidates.
        $query = DB::table('events')
            ->whereExists(fn ($q) => $q->from('shows')
                ->join('tickets', 'tickets.ticket_id', '=', 'shows.id')
                ->where('tickets.ticket_type', Show::class)
                ->whereColumn('shows.event_id', 'events.id'))
            ->select('id');

        if ($only = $this->option('event')) {
            $query->where('id', (int) $only);
        }

        $query->chunkById(200, function ($events) use ($apply, &$stats, &$differing, &$failed) {
            foreach ($events as $row) {
                $stats['events_with_show_tiers']++;
                try {
                    $this->reportParity((int) $row->id, $stats, $differing);
                    $this->backfillOne((int) $row->id, $apply, $stats);
                } catch (\Throwable $e) {
                    // One bad event (or a deadlock that outlived the retries)
                    // must not stop the run; it is listed and safe to re-run.
                    $stats['failed']++;
                    $failed[] = $row->id;
                    report($e);
                }
            }
        });

        $this->table(['', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($differing) {
            $this->warn('Events whose shows hold different tier sets (first 50): '.implode(', ', array_slice($differing, 0, 50)));
        }

        if ($failed) {
            $this->error('Failed events (re-run to retry them): '.implode(', ', $failed));
        }

        if (! $apply) {
            $this->info('Dry run: nothing was written. Re-run with --apply to copy the tiers.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every show of an event should carry the same tier set. Read outside any
     * lock: on a 4,000-date event it loads every copy, too slow to hold a
     * live save up for.
     */
    private function reportParity(int $eventId, array &$stats, array &$differing): void
    {
        $showIds = DB::table('shows')->where('event_id', $eventId)->pluck('id');

        $tiersByShow = Ticket::where('ticket_type', Show::class)
            ->whereIn('ticket_id', $showIds)
            ->get(['ticket_id', 'name', 'ticket_price', 'currency', 'description', 'type'])
            ->groupBy('ticket_id');

        $signatures = $tiersByShow->map(fn ($tiers) => $tiers
            ->map(fn ($t) => json_encode([$t->name, $t->ticket_price, $t->currency, (string) $t->description, $t->type]))
            ->sort()->implode(','))
            ->unique();

        if ($signatures->count() > 1) {
            $stats['differing_tier_sets']++;
            $differing[] = $eventId;
        }

        if ($tiersByShow->count() < $showIds->count()) {
            $stats['events_with_tierless_shows']++;
        }
    }

    private function backfillOne(int $eventId, bool $apply, array &$stats): void
    {
        DB::transaction(function () use ($eventId, $apply, &$stats) {
            if ($apply) {
                // The same lock Ticket::handleTickets takes, so a live save
                // and this copy can never interleave.
                Event::withoutGlobalScopes()->withTrashed()->whereKey($eventId)->lockForUpdate()->first();
            }

            if (Ticket::where('ticket_type', Event::class)->where('ticket_id', $eventId)->exists()) {
                $stats['already_backfilled']++;

                return;
            }

            $latestShowId = DB::table('shows')->where('event_id', $eventId)
                ->orderByDesc('date')->orderByDesc('id')->value('id');

            $source = $latestShowId === null ? collect() : Ticket::where('ticket_type', Show::class)
                ->where('ticket_id', $latestShowId)
                ->get()
                ->keyBy('name');

            if ($source->isEmpty()) {
                $stats['latest_show_has_no_tiers']++;

                return;
            }

            $stats['backfilled']++;
            $stats['rows_written'] += $source->count();

            if (! $apply) {
                return;
            }

            $now = now();
            Ticket::insert($source->map(fn ($t) => [
                'ticket_type' => Event::class,
                'ticket_id' => $eventId,
                'name' => $t->name,
                'description' => $t->description,
                'currency' => $t->currency,
                'ticket_price' => $t->ticket_price,
                'type' => $t->type,
                'ticket_amount' => $t->ticket_amount,
                'created_at' => $t->created_at ?? $now,
                'updated_at' => $now,
            ])->values()->all());
        }, 3);
    }
}
