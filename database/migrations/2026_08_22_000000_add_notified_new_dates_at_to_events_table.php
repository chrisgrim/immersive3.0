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
        Schema::table('events', function (Blueprint $table) {
            // Throttle for the saved-event-new-dates trigger (see
            // EventNotificationDispatcher::newDatesForSavedEvent) — an
            // organizer adding dates across several separate edits in one
            // day would otherwise fire one email per edit. A conditional
            // UPDATE ... WHERE notified_new_dates_at IS NULL OR <= 24h ago
            // is the same atomic claim-and-skip pattern organizer_notified_at
            // already uses for the followed-organizer trigger, just reset
            // on a rolling window instead of set once.
            $table->timestamp('notified_new_dates_at')->nullable()->after('organizer_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('notified_new_dates_at');
        });
    }
};
