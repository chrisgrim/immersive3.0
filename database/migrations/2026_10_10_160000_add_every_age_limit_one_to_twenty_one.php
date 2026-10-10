<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Organizers now type the youngest age instead of picking from a short
     * list (a VR show is 9 +, a light walk 4 +), so every age from 1 to 21
     * gets its own row. Events keep pointing at a row, nothing else changes.
     */
    public function up(): void
    {
        foreach (range(1, 21) as $age) {
            if (! DB::table('age_limits')->where('name', "{$age} +")->exists()) {
                DB::table('age_limits')->insert([
                    'name' => "{$age} +",
                    'age' => $age,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Removes only the rows this added, and refuses while any event uses one.
     */
    public function down(): void
    {
        $kept = [6, 8, 10, 12, 13, 16, 18, 21];
        $names = collect(range(1, 21))->diff($kept)->map(fn ($age) => "{$age} +");
        $ids = DB::table('age_limits')->whereIn('name', $names)->pluck('id');

        if (DB::table('events')->whereIn('age_limits_id', $ids)->exists()) {
            throw new RuntimeException('Some events use one of the added ages; move them to another age first.');
        }

        DB::table('age_limits')->whereIn('id', $ids)->delete();
    }
};
