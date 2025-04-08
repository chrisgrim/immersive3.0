<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('videoable_id');
            $table->string('videoable_type');
            $table->string('url');
            $table->string('platform')->nullable();  // e.g., 'youtube', 'tiktok', etc.
            $table->string('platform_video_id')->nullable();  // The ID specific to the platform (YouTube ID, TikTok ID)
            $table->string('thumbnail_url')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();
            
            $table->index(['videoable_id', 'videoable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
}; 