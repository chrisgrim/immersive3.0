<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateAttendanceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->integer('rank')->default(0);
            $table->timestamps();
        });

        // Add attendance_type_id to events table
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('attendance_type_id')->nullable()->after('category_id');
        });

        // Create the initial attendance types
        $types = [
            [
                'name' => 'In Person',
                'slug' => 'in-person',
                'rank' => 0
            ],
            [
                'name' => 'Remote',
                'slug' => 'remote',
                'rank' => 1
            ]
        ];

        foreach ($types as $type) {
            DB::table('attendance_types')->insert([
                'name' => $type['name'],
                'slug' => $type['slug'],
                'rank' => $type['rank'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Migrate existing events based on hasLocation value
        DB::statement('
            UPDATE events 
            SET attendance_type_id = CASE 
                WHEN hasLocation = 1 THEN 1 
                WHEN hasLocation = 0 THEN 2 
                ELSE NULL 
            END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('attendance_type_id');
        });
        
        Schema::dropIfExists('attendance_types');
    }
}