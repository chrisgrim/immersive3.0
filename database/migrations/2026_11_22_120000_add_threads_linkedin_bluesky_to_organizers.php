<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Three more places an organization can be found: Threads, Bluesky (handles) and LinkedIn (a page link). */
return new class extends Migration
{
    private const COLUMNS = ['threadsHandle', 'blueskyHandle', 'linkedinUrl'];

    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('organizers', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('organizers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
