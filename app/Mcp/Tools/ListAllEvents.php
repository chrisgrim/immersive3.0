<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Scopes\LatestPublishedFirstScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search events across the WHOLE platform, including organizers you do not belong to — use this (not list-my-events) to find any event by name, or to sweep for events needing attention, e.g. closing_before to find listings whose run is about to expire. Moderators and admins see every event in every status and can edit whatever they find with update-event; everyone else sees published events only. Returns a page of summaries; call get-event with a slug for the full detail.')]
class ListAllEvents extends Tool
{
    use FormatsEvents;

    protected const DEFAULT_LIMIT = 25;

    protected const MAX_LIMIT = 100;

    /**
     * Friendly status groups → the raw `status` chars they cover. Anything that
     * is not a lifecycle char is a draft: 'd', plus the wizard's step markers
     * ('0'-'9', 'A'-'D') that share the column.
     */
    protected const STATUS_GROUPS = [
        'published' => ['p'],
        'embargoed' => ['e'],
        'live' => ['p', 'e'],
        'in_review' => ['r'],
        'needs_revision' => ['n'],
    ];

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'organizer' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'genre' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:published,embargoed,live,in_review,needs_revision,draft,any',
            'showtype' => 'nullable|string|in:s,o,a,l',
            'closing_before' => 'nullable|date',
            'closing_after' => 'nullable|date',
            'include_archived' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_LIMIT,
            'offset' => 'nullable|integer|min:0',
            'sort' => 'nullable|string|in:closing_date,updated_at,created_at,name',
        ]);

        $user = $request->user();
        $isModerator = $user->isModerator();

        // LatestPublishedFirstScope only orders by published_at; dropping it lets our own
        // sort win (and keeps unpublished rows, whose published_at is null,
        // from sorting unpredictably).
        $query = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->with(['organizer:id,name,slug', 'category:id,name,slug', 'genres:id,name'])
            ->withCount('shows');

        // Draft/in-review/rejected events are not public. Everyone else sees
        // exactly what the website shows anonymously: published events.
        if ($isModerator) {
            $this->applyStatus($query, $validated['status'] ?? 'any');
        } else {
            $query->where('status', 'p');
        }

        if (filled($validated['search'] ?? null)) {
            $term = '%'.$this->escapeLike($validated['search']).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'LIKE', $term)->orWhere('slug', 'LIKE', $term));
        }

        if (filled($validated['organizer'] ?? null)) {
            $this->applyOrganizer($query, $validated['organizer']);
        }

        // The whole point of these two: auditing "every event in category X"
        // or "everything tagged Y" used to mean scraping the site's own
        // category page, which is lazy-loaded and drops cards silently — that
        // is how a tag cleanup missed 2 of 72 events.
        if (filled($validated['category'] ?? null)) {
            $this->applyCategory($query, $validated['category']);
        }

        if (filled($validated['genre'] ?? null)) {
            $this->applyGenre($query, $validated['genre']);
        }

        if (filled($validated['showtype'] ?? null)) {
            $query->where('showtype', $validated['showtype']);
        }

        // closingDate is what actually takes a listing off the site, so it is
        // the field to sweep on when hunting for runs about to expire.
        if (filled($validated['closing_before'] ?? null)) {
            $query->whereNotNull('closingDate')->where('closingDate', '<=', $validated['closing_before']);
        }

        if (filled($validated['closing_after'] ?? null)) {
            $query->whereNotNull('closingDate')->where('closingDate', '>=', $validated['closing_after']);
        }

        if (empty($validated['include_archived'])) {
            $query->where(fn (Builder $q) => $q->where('archived', false)->orWhereNull('archived'));
        }

        $total = (clone $query)->toBase()->getCountForPagination();

        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $offset = (int) ($validated['offset'] ?? 0);

        match ($validated['sort'] ?? 'closing_date') {
            // Nulls last either way: an event with no closing date is not
            // "expiring soonest", it is unscheduled.
            'closing_date' => $query->orderByRaw('closingDate IS NULL')->orderBy('closingDate'),
            'updated_at' => $query->orderByDesc('updated_at'),
            'created_at' => $query->orderByDesc('created_at'),
            'name' => $query->orderBy('name'),
        };

        $events = $query->orderBy('id')->offset($offset)->limit($limit)->get();

        return Response::json([
            'total_matching' => $total,
            'returned' => $events->count(),
            'offset' => $offset,
            'next_offset' => ($offset + $events->count()) < $total ? $offset + $events->count() : null,
            'scope' => $isModerator
                ? 'All events on the platform, every status. You can edit any of these with update-event — you are not limited to your own organizers.'
                : 'Published events only. Sign-in role limits this search to the public catalog; use list-my-events for your own drafts.',
            'events' => $events->map(fn (Event $event) => $this->eventSummary($event) + [
                'category' => $event->category?->name,
                'category_id' => $event->category_id,
                'genres' => $event->genres->pluck('name')->all(),
                'showtype' => $event->showtype,
                'showtype_label' => $this->showtypeLabel($event->showtype),
                'closing_date' => $event->closingDate,
                'shows' => $event->shows_count,
                'updated_at' => $event->updated_at?->toIso8601String(),
                'archived' => (bool) $event->archived,
            ]),
        ]);
    }

    /**
     * Restrict to a friendly status group. 'draft' is the complement of the
     * lifecycle chars rather than a list, because the wizard packs its step
     * marker into the same column and new markers must not silently fall out
     * of the filter.
     */
    protected function applyStatus(Builder $query, string $status): void
    {
        if ($status === 'any') {
            return;
        }

        if ($status === 'draft') {
            $query->whereNotIn('status', ['p', 'e', 'r', 'n']);

            return;
        }

        $query->whereIn('status', self::STATUS_GROUPS[$status]);
    }

    /**
     * Accept either a category id or a name fragment, matching the organizer
     * filter's convention. A name that matches nothing must return nothing
     * rather than everything, so an unmatched fragment yields an empty id set.
     */
    protected function applyCategory(Builder $query, string $category): void
    {
        if (ctype_digit($category)) {
            $query->where('category_id', (int) $category);

            return;
        }

        $ids = Category::where('name', 'LIKE', '%'.$this->escapeLike($category).'%')->pluck('id');
        $query->whereIn('category_id', $ids);
    }

    /**
     * Genres are a many-to-many, so this is whereHas, not a column match. Id or
     * name fragment again — with 1,600+ genre rows, most of them user-created
     * duplicates of each other, a fragment is usually what the caller has.
     */
    protected function applyGenre(Builder $query, string $genre): void
    {
        if (ctype_digit($genre)) {
            $query->whereHas('genres', fn (Builder $q) => $q->where('genres.id', (int) $genre));

            return;
        }

        $term = '%'.$this->escapeLike($genre).'%';
        $query->whereHas('genres', fn (Builder $q) => $q->where('genres.name', 'LIKE', $term));
    }

    /**
     * Accept either an organizer id or a name fragment, so a client that only
     * knows what the user called the company does not have to look it up first.
     */
    protected function applyOrganizer(Builder $query, string $organizer): void
    {
        if (ctype_digit($organizer)) {
            $query->where('organizer_id', (int) $organizer);

            return;
        }

        $ids = Organizer::where('name', 'LIKE', '%'.$this->escapeLike($organizer).'%')->pluck('id');
        $query->whereIn('organizer_id', $ids);
    }

    /**
     * LIKE wildcards in user input must not widen the search — "100%" should
     * match the literal string, not everything starting with "100".
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Match against the event name or slug (substring, case-insensitive).'),
            'organizer' => $schema->string()->description('Restrict to one organizer: either its numeric id or a fragment of its name.'),
            'status' => $schema->string()
                ->enum(['published', 'embargoed', 'live', 'in_review', 'needs_revision', 'draft', 'any'])
                ->description('Moderators/admins only (everyone else always gets published events). "live" = published or embargoed; "draft" = anything still in the creation wizard. Defaults to "any".'),
            'category' => $schema->string()->description('Restrict to one category: either its numeric id or a fragment of its name (e.g. "Escape Rooms"). Use list-event-attributes type=categories for the list. This is the reliable way to enumerate every event in a category.'),
            'genre' => $schema->string()->description('Restrict to events carrying this genre/tag: either its numeric id or a fragment of its name. Matches if ANY of the event\'s genres match.'),
            'showtype' => $schema->string()->enum(['s', 'o', 'a', 'l'])->description('s = specific dates, o = ongoing/recurring, a = always available, l = the retired "limited" type.'),
            'closing_before' => $schema->string()->description('Only events whose closingDate is on or before this date ("Y-m-d" or "Y-m-d H:i:s"). This is the expiry sweep: closing_before = a month from now finds runs about to drop off the site.'),
            'closing_after' => $schema->string()->description('Only events whose closingDate is on or after this date. Combine with closing_before for a window.'),
            'include_archived' => $schema->boolean()->description('Include archived events. Defaults to false.'),
            'limit' => $schema->integer()->description('Results per page, 1-'.self::MAX_LIMIT.'. Defaults to '.self::DEFAULT_LIMIT.'.'),
            'offset' => $schema->integer()->description('Skip this many results — pass the next_offset from the previous response to page through.'),
            'sort' => $schema->string()->enum(['closing_date', 'updated_at', 'created_at', 'name'])->description('Defaults to closing_date (soonest first, events with no closing date last).'),
        ];
    }
}
