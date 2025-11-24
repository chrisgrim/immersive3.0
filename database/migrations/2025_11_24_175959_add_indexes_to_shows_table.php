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
        Schema::table('shows', function (Blueprint $table) {
            // Add index on date for ordering queries
            $table->index('date');
            
            // Add composite index for common event + date queries
            // This covers queries filtering by event_id and ordering by date
            $table->index(['event_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['shows_date_index']);
            $table->dropIndex(['shows_event_id_date_index']);
        });
    }
};
