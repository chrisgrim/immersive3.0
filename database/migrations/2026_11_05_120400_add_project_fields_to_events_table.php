<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A listing's place in a project. Every column is nullable and appended, so
 * MySQL adds them instantly and existing rows keep working as plain listings.
 * No foreign key on project_id: events has none on any column, and adding one
 * would copy the live table under a write lock.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'project_id' => 'bigint unsigned',
        'instance_slug' => 'varchar(255)',
        'edition' => 'varchar(255)',
        'run_type' => 'varchar(32)',
        'festival' => 'varchar(255)',
        'special_thanks' => 'text',
    ];

    public function up(): void
    {
        // Gives up after 10 s rather than queueing behind a long statement
        // while every read of `events` queues behind it.
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Each step checks first: MySQL DDL is not transactional, so a run
        // that stopped partway can simply run again.
        $missing = collect(self::COLUMNS)->reject(fn ($type, $name) => Schema::hasColumn('events', $name));
        if ($missing->isNotEmpty()) {
            $add = $missing->map(fn ($type, $name) => "ADD COLUMN `{$name}` {$type} NULL")->implode(', ');
            DB::statement("ALTER TABLE events {$add}, ALGORITHM=INSTANT");
        }

        // Also serves lookups by project_id. MySQL allows any number of NULL
        // pairs, so plain listings do not collide. Built online.
        if (! Schema::hasIndex('events', 'events_project_instance_unique')) {
            DB::statement('ALTER TABLE events ADD UNIQUE INDEX events_project_instance_unique (project_id, instance_slug), ALGORITHM=INPLACE, LOCK=NONE');
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_project_instance_unique');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['project_id', 'instance_slug', 'edition', 'run_type', 'festival', 'special_thanks']);
        });
    }
};
