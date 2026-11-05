<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project is the show itself, wherever it plays. A row is made only by
 * App\Actions\Projects\LinkEventToProject, when a second run of a show is
 * approved. See docs/plans/projects-venues-artists.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('largeImagePath')->nullable();
            $table->string('thumbImagePath')->nullable();
            // Made by, and who keeps the page (null: the maker does). Indexed,
            // no foreign key: a dissolved project stays soft-deleted as a
            // record and must not stop its old maker from being deleted.
            // Organizer::deleteOrganizer refuses while a live project needs it.
            $table->unsignedBigInteger('organizer_id')->index();
            $table->unsignedBigInteger('editor_organizer_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
