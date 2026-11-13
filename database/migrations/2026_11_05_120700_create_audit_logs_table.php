<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Who linked, approved, changed or merged what. show_change_logs stays as it is. */
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
        if (Schema::hasTable('audit_logs')) {
            if (DB::table('audit_logs')->exists()) {
                return;
            }

            Schema::drop('audit_logs');
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('action', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
