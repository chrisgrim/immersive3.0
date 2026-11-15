<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The real venue asks for its page, as a company asks for its organizer page
 * (ownership_claims). A claim is a person's: once one is approved, the venue
 * is theirs (venues.user_id) and claimed_at is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venue_claims')) {
            Schema::create('venue_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who asked
                $table->text('message')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('admin_notes')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['venue_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasColumn('venues', 'claimed_at')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->timestamp('claimed_at')->nullable()->after('organizer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_claims');

        if (Schema::hasColumn('venues', 'claimed_at')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropColumn('claimed_at');
            });
        }
    }
};
