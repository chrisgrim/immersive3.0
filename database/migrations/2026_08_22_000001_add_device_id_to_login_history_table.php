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
            // The actual dedup key for RecordLoginHistory (see its docblock) —
            // a long-lived cookie set once per browser install. Replaces
            // matching on browser+platform+ip_address, which could merge two
            // genuinely different devices sharing all three (e.g. two Macs on
            // Chrome behind the same home router), silently making one of
            // them disappear from Device History with no way to revoke it.
            $table->string('device_id')->nullable()->after('session_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
