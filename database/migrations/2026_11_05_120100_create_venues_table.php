<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue is a real place people go to. The listing's own locations row keeps
 * its copied address, so search and the map never read this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('room')->nullable();
            // The address fields of `locations`, same names and types.
            $table->string('home')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('region_long')->nullable();
            $table->string('country')->nullable();
            $table->string('country_long')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->text('description')->nullable();
            $table->text('directions')->nullable();
            $table->string('largeImagePath')->nullable();
            $table->string('thumbImagePath')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->timestamps();
        });

        // An indexed column, with no foreign key: adding a constraint to a
        // live table copies it under a write lock, and an instant column plus
        // an in-place index never blocks the wizard. events.organizer_id has
        // none either.
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_id')->nullable();
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['venue_id']);
            $table->dropColumn('venue_id');
        });

        Schema::dropIfExists('venues');
    }
};
