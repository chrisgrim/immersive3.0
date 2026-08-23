<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time data backfill, not a schema change. Before OrganizerRules::
 * normalizeHandle() existed, a user typing "@handle" instead of "handle"
 * into the Instagram/Twitter field saved literally with the "@" — every
 * display site prepends its own "@", rendering "@@handle" and building a
 * broken profile link URL from the raw value. Now that every write path
 * (web, MCP) strips a leading "@" going forward, this backfill makes that
 * the same guarantee for rows that already exist, so display code never
 * needs to defensively re-strip it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['instagramHandle', 'twitterHandle'] as $column) {
            DB::table('organizers')
                ->where($column, 'like', '@%')
                ->orderBy('id')
                ->each(function ($organizer) use ($column) {
                    DB::table('organizers')
                        ->where('id', $organizer->id)
                        ->update([$column => ltrim($organizer->{$column}, '@') ?: null]);
                });
        }
    }

    public function down(): void
    {
        // Not reversible — the original "@"-prefixed values aren't recoverable
        // (and wouldn't be worth restoring; they were the bug).
    }
};
