<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A listing's place in a project. Every column is nullable and appended, so
 * MySQL adds them instantly and existing rows keep working as plain listings.
 * No foreign key on project_id: events has none on any column, and adding one
 * would copy the live table under a write lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('instance_slug')->nullable();
            $table->string('edition')->nullable();
            $table->string('run_type', 32)->nullable();
            $table->string('festival')->nullable();
            $table->text('special_thanks')->nullable();
        });

        // Also serves lookups by project_id. MySQL allows any number of NULL
        // pairs, so plain listings do not collide.
        Schema::table('events', function (Blueprint $table) {
            $table->unique(['project_id', 'instance_slug'], 'events_project_instance_unique');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_project_instance_unique');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['project_id', 'instance_slug', 'edition', 'run_type', 'festival', 'special_thanks']);
        });
    }
};
