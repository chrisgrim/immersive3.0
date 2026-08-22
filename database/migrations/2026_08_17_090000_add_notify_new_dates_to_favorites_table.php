<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable, not a plain boolean default — null means "no per-item
     * override yet, inherit the user's global saved_event_new_dates
     * preference" (see User::wantsNotification()). Explicitly true/false
     * once the user has touched the per-event "Get updates" toggle for
     * this specific favorite.
     */
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->boolean('notify_new_dates')->nullable()->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('notify_new_dates');
        });
    }
};
