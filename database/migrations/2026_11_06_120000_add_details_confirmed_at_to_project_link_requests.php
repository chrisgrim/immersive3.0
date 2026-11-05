<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the team confirmed the warnings, age range, accessibility, address and
 * tickets of a run joining a show. Submission asks for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_link_requests', function (Blueprint $table) {
            $table->timestamp('details_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_link_requests', function (Blueprint $table) {
            $table->dropColumn('details_confirmed_at');
        });
    }
};
