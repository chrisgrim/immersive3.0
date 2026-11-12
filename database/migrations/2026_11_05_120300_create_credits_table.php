<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who did what on a show (Project: writers only) or a run (Event: everyone
 * else). department and kind are plain strings on purpose: their allowed
 * values live in App\Enums, so changing a group needs no migration here.
 * No uniqueness: one person can hold several rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Run again after stopping partway: what is already there stays.
        if (Schema::hasTable('credits')) {
            return;
        }

        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->string('creditable_type');
            $table->unsignedBigInteger('creditable_id');
            $table->foreignId('artist_id')->constrained('artists')->restrictOnDelete();
            $table->string('department', 32);
            $table->string('role')->nullable();
            $table->string('character')->nullable();
            $table->string('kind', 16)->default('regular');
            $table->text('covers')->nullable();
            $table->string('credited_as')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('note')->nullable();
            $table->integer('rank')->default(0);
            $table->string('status', 16)->default('approved');
            $table->timestamps();

            $table->index(['creditable_type', 'creditable_id', 'department', 'rank'], 'credits_creditable_department_rank_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
