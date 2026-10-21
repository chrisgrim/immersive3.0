<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An event the AI crawler drafted under an existing organizer stays out of that organizer's
// sight until staff approve it (see Event::isHeldFrom()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('held_for_staff')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('held_for_staff');
        });
    }
};
