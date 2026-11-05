<?php

namespace App\Actions\Projects;

use App\Enums\CreditDepartment;
use App\Models\Credit;
use App\Models\Event;
use App\Models\Project;
use App\Support\Projects\InstanceAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * What happens to credits as listings join and leave shows. A show carries
 * only its writers; every run carries its own full list of everyone else.
 * Rows marked pending are reserved for a later round and never travel.
 */
class CreditLifecycle
{
    /**
     * A listing joining a show: its writing rows go up to the project. One is
     * dropped only when the project already holds an equivalent credit; a
     * different role or stint for the same person is kept.
     */
    public function moveWritersUp(Event $event, Project $project): void
    {
        $held = $project->writers()->get();

        $event->credits()
            ->where('department', CreditDepartment::Writing->value)
            ->get()
            ->each(function (Credit $writer) use ($project, $held) {
                if ($this->holds($held, $writer)) {
                    $writer->delete();

                    return;
                }

                $writer->unsetRelation('creditable');
                $writer->creditable()->associate($project);
                $writer->save();
                $held->push($writer);
            });
    }

    /**
     * A newly linked run starts as a copy of the presenter's own most recent
     * run of the show, or of the show's most recent run, by first date. One
     * that already has rows keeps them: linking never deletes data.
     */
    public function copyFromPreviousRun(Event $run, Project $project): void
    {
        if ($run->credits()->exists()) {
            return;
        }

        $this->previousRun($run, $project)
            ?->credits()->approved()->orderBy('rank')->orderBy('id')->get()
            ->each(fn (Credit $credit) => $credit->copyOnto($run));
    }

    /** Leaving a show, unlinked or dissolved, a run takes a copy of its writers. */
    public function copyWritersDown(Project $project, Event $run): void
    {
        $project->writers()->approved()->orderBy('rank')->orderBy('id')->get()
            ->each(fn (Credit $credit) => $credit->copyOnto($run));
    }

    /**
     * Copies $rows onto $destination, skipping any it already holds an
     * equivalent of. Adds, and never replaces.
     *
     * @return int how many rows were added
     */
    public function addMissing(Collection $rows, Model $destination): int
    {
        $held = $destination->credits()->get();
        $added = 0;

        foreach ($rows as $row) {
            if (! $this->holds($held, $row)) {
                $held->push($row->copyOnto($destination));
                $added++;
            }
        }

        return $added;
    }

    private function holds(Collection $held, Credit $row): bool
    {
        return $held->contains(fn (Credit $credit) => $credit->isEquivalentTo($row));
    }

    private function previousRun(Event $run, Project $project): ?Event
    {
        $others = $project->runs()->whereKeyNot($run->id)->get();
        $own = $others->where('organizer_id', $run->organizer_id);

        return ($own->isNotEmpty() ? $own : $others)
            ->sortByDesc(fn (Event $other) => InstanceAddress::startDay($other).sprintf('-%012d', $other->id))
            ->first();
    }
}
