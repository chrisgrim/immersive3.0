<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deleted events give up their slug when they're deleted (Event::booted).
     * Events deleted before that still hold theirs, which keeps every name
     * they went by off-limits to the listings on EI. Release them now.
     */
    public function up(): void
    {
        DB::table('events')
            ->whereNotNull('deleted_at')
            ->where('slug', 'not like', 'deleted--%')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    // Re-checked in the UPDATE itself: a row restored since this
                    // chunk was read keeps the fresh slug the restore gave it.
                    DB::table('events')->where('id', $row->id)->whereNotNull('deleted_at')
                        ->update(['slug' => Event::releasedSlug($row->id)]);
                }
            });
    }

    /**
     * The old slugs aren't kept, and they may already belong to live
     * listings, so this can't be undone. A restored event takes a new slug.
     */
    public function down(): void
    {
        //
    }
};
