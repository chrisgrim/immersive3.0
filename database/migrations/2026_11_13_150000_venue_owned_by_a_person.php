<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue is like an organizer: a person can own it outright. Until someone
 * claims it, the team that added it keeps it (organizer_id); an approved claim
 * hands it to the claimant (user_id). Guarded so a stopped run can run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'user_id')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('organizer_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'user_id')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
