<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConversableToConversationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Add polymorphic relationship columns
            $table->string('conversable_type')->nullable();
            $table->unsignedBigInteger('conversable_id')->nullable();
            $table->string('subject')->nullable(); // This replaces event_name with a generic subject field
            
            // Create index for polymorphic relationship
            $table->index(['conversable_type', 'conversable_id']);
        });

        // Migrate existing event conversations to the new structure
        DB::table('conversations')
            ->whereNotNull('event_id')
            ->update([
                'conversable_type' => 'App\\Models\\Event',
                'conversable_id' => DB::raw('event_id'),
                'subject' => DB::raw('event_name')
            ]);

        // Drop old columns
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('event_id');
            $table->dropColumn('event_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable();
            $table->string('event_name')->nullable();
            
            // Migrate data back
            DB::table('conversations')
                ->where('conversable_type', 'App\\Models\\Event')
                ->update([
                    'event_id' => DB::raw('conversable_id'),
                    'event_name' => DB::raw('subject')
                ]);

            $table->dropColumn('conversable_type');
            $table->dropColumn('conversable_id');
            $table->dropColumn('subject');
        });
    }
}