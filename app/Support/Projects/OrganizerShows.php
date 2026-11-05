<?php

namespace App\Support\Projects;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Project;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * An organizer's page lists each show once: projects it made (while one has a
 * published run), projects it presents (through a published, non-archived run,
 * so an embargoed run never announces a presenter early) and standalone
 * listings. Deduplicated first, then paginated.
 */
class OrganizerShows
{
    private const STACK_DEPTH = 3;

    private const EVENT_RELATIONS = ['category', 'genres', 'currentUserFavorite'];

    public static function paginate(Organizer $organizer, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $projectIds = self::projectIds($organizer);

        // An organizer with no shows lists exactly what it always did.
        if ($projectIds->isEmpty()) {
            return self::listings($organizer)
                ->with(self::EVENT_RELATIONS)
                ->orderByStillRunningFirst()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        }

        $entries = self::entries($organizer, $projectIds)
            ->sort(fn (array $a, array $b) => [$b['running'], $b['created'], $b['id']] <=> [$a['running'], $a['created'], $a['id']])
            ->values();

        return new LengthAwarePaginator(
            self::hydrate($organizer, $entries->forPage($page, $perPage)->values()),
            $entries->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /** @return Collection<int, int> */
    private static function projectIds(Organizer $organizer): Collection
    {
        $made = Project::where('organizer_id', $organizer->id)
            ->whereHas('runs', fn ($query) => $query->where('status', Event::STATUS_PUBLISHED))
            ->pluck('id');

        $presented = self::listings($organizer)
            ->whereNotNull('project_id')
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->distinct()
            ->pluck('project_id');

        return $made->concat($presented)->unique()->values();
    }

    private static function listings(Organizer $organizer)
    {
        return Event::where('status', Event::STATUS_PUBLISHED)
            ->where('organizer_id', $organizer->id)
            ->where('archived', false);
    }

    /** One light row per show, enough to sort the whole list before cutting a page. */
    private static function entries(Organizer $organizer, Collection $projectIds): Collection
    {
        $running = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereIn('project_id', $projectIds)
            ->stillRunning()
            ->distinct()
            ->pluck('project_id')
            ->flip();

        $projects = Project::whereKey($projectIds)->get(['id', 'created_at'])
            ->map(fn (Project $project) => [
                'type' => 'project',
                'id' => $project->id,
                'running' => isset($running[$project->id]),
                'created' => (string) $project->created_at,
            ]);

        $listings = self::listings($organizer)
            ->whereNull('project_id')
            ->without('project')
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->get(['id', 'created_at', 'closingDate', 'timezone'])
            ->map(fn (Event $event) => [
                'type' => 'event',
                'id' => $event->id,
                'running' => $event->closingAt()?->gte(now()) ?? false,
                'created' => (string) $event->created_at,
            ]);

        return $projects->concat($listings);
    }

    /** The page's rows as the card grid reads them: events as ever, shows as cards. */
    private static function hydrate(Organizer $organizer, Collection $entries): Collection
    {
        $events = Event::whereKey($entries->where('type', 'event')->pluck('id'))
            ->with(self::EVENT_RELATIONS)
            ->get()
            ->keyBy('id');

        $projects = Project::whereKey($entries->where('type', 'project')->pluck('id'))->get()->keyBy('id');

        $runs = Event::where('status', Event::STATUS_PUBLISHED)
            ->whereIn('project_id', $projects->keys())
            ->with('images')
            ->get(['id', 'project_id', 'instance_slug', 'largeImagePath'])
            ->groupBy('project_id');

        return $entries->map(fn (array $entry) => $entry['type'] === 'event'
            ? $events[$entry['id']]
            : self::card($projects[$entry['id']], $runs->get($entry['id'], collect()), $organizer, $entry['running']));
    }

    /** @return array<string, mixed> */
    private static function card(Project $project, Collection $runs, Organizer $organizer, bool $running): array
    {
        $images = $runs->map(fn (Event $run) => $run->images->firstWhere('rank', 0)?->large_image_path ?? $run->largeImagePath)
            ->filter()
            ->values();

        return [
            // Not a number: event and show ids share the grid's keys.
            'id' => 'project-'.$project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'public_url' => $project->publicPath(),
            'largeImagePath' => $project->largeImagePath ?? $images->first(),
            'isShowing' => $running,
            'show' => [
                'original' => $project->organizer_id === $organizer->id,
                'runs' => $runs->count(),
                'depth' => min($runs->count(), self::STACK_DEPTH),
            ],
        ];
    }
}
