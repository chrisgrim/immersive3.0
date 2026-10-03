<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * search_console_daily added up per calendar month (month = its first
 * day), kept by ei:search-console-import (each month it touched is rebuilt
 * from the daily rows; --rebuild-months refills them all). Long ranges read
 * whole months here and only the partial edge days from the daily table:
 * a year of searches is a few hundred thousand rows here instead of
 * millions there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_console_monthly')) {
            return;
        }

        Schema::create('search_console_monthly', function (Blueprint $table) {
            $table->date('month');
            $table->string('dim', 16);
            $table->string('key', 191);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->double('position_sum')->default(0);

            $table->primary(['dim', 'month', 'key']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_monthly');
    }
};
