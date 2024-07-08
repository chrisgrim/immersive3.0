<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mobility_advisories', function (Blueprint $table) {
            $table->renameColumn('mobilities', 'name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobility_advisories', function (Blueprint $table) {
            $table->renameColumn('mobilities', 'name');
        });
    }
};
