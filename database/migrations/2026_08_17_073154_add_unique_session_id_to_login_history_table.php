<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            // A session id genuinely belongs to exactly one login — this also
            // closes a real race: two concurrent first-requests of a brand
            // new session could both pass RecordLoginHistory's non-atomic
            // "already recorded?" check and both attempt an insert. MySQL
            // permits multiple NULLs under a UNIQUE index, so this is safe
            // even though the column itself stays nullable.
            $table->dropIndex(['session_id']);
            $table->unique('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropUnique(['session_id']);
            $table->index('session_id');
        });
    }
};
