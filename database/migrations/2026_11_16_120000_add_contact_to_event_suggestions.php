<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest's way back: an optional name and email on the "Suggest an event"
 * form, so EI can reply (a signed-in person is reached through their account).
 * Adds only; a small table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_suggestions', function (Blueprint $table) {
            if (! Schema::hasColumn('event_suggestions', 'name')) {
                $table->string('name', 100)->nullable()->after('message');
            }
            if (! Schema::hasColumn('event_suggestions', 'email')) {
                $table->string('email', 255)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_suggestions', function (Blueprint $table) {
            $table->dropColumn(['name', 'email']);
        });
    }
};
