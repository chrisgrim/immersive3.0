<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-save (SaveSearchAction) overwrites a user's single unpinned row
     * in place rather than creating a new one per search — pinned = true is
     * what protects a row from that overwrite. Not enforced as a DB-level
     * "at most one unpinned row per user" constraint; that invariant is
     * maintained by the action itself.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->boolean('pinned')->default(false)->after('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn('pinned');
        });
    }
};
