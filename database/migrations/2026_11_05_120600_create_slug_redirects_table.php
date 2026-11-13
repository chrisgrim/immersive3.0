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

        // Laravel adds a new table's keys and indexes in statements after the
        // CREATE, so a run stopped partway can leave it without them. Nothing
        // writes here before this migration is recorded (the deploy migrates
        // before the code lands), so an empty table is made again; one holding
        // rows is a finished run whose record went missing.
        if (Schema::hasTable('slug_redirects')) {
            if (DB::table('slug_redirects')->exists()) {
                return;
            }

            Schema::drop('slug_redirects');
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
