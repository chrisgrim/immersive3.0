<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tier name identifies a tier — Ticket::handleTickets has always treated
     * it that way — but nothing enforced it. The only index on this table was a
     * NON-unique (ticket_type, ticket_id) that doesn't include name, so two
     * concurrent saves could both read "this tier doesn't exist yet" and both
     * insert. 148 duplicate groups existed in production, 147 of them created
     * within the same second, which is that race's signature.
     *
     * Deduplicates first, then adds the constraint: the index cannot be created
     * while duplicates remain, and migrations run unattended on deploy, so
     * leaving the cleanup as a separate manual step would mean a deploy that
     * fails or not depending on what happens to be in the table.
     * `ei:dedupe-tickets` does the same cleanup with a dry run for inspecting
     * it first; running it beforehand simply leaves this with nothing to do.
     *
     * The two steps are deliberately NOT wrapped in one transaction. MySQL
     * implicitly commits on DDL, so an ALTER TABLE inside a transaction ends it
     * early and Laravel's own COMMIT then has nothing to close — which threw,
     * leaving the dedupe and the index both applied but the migration
     * unrecorded, so the next run failed on "Duplicate key name". Wrapping DDL
     * buys no atomicity on MySQL; it only hides that fact until something
     * fails. The delete is transactional on its own, and the index creation is
     * a single statement that either applies or doesn't.
     */
    public function up(): void
    {
        $this->removeDuplicates();

        // Idempotent: the failure mode above leaves the index in place with the
        // migration unrecorded, and a re-run should complete rather than throw.
        if ($this->indexExists('tickets_owner_name_unique')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            // Column order matters: the existing non-unique index leads with
            // (ticket_type, ticket_id), and this one extends it, so lookups by
            // owner still use a prefix of this index.
            $table->unique(['ticket_type', 'ticket_id', 'name'], 'tickets_owner_name_unique');
        });
    }

    private function indexExists(string $name): bool
    {
        return collect(DB::select('show index from tickets'))
            ->pluck('Key_name')
            ->contains($name);
    }

    /**
     * Keeps the most recently updated row per (ticket_type, ticket_id, name),
     * oldest id breaking a tie. Where racing saves disagreed on a price, the
     * later update is what a person most recently meant — checked against the
     * only conflicting case in production, where it keeps the price that was
     * on every show over the stragglers the losing request left behind.
     */
    private function removeDuplicates(): void
    {
        $groups = DB::table('tickets')
            ->select('ticket_type', 'ticket_id', 'name', DB::raw('count(*) as total'))
            ->groupBy('ticket_type', 'ticket_id', 'name')
            ->having('total', '>', 1)
            ->get();

        $doomed = [];

        foreach ($groups as $group) {
            $ids = DB::table('tickets')
                ->where('ticket_type', $group->ticket_type)
                ->where('ticket_id', $group->ticket_id)
                ->where('name', $group->name)
                ->orderByDesc('updated_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->skip(1) as $id) {
                $doomed[] = $id;
            }
        }

        if ($doomed === []) {
            return;
        }

        DB::transaction(function () use ($doomed) {
            foreach (array_chunk($doomed, 1000) as $chunk) {
                DB::table('tickets')->whereIn('id', $chunk)->delete();
            }
        });
    }

    /**
     * Only the constraint is reversible. The deleted duplicates are not
     * recoverable from here, which is deliberate — they were never valid rows.
     */
    public function down(): void
    {
        if (! $this->indexExists('tickets_owner_name_unique')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('tickets_owner_name_unique');
        });
    }
};
