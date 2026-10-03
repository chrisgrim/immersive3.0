<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics phase 2: page views on every public page, device, campaign
 * tags, city, time on page. New columns are added with ALGORITHM=INSTANT
 * (MySQL 8: metadata only, no table rebuild, sub-second at any size), and
 * the migration gives up after 10 s rather than queueing behind a long
 * statement while holding the table. Nothing is recorded into them until
 * each capture is switched on (config/analytics.php 'capture').
 */
return new class extends Migration
{
    private const COLUMNS = [
        'page' => 'varchar(32)',
        'path' => 'varchar(191)',
        'view_id' => 'char(12)',
        'organizer_id' => 'bigint unsigned',
        'device' => 'varchar(16)',
        'browser' => 'varchar(32)',
        'os' => 'varchar(32)',
        'region' => 'varchar(64)',
        'city' => 'varchar(64)',
        'seconds' => 'smallint unsigned',
        'depth' => 'tinyint unsigned',
        'utm_source' => 'varchar(64)',
        'utm_medium' => 'varchar(64)',
        'utm_campaign' => 'varchar(64)',
    ];

    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Each step checks first: MySQL DDL is not transactional, so a run
        // that stopped halfway (e.g. at the lock timeout) can simply run again.
        $missing = collect(self::COLUMNS)->reject(fn ($type, $name) => Schema::hasColumn('analytics_events', $name));
        if ($missing->isNotEmpty()) {
            $add = $missing->map(fn ($type, $name) => "ADD COLUMN `{$name}` {$type} NULL")->implode(', ');
            DB::statement("ALTER TABLE analytics_events {$add}, ALGORITHM=INSTANT");
        }

        // Joins a time-on-page note to its page view. Built online: reads
        // and inserts carry on while it builds.
        if (! Schema::hasIndex('analytics_events', 'analytics_events_view_id_index')) {
            DB::statement('ALTER TABLE analytics_events ADD INDEX analytics_events_view_id_index (view_id), ALGORITHM=INPLACE, LOCK=NONE');
        }

        // Totals per day, kept for good (raw rows are pruned): what reports
        // and the MCP tools read for any range. Built by ei:analytics-rollup.
        if (Schema::hasTable('analytics_daily')) {
            return;
        }

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->date('day');
            $table->string('type', 32);
            $table->string('dim', 16);
            $table->string('key', 191);
            $table->boolean('bot');
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedBigInteger('seconds_sum')->default(0);
            $table->unsignedInteger('seconds_count')->default(0);

            $table->primary(['day', 'type', 'dim', 'key', 'bot']);
            $table->index(['dim', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');

        DB::statement('SET SESSION lock_wait_timeout = 10');
        DB::statement('ALTER TABLE analytics_events DROP INDEX analytics_events_view_id_index');
        $drop = collect(array_keys(self::COLUMNS))->map(fn ($name) => "DROP COLUMN `{$name}`")->implode(', ');
        DB::statement("ALTER TABLE analytics_events {$drop}, ALGORITHM=INSTANT");
    }
};
