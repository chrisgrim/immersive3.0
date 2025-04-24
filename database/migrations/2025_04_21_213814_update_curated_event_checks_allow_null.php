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
        // First modify the columns to be nullable
        Schema::table('curated_event_checks', function (Blueprint $table) {
            // For MySQL, we need to modify the column with its full definition
            $table->boolean('curated')->nullable()->default(null)->change();
            $table->boolean('social')->nullable()->default(null)->change();
            $table->boolean('newsletter')->nullable()->default(null)->change();
        });
        
        // Optional: After making columns nullable, update existing false values to null
        // Comment out this block if you want to keep existing values
        DB::table('curated_event_checks')
            ->where('curated', 0)
            ->update(['curated' => null]);
            
        DB::table('curated_event_checks')
            ->where('social', 0)
            ->update(['social' => null]);
            
        DB::table('curated_event_checks')
            ->where('newsletter', 0)
            ->update(['newsletter' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update null values to 0
        DB::table('curated_event_checks')
            ->whereNull('curated')
            ->update(['curated' => 0]);
            
        DB::table('curated_event_checks')
            ->whereNull('social')
            ->update(['social' => 0]);
            
        DB::table('curated_event_checks')
            ->whereNull('newsletter')
            ->update(['newsletter' => 0]);
        
        // Then remove nullable constraint
        Schema::table('curated_event_checks', function (Blueprint $table) {
            $table->boolean('curated')->nullable(false)->default(0)->change();
            $table->boolean('social')->nullable(false)->default(0)->change();
            $table->boolean('newsletter')->nullable(false)->default(0)->change();
        });
    }
};
