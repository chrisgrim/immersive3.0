<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of place a listing is at, as its organizer said on the Location
 * step: "venue" (linked to a venue page) or "place" (somewhere else: a park,
 * the streets, a home, a hidden location; any name typed is just a label).
 * NULL means nobody has said yet: every listing from before this step asked.
 * Later work that turns typed names into venues reads only the rows with
 * kind NULL AND venue_id NULL (an older row already linked to a venue is
 * settled), and never a "place".
 *
 * Nullable and appended, so MySQL adds it instantly to the big live table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        if (! Schema::hasColumn('locations', 'kind')) {
            DB::statement('ALTER TABLE locations ADD COLUMN `kind` varchar(16) NULL, ALGORITHM=INSTANT');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('locations', 'kind')) {
            DB::statement('ALTER TABLE locations DROP COLUMN `kind`');
        }
    }
};
