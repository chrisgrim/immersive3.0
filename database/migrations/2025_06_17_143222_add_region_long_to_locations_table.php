<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restored 2026-09-14: this migration ran on prod in June 2025 but the file was
 * deleted in commit b75449e, so fresh installs (and the test database) had no
 * region_long column even though the model, validator and MCP schema use it.
 * Guarded with hasColumn so it is a no-op wherever the column already exists.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('locations', 'region_long')) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->string('region_long')->nullable()->after('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('locations', 'region_long')) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('region_long');
        });
    }
};
