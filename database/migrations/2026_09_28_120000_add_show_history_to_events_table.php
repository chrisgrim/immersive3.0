<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Old show days kept as compact weekly runs instead of one shows row each,
     * so a permanent artwork can carry decades of history. Shape and rules:
     * App\Support\ShowHistory. Null means every show day is still a row.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('show_history')->nullable()->after('showtype_config');
        });
    }

    public function down(): void
    {
        // Once anything has been folded, the history is the ONLY copy of
        // those days (their rows were deleted). Dropping the column would
        // lose them for good, so bring them back as rows first.
        if (\Illuminate\Support\Facades\DB::table('events')->whereNotNull('show_history')->exists()) {
            throw new \RuntimeException('Some events keep old show days only in show_history. Run `php artisan ei:unfold-show-history --apply` first, then roll back.');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('show_history');
        });
    }
};
