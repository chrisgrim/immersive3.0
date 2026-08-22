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
        Schema::table('user_locations', function (Blueprint $table) {
            // Enforces the User::location() hasOne invariant at the DB level —
            // without this, two concurrent first-time address saves could both
            // insert (updateOrCreate's read-then-write isn't atomic), leaving
            // two rows for one user and undefined behavior on future reads.
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_locations', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
