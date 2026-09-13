<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2026-09-13 audit found four pivot tables with no index at all
 * (every event page, search hydration and reindex full-scanned them) and a
 * set of foreign-key columns that are filtered on constantly but were never
 * indexed. Plain (non-unique) indexes on purpose: they cannot fail on a
 * stray duplicate row during a production migrate, and Eloquent's sync()
 * already keeps the pivots clean. Every step is guarded so the migration is
 * idempotent on an environment where some index already exists.
 */
return new class extends Migration
{
    /** table => list of index column sets */
    private function indexes(): array
    {
        return [
            'event_genre' => [['event_id', 'genre_id'], ['genre_id']],
            'content_advisory_event' => [['event_id', 'content_advisory_id'], ['content_advisory_id']],
            'event_mobility_advisory' => [['event_id', 'mobility_advisory_id'], ['mobility_advisory_id']],
            'contact_level_event' => [['event_id', 'contact_level_id'], ['contact_level_id']],
            'organizers' => [['user_id']],
            'organizer_user' => [['user_id']],
            'events' => [['user_id']],
            'cards' => [['post_id'], ['event_id']],
            'posts' => [['community_id'], ['shelf_id']],
            'shelves' => [['community_id']],
            'curated_event_checks' => [['event_id']],
            'review_events' => [['event_id']],
            'messages' => [['user_id']],
            'favorites' => [['favorited_type', 'favorited_id']],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as $table => $columnSets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columnSets as $columns) {
                if (Schema::hasIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->index($columns);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $table => $columnSets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columnSets as $columns) {
                if (! Schema::hasIndex($table, $columns)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($columns) {
                    $t->dropIndex($columns);
                });
            }
        }
    }
};
