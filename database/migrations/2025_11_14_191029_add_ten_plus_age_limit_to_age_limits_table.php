<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert 10+ age limit - will be automatically ordered by age value when fetched
        DB::table('age_limits')->insert([
            'name' => '10 +',
            'age' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 10+ age limit
        DB::table('age_limits')->where('name', '10 +')->where('age', 10)->delete();
    }
};
