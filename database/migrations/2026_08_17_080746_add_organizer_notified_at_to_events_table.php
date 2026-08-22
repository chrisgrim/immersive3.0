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
            // Idempotency marker for the followed-organizer-new-event trigger,
            // which has 3 separate hook points (embargo cron, admin approval,
            // embargo-lift-on-edit) that could otherwise race — two overlapping
            // cron runs, or a cron racing an admin approving the same embargoed
            // event, could each independently decide "this just published" and
            // both dispatch. A conditional UPDATE ... WHERE organizer_notified_at
            // IS NULL is the atomic compare-and-set that makes this safe
            // regardless of which hook point gets there first.
            $table->timestamp('organizer_notified_at')->nullable()->after('embargo_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('organizer_notified_at');
        });
    }
};
