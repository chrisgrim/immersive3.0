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
        // Both tables were created with no indexes at all beyond their bare
        // foreignId columns — EventAttributesController::publicRemoteLocations()
        // joins events -> event_remote_location and filters on
        // attendance_type_id, and MySQL was doing a full table scan +
        // temp-table DISTINCT on every call with neither of these.
        Schema::table('event_remote_location', function (Blueprint $table) {
            $table->index('event_id');
            $table->index('remote_location_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->index('attendance_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_remote_location', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropIndex(['remote_location_id']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['attendance_type_id']);
        });
    }
};
