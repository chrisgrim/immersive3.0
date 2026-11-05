<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A listing asking to join a show: project_id, or source_event_id when the
 * show is still a standalone listing (approval then makes the project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_link_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('source_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            // The 72-hour clock: null for a draft, so an unsubmitted copy never escalates.
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_link_requests');
    }
};
