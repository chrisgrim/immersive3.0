<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixes a real data-loss bug: SaveSearchAction's passive auto-save
     * reclaims "the most recent unpinned row" on every ordinary nav search,
     * overwriting its name/criteria in place. `pinned` was the only
     * protection from that — which meant a deliberately-edited-but-not-
     * pinned search was fair game, AND unpinning a search (regardless of
     * whether it started life as a real edit) threw it right back into the
     * reclaim pool, silently destroying it on the user's next unrelated
     * search. `is_scratch` decouples "protected from the auto-save
     * overwrite" from "shown pinned at the top of the list" — a search
     * only starts as scratch (a disposable, auto-populated row) and is
     * permanently marked non-scratch the moment a person deliberately
     * touches it (UpdateSavedSearchAction) or pins it, regardless of
     * whether it's later unpinned.
     *
     * Backfill: existing pinned rows are marked protected going forward
     * (matches what pinning already implied); existing unpinned rows are
     * left as scratch since there's no reliable signal to tell a
     * genuinely-ephemeral row from a previously-edited-then-unpinned one
     * in historical data.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->boolean('is_scratch')->default(true)->after('pinned');
        });

        DB::table('saved_searches')->where('pinned', true)->update(['is_scratch' => false]);
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn('is_scratch');
        });
    }
};
