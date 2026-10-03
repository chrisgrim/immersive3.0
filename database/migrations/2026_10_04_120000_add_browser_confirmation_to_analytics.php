<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Browser-confirmed and engaged visitors, side by side with the existing
 * counts. analytics_events.js: NULL when the load ping was not captured for
 * that page view, 0 while waiting for it, 1 once the browser confirmed it.
 * analytics_daily: visitor-days that were browser confirmed and engaged
 * (NULL for bot rows, and js_visitors NULL for days without the ping).
 * ALGORITHM=INSTANT (metadata only), giving up after 10 s rather than
 * queueing behind a long statement while holding the table.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'analytics_events' => ['js' => 'tinyint unsigned'],
        'analytics_daily' => ['js_visitors' => 'int unsigned', 'engaged_visitors' => 'int unsigned'],
    ];

    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Each table checks first: MySQL DDL is not transactional, so a run
        // that stopped halfway can simply run again.
        foreach (self::COLUMNS as $table => $columns) {
            $missing = collect($columns)->reject(fn ($type, $name) => Schema::hasColumn($table, $name));
            if ($missing->isNotEmpty()) {
                $add = $missing->map(fn ($type, $name) => "ADD COLUMN `{$name}` {$type} NULL")->implode(', ');
                DB::statement("ALTER TABLE {$table} {$add}, ALGORITHM=INSTANT");
            }
        }

        // An automated browser's whole day is flagged by visitor (the
        // flusher's markPings). Built online: reads and inserts carry on.
        if (! Schema::hasIndex('analytics_events', 'analytics_events_visitor_occurred_at_index')) {
            DB::statement('ALTER TABLE analytics_events ADD INDEX analytics_events_visitor_occurred_at_index (visitor, occurred_at), ALGORITHM=INPLACE, LOCK=NONE');
        }
    }

    public function down(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        if (Schema::hasIndex('analytics_events', 'analytics_events_visitor_occurred_at_index')) {
            DB::statement('ALTER TABLE analytics_events DROP INDEX analytics_events_visitor_occurred_at_index');
        }

        foreach (self::COLUMNS as $table => $columns) {
            $present = collect($columns)->filter(fn ($type, $name) => Schema::hasColumn($table, $name));
            if ($present->isNotEmpty()) {
                $drop = $present->keys()->map(fn ($name) => "DROP COLUMN `{$name}`")->implode(', ');
                DB::statement("ALTER TABLE {$table} {$drop}, ALGORITHM=INSTANT");
            }
        }
    }
};
