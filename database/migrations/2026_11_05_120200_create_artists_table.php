<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One row per person. Credits are the join between artists and shows or runs. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Run again after stopping partway: what is already there stays.
        if (Schema::hasTable('artists')) {
            return;
        }

        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('bio')->nullable();
            $table->string('largeImagePath')->nullable();
            $table->string('thumbImagePath')->nullable();
            $table->string('status', 16)->default('proposed')->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('artists')->nullOnDelete();
            // The signed-in person's own record, later. Nothing sets it yet.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
