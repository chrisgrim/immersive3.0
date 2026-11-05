<?php

namespace App\Actions\Projects;

use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\SlugRedirect;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Folds a duplicate artist into another. The loser is kept with
 * merged_into_id, its credits move to the winner (billed under the old name
 * when the names differ), and the log lists them, so a mistaken merge can be
 * undone. No credit row is dropped.
 */
class MergeArtists
{
    public function handle(Artist $loser, Artist $winner, User $actor): Artist
    {
        Gate::forUser($actor)->authorize('merge', Artist::class);

        if ($loser->is($winner) || $loser->merged_into_id !== null || $winner->merged_into_id !== null) {
            throw ValidationException::withMessages(['artist' => 'Those two artists cannot be merged.']);
        }

        DB::transaction(function () use ($loser, $winner, $actor) {
            $credits = Credit::where('artist_id', $loser->id)->lockForUpdate()->get();

            Credit::whereKey($credits->modelKeys())->update(['artist_id' => $winner->id]);

            if ($loser->name !== $winner->name) {
                Credit::whereKey($credits->modelKeys())->whereNull('credited_as')->update(['credited_as' => $loser->name]);
            }

            Artist::where('merged_into_id', $loser->id)->update(['merged_into_id' => $winner->id]);

            // Artists have no public page yet; when they do, this address leads on.
            SlugRedirect::repoint($loser, $winner);
            SlugRedirect::remember('artists/'.$loser->slug, $winner);

            // A user's own record can only be one row, and the winner's stands.
            $loser->update(['merged_into_id' => $winner->id]);

            AuditLog::record($loser, 'artist.merged', $actor, ['into_artist_id' => $winner->id, 'credit_ids' => $credits->modelKeys()]);
        });

        return $winner->refresh();
    }
}
