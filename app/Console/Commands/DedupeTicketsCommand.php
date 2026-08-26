<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes duplicate ticket tiers — more than one row sharing the same
 * (ticket_type, ticket_id, name).
 *
 * Those shouldn't exist: a tier name identifies a tier, and Ticket::
 * handleTickets treats it that way. They were created by concurrent saves.
 * handleTickets has always been read-then-write ("does a tier with this name
 * exist? no? insert it"), and nothing at the database level enforced
 * uniqueness — the only index on the table is a NON-unique (ticket_type,
 * ticket_id), which doesn't even include name. Two saves landing together both
 * read "missing" and both inserted. 147 of the 148 duplicate groups found in
 * production were created within the same second, which is that race's
 * signature.
 *
 * Run this before the migration that adds the unique index; that index cannot
 * be created while duplicates remain. Dry run by default — pass --apply to
 * actually delete.
 */
class DedupeTicketsCommand extends Command
{
    protected $signature = 'ei:dedupe-tickets {--apply : Actually delete, instead of only reporting}';

    protected $description = 'Remove duplicate ticket tiers sharing a (ticket_type, ticket_id, name)';

    public function handle(): int
    {
        $groups = DB::table('tickets')
            ->select('ticket_type', 'ticket_id', 'name', DB::raw('count(*) as total'))
            ->groupBy('ticket_type', 'ticket_id', 'name')
            ->having('total', '>', 1)
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No duplicate ticket tiers found.');

            return self::SUCCESS;
        }

        $this->info("Found {$groups->count()} duplicated (ticket_type, ticket_id, name) group(s).");

        $toDelete = [];
        $conflicting = 0;

        foreach ($groups as $group) {
            $rows = DB::table('tickets')
                ->where('ticket_type', $group->ticket_type)
                ->where('ticket_id', $group->ticket_id)
                ->where('name', $group->name)
                // The survivor: most recently updated wins, oldest id breaks a
                // tie. Where the racing saves disagreed on a value, the later
                // update is the one a person most recently meant — verified
                // against the only conflicting case in production (an event
                // with $42 on every show and $40 on 49 stragglers; this picks
                // $42 on all 46 affected shows).
                ->orderByDesc('updated_at')
                ->orderBy('id')
                ->get();

            $distinctValues = $rows
                ->map(fn ($r) => $r->ticket_price.'|'.$r->currency.'|'.($r->description ?? ''))
                ->unique();

            if ($distinctValues->count() > 1) {
                $conflicting++;
                $keep = $rows->first();
                $this->line(sprintf(
                    '  conflict: %s #%d "%s" — keeping %s%s, dropping %s',
                    class_basename($group->ticket_type),
                    $group->ticket_id,
                    $group->name,
                    $keep->currency,
                    $keep->ticket_price,
                    $rows->skip(1)->map(fn ($r) => $r->currency.$r->ticket_price)->implode(', ')
                ));
            }

            // Everything after the survivor goes.
            foreach ($rows->skip(1) as $row) {
                $toDelete[] = $row->id;
            }
        }

        $this->info(sprintf(
            '%d row(s) to delete. %d group(s) had differing values; the rest were identical copies.',
            count($toDelete),
            $conflicting
        ));

        if (! $this->option('apply')) {
            $this->warn('Dry run — nothing deleted. Re-run with --apply to commit.');

            return self::SUCCESS;
        }

        // Chunked so an id list of any size stays inside the bind-parameter
        // limit, and wrapped so a partial delete can't leave the table in a
        // state the unique index still rejects.
        DB::transaction(function () use ($toDelete) {
            foreach (array_chunk($toDelete, 1000) as $chunk) {
                DB::table('tickets')->whereIn('id', $chunk)->delete();
            }
        });

        $this->info('Deleted '.count($toDelete).' duplicate ticket row(s).');

        return self::SUCCESS;
    }
}
