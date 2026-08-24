<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pilot: "notify me when a new event appears in this search" — see
     * NotifySavedSearchMatchesCommand. last_checked_at is the cursor a
     * twice-daily scheduled command advances after each successful check;
     * paired with events.published_at, it's what lets the command ask for
     * only what's NEW since last time instead of storing a result-set
     * snapshot to diff against. Nullable because a search that has never
     * been checked (just enabled, or never enabled at all) has none yet.
     */
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->boolean('notify_new_events')->default(false)->after('pinned');
            $table->timestamp('last_checked_at')->nullable()->after('notify_new_events');
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn(['notify_new_events', 'last_checked_at']);
        });
    }
};
