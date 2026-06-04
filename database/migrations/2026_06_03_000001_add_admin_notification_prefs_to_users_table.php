<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-admin opt-out preferences for the admin review-queue notification emails.
     * Stored as JSON so notification types can be added/granularized later without a
     * migration. Keys default to ON in code (see User::wantsNotification) — this is an
     * opt-OUT model, and a missing key means "still subscribed". Only meaningful for
     * admins (type 'a'); ignored for everyone else.
     *
     * Shape (today): {"organizers": bool, "events": bool}
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
