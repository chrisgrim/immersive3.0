<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Events\Location;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Folds a duplicate venue into another. The loser is kept with merged_into_id,
 * its old address leads to the winner, and the log records which listings were
 * attached, so a mistaken merge can be undone. A listing keeps its own copied
 * address: search and the map never read venues.
 */
class MergeVenues
{
    public function handle(Venue $loser, Venue $winner, User $actor): Venue
    {
        Gate::forUser($actor)->authorize('merge', Venue::class);

        if ($loser->is($winner) || $loser->merged_into_id !== null || $winner->merged_into_id !== null) {
            throw ValidationException::withMessages(['venue' => 'Those two venues cannot be merged.']);
        }

        DB::transaction(function () use ($loser, $winner, $actor) {
            $locationIds = Location::where('venue_id', $loser->id)->lockForUpdate()->pluck('id')->all();

            Location::whereKey($locationIds)->update(['venue_id' => $winner->id]);

            // Anything merged into the loser earlier now resolves to the winner.
            Venue::where('merged_into_id', $loser->id)->update(['merged_into_id' => $winner->id]);

            SlugRedirect::repoint($loser, $winner);
            SlugRedirect::remember('venues/'.$loser->slug, $winner);
            SlugRedirect::forget('venues/'.$winner->slug, $winner);

            $loser->update(['merged_into_id' => $winner->id]);

            AuditLog::record($loser, 'venue.merged', $actor, ['into_venue_id' => $winner->id, 'location_ids' => $locationIds]);
        });

        return $winner->refresh();
    }
}
