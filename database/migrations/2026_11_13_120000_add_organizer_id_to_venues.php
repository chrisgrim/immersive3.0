<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue is kept by an organizer team, as an organizer page is kept by its
 * owner: whoever adds it from now on keeps it until the real venue claims it.
 * Existing venues are NOT handed to any team: they stay with staff (admins and
 * moderators can always edit) until `ei:venues-assign-owner` gives them to a
 * person, or the real venue claims them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'organizer_id')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->foreignId('organizer_id')->nullable()->after('status')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'organizer_id')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organizer_id');
            });
        }
    }
};
