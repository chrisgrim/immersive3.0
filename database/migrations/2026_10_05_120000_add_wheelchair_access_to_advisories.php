<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wheelchair question gets three answers (full / partial / none) and an
 * explanation, required for anything short of full. The old yes/no column
 * stays and is still written (true only for "full"), so older code keeps
 * working after a rollback. Existing answers carry over: yes = full, no =
 * none (with a default explanation); unanswered stays unanswered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisories', function (Blueprint $table) {
            $table->string('wheelchairAccess', 10)->nullable()->after('wheelchairReady');
            $table->text('wheelchairDescription')->nullable()->after('wheelchairAccess');
        });

        DB::table('advisories')->where('wheelchairReady', true)->update(['wheelchairAccess' => 'full']);
        // Old "no" answers never had to explain, so they get a plain default
        // explanation instead of becoming incomplete (same text as
        // Advisory::WHEELCHAIR_DEFAULT_EXPLANATION, written out so this stays fixed).
        DB::table('advisories')->where('wheelchairReady', false)->update([
            'wheelchairAccess' => 'none',
            'wheelchairDescription' => 'Not wheelchair accessible.',
        ]);
    }

    public function down(): void
    {
        Schema::table('advisories', function (Blueprint $table) {
            $table->dropColumn(['wheelchairAccess', 'wheelchairDescription']);
        });
    }
};
