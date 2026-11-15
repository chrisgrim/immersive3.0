<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A venue's contact links, as an organizer page has them, plus a phone. */
return new class extends Migration
{
    private const COLUMNS = ['website', 'email', 'phone', 'instagramHandle', 'facebookHandle', 'twitterHandle', 'tiktokHandle'];

    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('venues', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('venues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
