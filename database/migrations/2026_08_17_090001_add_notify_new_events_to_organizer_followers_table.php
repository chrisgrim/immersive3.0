<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same inherit-by-null pattern as favorites.notify_new_dates — null
     * falls back to the user's global followed_organizer_new_event
     * preference until they explicitly set a per-organizer override.
     */
    public function up(): void
    {
        Schema::table('organizer_followers', function (Blueprint $table) {
            $table->boolean('notify_new_events')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('organizer_followers', function (Blueprint $table) {
            $table->dropColumn('notify_new_events');
        });
    }
};
