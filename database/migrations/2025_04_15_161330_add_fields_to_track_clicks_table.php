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
        Schema::table('track_clicks', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referer_url')->nullable();
            $table->string('click_type')->default('link');  // Type of click (link, button, etc.)
            $table->string('destination_url')->nullable();  // Where the user was directed to
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('track_clicks', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'referer_url',
                'click_type',
                'destination_url'
            ]);
        });
    }
};
