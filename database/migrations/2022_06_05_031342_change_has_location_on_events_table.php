<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeHasLocationOnEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('hasLocation')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            // Update all NULL values to false before altering the column to not null
            DB::table('events')
                ->whereNull('hasLocation')
                ->update(['hasLocation' => false]);

            // Now it's safe to alter the column
            $table->boolean('hasLocation')->default(false)->nullable(false)->change();
        });
    }
}
