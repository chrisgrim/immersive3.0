<?php

namespace App\Support\Projects;

use App\Models\Event;
use App\Models\Events\Show;
use App\Models\Project;
use App\Models\SlugRedirect;
use App\Scopes\DateScope;
use App\Support\ShowHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A run's address under its show: {where}-{year}[-{edition}][-{n}].
 */
class InstanceAddress
{
    /**
     * A free instance slug for $event under $project: $preferred if it can
     * have it, otherwise one built from where and when the run is. Refuses
     * any live run's address and any old address of anything but $event.
     */
    public static function allocate(Event $event, Project $project, ?string $preferred = null): string
    {
        if ($preferred !== null && static::isFree($preferred, $event, $project)) {
            return $preferred;
        }

        $base = static::base($event);
        $slug = $base;
        $count = 2;

        while (! static::isFree($slug, $event, $project)) {
            $slug = $base.'-'.$count;
            $count++;
        }

        return $slug;
    }

    private static function isFree(string $slug, Event $event, Project $project): bool
    {
        $held = Event::withTrashed()
            ->where('project_id', $project->id)
            ->where('instance_slug', $slug)
            ->whereKeyNot($event->id)
            ->exists();

        return ! $held
            && ! SlugRedirect::pointsElsewhere("events/{$project->slug}/{$slug}", $event->getMorphClass(), $event->id);
    }

    private static function base(Event $event): string
    {
        return implode('-', array_filter([
            static::where($event),
            substr(static::startDay($event), 0, 4),
            Str::slug((string) $event->edition),
        ], fn (string $part) => $part !== ''));
    }

    /** The city, or for an online run its one platform, or "online" with several. */
    private static function where(Event $event): string
    {
        if ($event->hasLocation) {
            return Str::slug((string) $event->location?->city);
        }

        $platforms = $event->remotelocations()->pluck('remote_locations.slug');

        // Run through slug() again: older platforms carry a slash ("discord/slack").
        $platform = $platforms->count() === 1 ? Str::slug(str_replace('/', '-', (string) $platforms->first())) : '';

        return $platform !== '' ? $platform : 'online';
    }

    /**
     * The local day a run starts, Y-m-d: for dated runs the earliest real day
     * in rows or history. An always-available or limited run's only row is a
     * sentinel end date, so its start is when it goes live: the embargo while
     * one holds, otherwise the approval (published_at, which the embargo cron
     * rewrites to the day it actually went live).
     */
    public static function startDay(Event $event): string
    {
        $timezone = Show::validTimezone($event->timezone);

        if (! in_array($event->showtype, ['a', 'l'], true) && ($day = static::firstShowDay($event)) !== null) {
            return $day;
        }

        if ($event->status === Event::STATUS_EMBARGOED && $event->embargoLiftsAt() !== null) {
            return $event->embargoLiftsAt()->toDateString();
        }

        $approved = $event->published_at ? Carbon::parse($event->published_at, 'UTC') : Carbon::now();

        return $approved->setTimezone($timezone)->toDateString();
    }

    /** One query for a page of runs, so startDay() runs none per run. */
    public static function primeFirstDays(Collection $events): void
    {
        $rows = Show::withoutGlobalScope(DateScope::class)
            ->whereIn('event_id', $events->pluck('id'))
            ->groupBy('event_id')
            ->selectRaw("event_id, MIN(date) as first_date, SUM(TIME(date) <> '00:00:00') as timed_shows_count")
            ->toBase()
            ->get()
            ->keyBy('event_id');

        foreach ($events as $event) {
            $event->firstShowRows = $rows->get($event->id) ?? (object) ['first_date' => null, 'timed_shows_count' => 0];
        }
    }

    private static function firstShowDay(Event $event): ?string
    {
        // As EventController::loadShowsForPage: without the scope's ORDER BY,
        // which an aggregate has no use for.
        $rows = $event->firstShowRows ?? $event->shows()
            ->withoutGlobalScope(DateScope::class)
            ->reorder()
            ->selectRaw("MIN(date) as first_date, SUM(TIME(date) <> '00:00:00') as timed_shows_count")
            ->first();

        $rowDay = $rows?->first_date
            ? Show::localDay((string) $rows->first_date, Show::validTimezone($event->timezone), (int) $rows->timed_shows_count > 0)
            : null;

        $historyDay = ShowHistory::firstDay($event->show_history);

        return collect([$rowDay, $historyDay])->filter()->min();
    }
}
