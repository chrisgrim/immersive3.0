<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Suggest an event" from the site footer: anyone (logged in or not) can point
 * us at an event we're missing. Rows land in the admin dashboard's Suggestions
 * queue; a moderator marks each one done (or deletes it as spam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_suggestions', function (Blueprint $table) {
            $table->id();
            $table->text('message');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_suggestions');
    }
};
