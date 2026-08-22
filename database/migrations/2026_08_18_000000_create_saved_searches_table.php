<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * criteria is the normalized filter shape SavedSearchController/Actions
     * work with (city, lat, lng, searchType, live, start, end, categories,
     * tags, price) — the single source of truth a replay URL is built from,
     * not a raw stored query string (param names/encoding can drift; JSON
     * doesn't). fingerprint is a hash of that same normalized criteria,
     * unique per user, so saving an identical search again is idempotent
     * (returns the existing row) instead of creating a duplicate.
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('criteria');
            $table->string('fingerprint');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
