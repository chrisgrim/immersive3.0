<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a venue has, picked from tiles (wheelchair access, parking, a bar...):
 * a list of keys from resources/data/venue-amenities.json. Optional, so NULL
 * for every venue until someone picks some. The venues table is small.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('venues', 'amenities')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->json('amenities')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('venues', 'amenities')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropColumn('amenities');
            });
        }
    }
};
