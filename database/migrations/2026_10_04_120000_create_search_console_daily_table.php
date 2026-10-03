<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Search Console totals per day, imported by
 * ei:search-console-import: per dimension (dim: all, query, page, country,
 * device, query_page) and value (key), the clicks, impressions and the
 * impressions-weighted position sum (average position = position_sum /
 * impressions, which stays right when days are added up).
 *
 * Primary key (dim, day, key): every reader is one dim over a day range,
 * read in key order straight from the clustered index. The (day) index is
 * for the importer's delete of one whole day.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_console_daily')) {
            return;
        }

        Schema::create('search_console_daily', function (Blueprint $table) {
            $table->date('day');
            $table->string('dim', 16);
            $table->string('key', 191);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->double('position_sum')->default(0);

            // Readers ask for one dimension over a range of days: the
            // primary key serves them; the importer deletes a day by day.
            $table->primary(['dim', 'day', 'key']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_daily');
    }
};
