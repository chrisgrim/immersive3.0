<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party analytics (App\Support\Analytics\Analytics). Written only by
 * ei:analytics-flush, pruned by ei:analytics-prune. No IP or user agent:
 * `visitor` is a hash with a salt that is thrown away after two days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->timestamp('occurred_at');
            $table->char('visitor', 16);
            $table->unsignedTinyInteger('bot')->default(0);
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('source', 16)->nullable();
            $table->string('query', 255)->nullable();
            $table->unsignedInteger('results')->nullable();
            $table->char('country', 2)->nullable();
            $table->json('props')->nullable();

            $table->index(['type', 'occurred_at']);
            $table->index(['event_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
