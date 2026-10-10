<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * More age minimums for children (museums and the like often say 6, 8 or
     * 12 and up). The picker sorts by `age`, which "13 +" held as 12; it
     * becomes 13 so "12 +" sorts before it. Nothing else reads `age`.
     */
    public function up(): void
    {
        foreach ([6, 8, 12] as $age) {
            if (! DB::table('age_limits')->where('name', "{$age} +")->exists()) {
                DB::table('age_limits')->insert([
                    'name' => "{$age} +",
                    'age' => $age,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('age_limits')->where('name', '13 +')->where('age', 12)->update(['age' => 13]);
    }

    /**
     * Refuses while any event uses one of the new minimums: removing it
     * would leave that event with no age at all.
     */
    public function down(): void
    {
        $ids = DB::table('age_limits')->whereIn('name', ['6 +', '8 +', '12 +'])->pluck('id');

        if (DB::table('events')->whereIn('age_limits_id', $ids)->exists()) {
            throw new RuntimeException('Some events use 6 +, 8 + or 12 +; move them to another age first.');
        }

        DB::table('age_limits')->whereIn('id', $ids)->delete();
        DB::table('age_limits')->where('name', '13 +')->where('age', 13)->update(['age' => 12]);
    }
};
