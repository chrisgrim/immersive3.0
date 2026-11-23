<?php

namespace App\Actions\Projects;

use App\Actions\Search\SearchActions;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
use App\Support\Projects\RunRow;
use App\Support\Search\SearchGuard;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shows with a name like this one: projects the viewer may know about (one
 * with a published run, or their own) by database match, and published
 * standalone listings through the nav search's fuzzy name query (no index of
 * its own; linked runs are filtered out after hydration). While Elasticsearch
 * is away it answers with projects only.
 */
class SuggestShows
{
    public const LIMIT = 6;

    public function __construct(private SearchActions $search) {}

    /** @return array<int, array<string, mixed>> */
    public function handle(string $name, ?int $exceptEventId = null, ?User $viewer = null): array
    {
        $name = trim($name);

        if ($name === '') {
            return [];
        }

        $typed = self::core($name);

        if (mb_strlen($typed) < 2) {
            return [];
        }

        // Only names that really are this show: the same name, give or take
        // a "(Texas)" or "(2025)", or one that starts with the other
        // ("Ha Ha Ha in Chicago" is a run of "Ha Ha Ha"). The searches below
        // are loose (any shared word), so this decides.
        return $this->projects($name, $viewer)
            ->concat($this->listings($name, $exceptEventId))
            ->filter(fn (array $show) => self::sameShow($typed, self::core($show['name'])))
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** A name reduced to the words that make it: no brackets, case, punctuation or leading "The". */
    public static function core(string $name): string
    {
        $name = preg_replace('/\s*\([^)]*\)/u', ' ', $name);
        $name = str_replace(["'", '’'], '', mb_strtolower(\Illuminate\Support\Str::ascii($name)));
        $name = trim(preg_replace('/[^a-z0-9]+/', ' ', $name));

        return preg_replace('/^the /', '', $name);
    }

    public static function sameShow(string $typed, string $other): bool
    {
        if ($other === '') {
            return false;
        }

        return $typed === $other
            || str_starts_with($typed.' ', $other.' ')
            || str_starts_with($other.' ', $typed.' ');
    }

    private function projects(string $name, ?User $viewer): Collection
    {
        $like = '%'.addcslashes($name, '\\%_').'%';

        return Project::with(['organizer:id,name', 'runs' => fn ($query) => $query
            ->where('status', Event::STATUS_PUBLISHED)
            ->with(['location', 'remotelocations'])])
            ->where(fn ($query) => $query->where('name', 'like', $like)
                // "Ha Ha Ha in Chicago" is a run of "Ha Ha Ha".
                ->orWhereRaw("? LIKE CONCAT('%', name, '%')", [$name]))
            ->visibleTo($viewer)
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Project $project) => [
                'type' => 'project',
                'id' => $project->id,
                'name' => $project->name,
                'public_url' => $project->publicPath(),
                'organizer' => $project->organizer?->only(['id', 'name']),
                'place' => self::places($project->runs),
            ]);
    }

    /**
     * Where a show's runs are, so runs with the same name can be told apart:
     * "New York, NY · Brooklyn, NY", or the first three "and 2 more".
     */
    public static function places(Collection $runs): ?string
    {
        $places = $runs->map(fn (Event $run) => RunRow::place($run))->filter()->unique()->values();
        $more = $places->count() - 3;

        return $places->isEmpty() ? null : $places->take(3)->implode(' · ').($more > 0 ? " and {$more} more" : '');
    }

    private function listings(string $name, ?int $exceptEventId): Collection
    {
        $request = Request::create('/', 'GET', ['keywords' => $name]);

        $hits = SearchGuard::run(fn () => Event::searchQuery(
            Query::bool()->must($this->search->nameSearch($request))->filter(Event::publishedSearchFilter())
        )->load(['organizer', 'location', 'remotelocations'])->size(self::LIMIT * 2)->trackScores(true)->execute()->hits(), fn () => collect());

        return collect($hits)
            ->map(fn ($hit) => $hit->model())
            ->filter(fn (?Event $event) => $event
                && $event->id !== $exceptEventId
                && ! $event->isLinked()
                && $event->status === Event::STATUS_PUBLISHED)
            ->map(fn (Event $event) => [
                'type' => 'listing',
                'id' => $event->id,
                'name' => $event->name,
                'public_url' => $event->publicPath(),
                'organizer' => $event->organizer?->only(['id', 'name']),
                'place' => RunRow::place($event) ?: null,
            ]);
    }
}
