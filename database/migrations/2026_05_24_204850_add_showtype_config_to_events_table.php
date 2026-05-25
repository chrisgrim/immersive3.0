<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the original rule that generated an event's shows (e.g. for ongoing
     * events: which weekdays + start/end date), so we don't have to reverse-engineer
     * it from the shows rows when the user re-opens the form to edit.
     *
     * Shape (ongoing):
     *   {
     *     "type": "ongoing",
     *     "days_of_week": [1, 3],
     *     "start_date": "2026-06-01",
     *     "end_date":   "2026-12-01"
     *   }
     *
     * Null is valid — legacy rows fall back to the existing reconstruction heuristic.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('showtype_config')->nullable()->after('showtype');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('showtype_config');
        });
    }
};
