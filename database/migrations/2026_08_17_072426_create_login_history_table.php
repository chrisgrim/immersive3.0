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
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The live session id this login is tied to — recorded on the first
            // authenticated request of the session (not at the Login event
            // itself, since some login paths regenerate the session id right
            // after auth()->login(), which would make an event-time id stale).
            // Lets the UI mark "this device" and lets "Log out" target exactly
            // this session via the session handler's own destroy($id).
            $table->string('session_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_type')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};
