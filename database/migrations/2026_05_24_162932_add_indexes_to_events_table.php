<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('organizer_id');
            $table->index('category_id');
            $table->index('archived');
            $table->index('rank');
            $table->index('published_at');
            $table->index(['status', 'organizer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['organizer_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['archived']);
            $table->dropIndex(['rank']);
            $table->dropIndex(['published_at']);
            $table->dropIndex(['status', 'organizer_id']);
        });
    }
};
