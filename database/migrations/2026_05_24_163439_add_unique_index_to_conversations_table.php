<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Abort early with a clear message if duplicates already exist — adding the
        // unique index would otherwise fail mid-deploy with a cryptic MySQL error.
        $duplicates = DB::table('conversations')
            ->select('conversable_type', 'conversable_id', 'user_one', 'user_two', DB::raw('COUNT(*) as c'))
            ->whereNotNull('user_one')
            ->whereNotNull('user_two')
            ->groupBy('conversable_type', 'conversable_id', 'user_one', 'user_two')
            ->having('c', '>', 1)
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException(
                "Cannot add unique index — {$duplicates} duplicate conversation group(s) exist. " .
                "Dedupe first (keep the oldest in each group, merge messages into it), then re-run."
            );
        }

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(
                ['conversable_type', 'conversable_id', 'user_one', 'user_two'],
                'conversations_participants_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_participants_unique');
        });
    }
};
