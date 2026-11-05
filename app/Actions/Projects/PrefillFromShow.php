<?php

namespace App\Actions\Projects;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Services\ImageHandler;
use App\Support\Projects\InstanceAddress;
use Illuminate\Support\Arr;

/**
 * Starting a new run fills the form from the show's page plus the organizer's
 * own previous listing of it, and never another presenter's details. Only
 * empty fields are filled: nothing the team has typed is overwritten. The
 * details that differ by run are then confirmed before submitting
 * (Event::needsDetailsConfirmed).
 */
class PrefillFromShow
{
    private const OWN_FIELDS = ['tag_line', 'description', 'websiteUrl', 'ticketUrl', 'category_id', 'interactive_level_id', 'age_limits_id', 'call_to_action'];

    private const ADDRESS_FIELDS = ['venue', 'venue_id', 'home', 'street', 'city', 'region', 'region_long', 'country', 'country_long', 'postal_code', 'longitude', 'latitude'];

    /** @return array<int, string> what was filled in */
    public function handle(Event $draft, Project|Event $target, User $actor): array
    {
        // Only a draft: a live listing already says what it says.
        if (in_array($draft->status, [Event::STATUS_IN_REVIEW, ...Event::LIVE_STATUSES], true)) {
            return [];
        }

        $project = $target instanceof Project ? $target : $target->project;
        $own = $this->ownPreviousListing($draft, $project, $target);
        $filled = [];

        if ($own !== null) {
            $filled = $this->fromOwnListing($draft, $own);
        }

        if ($project !== null) {
            if (blank($draft->description) && filled($project->description)) {
                $draft->update(['description' => $project->description]);
                $filled[] = 'description';
            }

            if (! $draft->images()->exists() && $project->images()->exists()) {
                ImageHandler::duplicateImages($project, $draft, 'event');
                $filled[] = 'images';
            }
        }

        if ($filled !== []) {
            AuditLog::record($draft, 'event.prefilled', $actor, ['from_event_id' => $own?->id, 'project_id' => $project?->id, 'fields' => $filled]);
        }

        return $filled;
    }

    private function ownPreviousListing(Event $draft, ?Project $project, Project|Event $target): ?Event
    {
        $candidates = $project
            ? $project->runs()->where('organizer_id', $draft->organizer_id)->whereKeyNot($draft->id)->get()
            : collect([$target])->filter(fn (Event $event) => $event->organizer_id === $draft->organizer_id);

        return $candidates
            ->sortByDesc(fn (Event $run) => InstanceAddress::startDay($run).sprintf('-%012d', $run->id))
            ->first();
    }

    /** @return array<int, string> */
    private function fromOwnListing(Event $draft, Event $own): array
    {
        $filled = [];

        foreach (self::OWN_FIELDS as $field) {
            if (blank($draft->{$field}) && filled($own->{$field}) && $this->fits($draft, $own, $field)) {
                $draft->{$field} = $own->{$field};
                $filled[] = $field;
            }
        }

        $draft->save();

        foreach (['genres', 'contentadvisories', 'mobilityadvisories', 'contactlevels'] as $relation) {
            if (! $draft->{$relation}()->exists() && $own->{$relation}()->exists()) {
                $draft->{$relation}()->sync($own->{$relation}()->pluck($own->{$relation}()->getRelated()->getTable().'.id'));
                $filled[] = $relation;
            }
        }

        if ($own->advisories && $draft->advisories && blank($draft->advisories->audience)) {
            $draft->advisories->fill(Arr::except($own->advisories->attributesToArray(), ['id', 'event_id', 'created_at', 'updated_at']))->save();
            $filled[] = 'advisories';
        }

        // A secret address stays with its own run.
        if ($draft->hasLocation && $own->hasLocation && $own->location && ! $own->location->hiddenLocationToggle
            && $draft->location && blank($draft->location->city)) {
            $draft->location->update($own->location->only(self::ADDRESS_FIELDS));
            $draft->update(['location_latlon' => $own->location_latlon]);
            $filled[] = 'address';
        }

        if (! $draft->tickets()->exists()) {
            foreach ($own->tickets as $ticket) {
                $copy = $ticket->replicate();
                $copy->ticket_id = $draft->id;
                $copy->save();
                $filled[] = 'tickets';
            }
        }

        return array_values(array_unique($filled));
    }

    /** A category belongs to in-person or online; it only carries across within one. */
    private function fits(Event $draft, Event $own, string $field): bool
    {
        return $field !== 'category_id' || (int) $draft->attendance_type_id === (int) $own->attendance_type_id;
    }
}
