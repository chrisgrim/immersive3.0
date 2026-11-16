<?php

namespace App\Actions\Projects;

use App\Enums\CreditDepartment;
use App\Models\AuditLog;
use App\Models\Credit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Copy this run's credits onto…": adds to the destination and skips rows it
 * already holds an equivalent of, by the same test as the writer merge. It
 * never replaces. A team may copy only from a run of the same show that is
 * published or its own, since credits are not public; a moderator from any
 * listing.
 */
class CopyCredits
{
    public function __construct(private CreditLifecycle $credits) {}

    /** @return int how many rows were added */
    public function handle(Event $from, Event $to, User $actor): int
    {
        Gate::forUser($actor)->authorize('copyOnto', [Credit::class, $to]);

        if ($from->is($to)) {
            throw ValidationException::withMessages(['from_event_id' => 'A list cannot be copied onto itself.']);
        }

        $readable = $from->status === Event::STATUS_PUBLISHED || $actor->can('manage', $from);

        if (! $actor->isModerator() && ($to->project_id === null || $from->project_id !== $to->project_id || ! $readable)) {
            throw ValidationException::withMessages(['from_event_id' => 'Credits can be copied only from another run of the same show.']);
        }

        return DB::transaction(function () use ($from, $to, $actor) {
            // Serialises two copies, so the check for what is held stays true.
            Event::withTrashed()->whereKey($to->id)->reorder()->lockForUpdate()->first();

            $rows = $from->credits()->approved()->orderBy('rank')->orderBy('id')->get()
                // A linked run never holds writers: they are on its show.
                ->reject(fn (Credit $row) => $to->isLinked() && $row->department === CreditDepartment::Writing->value);

            $added = $this->credits->addMissing($rows, $to, exact: true);

            AuditLog::record($to, 'credits.copied', $actor, ['from_event_id' => $from->id, 'added' => $added]);

            return $added;
        });
    }
}
