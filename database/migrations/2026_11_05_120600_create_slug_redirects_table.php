<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Old public addresses and the record each now belongs to. A path here is
 * reserved: nothing else may take it. Long enough for
 * events/{255 chars}/{255 chars}.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION lock_wait_timeout = 10');

        // Run again after stopping partway: what is already there stays.
        if (Schema::hasTable('slug_redirects')) {
            return;
        }

        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('path', 520)->unique();
            $table->morphs('redirectable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
