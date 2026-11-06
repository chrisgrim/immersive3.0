<?php

namespace App\Actions\Projects;

use App\Actions\Search\SearchActions;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;
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

        return $this->projects($name, $viewer)
            ->concat($this->listings($name, $exceptEventId))
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    private function projects(string $name, ?User $viewer): Collection
    {
        $like = '%'.addcslashes($name, '\\%_').'%';

        return Project::with('organizer:id,name')
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
            ]);
    }

    private function listings(string $name, ?int $exceptEventId): Collection
    {
        $request = Request::create('/', 'GET', ['keywords' => $name]);

        $hits = SearchGuard::run(fn () => Event::searchQuery(
            Query::bool()->must($this->search->nameSearch($request))->filter(Event::publishedSearchFilter())
        )->load(['organizer'])->size(self::LIMIT * 2)->trackScores(true)->execute()->hits(), fn () => collect());

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
            ]);
    }
}
