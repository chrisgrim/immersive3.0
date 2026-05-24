<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('track_clicks', function (Blueprint $table) {
            $table->index('event_id');
            $table->index(['event_id', 'ip_address']);
            $table->index(['event_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('track_clicks', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropIndex(['event_id', 'ip_address']);
            $table->dropIndex(['event_id', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
