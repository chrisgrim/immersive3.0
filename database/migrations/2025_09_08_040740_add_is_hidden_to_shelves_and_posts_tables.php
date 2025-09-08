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
        Schema::table('shelves', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('status');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shelves', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
