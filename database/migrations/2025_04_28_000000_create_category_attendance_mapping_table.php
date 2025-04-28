<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateCategoryAttendanceMappingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add applicable_attendance_types field to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->json('applicable_attendance_types')->nullable()->after('type');
        });

        // Get all categories
        $categories = DB::table('categories')->get();
        
        // Update each category based on its current remote value
        foreach ($categories as $category) {
            // Check if the category has a remote field and its value
            $remoteValue = property_exists($category, 'remote') ? $category->remote : null;
            
            if ($remoteValue === true) {
                // This was a remote-only category
                $applicableTypes = [2]; // Only Remote (id=2)
            } else if ($remoteValue === false) {
                // This was an in-person-only category
                $applicableTypes = [1]; // Only In-Person (id=1)
            } else {
                // Default: can be used with any attendance type
                $applicableTypes = [1, 2]; // Both In-Person and Remote
            }
            
            // Update the category with applicable attendance types
            DB::table('categories')
                ->where('id', $category->id)
                ->update([
                    'applicable_attendance_types' => json_encode($applicableTypes)
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('applicable_attendance_types');
        });
    }
} 