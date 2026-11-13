<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A venue is a real place people go to. The listing's own locations row keeps
 * its copied address, so search and the map never read this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Each step checks first: MySQL DDL is not transactional, so a run
        // that stopped partway (e.g. at the lock timeout) can simply run again.
        // A venues table stopped before its keys and indexes is made again
        // while empty (see the note in create_projects_table).
        if (Schema::hasTable('venues') && ! DB::table('venues')->exists()) {
            Schema::drop('venues');
        }

        if (! Schema::hasTable('venues')) {
            Schema::create('venues', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('room')->nullable();
                // The address fields of `locations`, same names and types.
                $table->string('home')->nullable();
                $table->string('street')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('region_long')->nullable();
                $table->string('country')->nullable();
                $table->string('country_long')->nullable();
                $table->string('postal_code')->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->text('description')->nullable();
                $table->text('directions')->nullable();
                $table->string('largeImagePath')->nullable();
                $table->string('thumbImagePath')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->foreignId('merged_into_id')->nullable()->constrained('venues')->nullOnDelete();
                $table->timestamps();
            });
        }

        // An indexed column, with no foreign key: adding a constraint to a
        // live table copies it under a write lock. The column is added
        // instantly and the index built online, and MySQL refuses rather than
        // quietly rebuild `locations` if it can't. events.organizer_id has no
        // foreign key either.
        if (! Schema::hasColumn('locations', 'venue_id')) {
            DB::statement('ALTER TABLE locations ADD COLUMN venue_id bigint unsigned NULL, ALGORITHM=INSTANT');
        }

        if (! Schema::hasIndex('locations', 'locations_venue_id_index')) {
            DB::statement('ALTER TABLE locations ADD INDEX locations_venue_id_index (venue_id), ALGORITHM=INPLACE, LOCK=NONE');
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['venue_id']);
            $table->dropColumn('venue_id');
        });

        Schema::dropIfExists('venues');
    }
};
