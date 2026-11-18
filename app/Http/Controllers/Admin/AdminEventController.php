<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ModerateSubmission;
use App\Actions\Projects\LinkEventToProject;
use App\Actions\Projects\LinkReview;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Messaging\Message;
use App\Models\Organizer;
use App\Models\Project;
use App\Models\ProjectLinkRequest;
use App\Models\User;
use App\Models\Venue;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\EventNotificationDispatcher;
use App\Services\ImageHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminEventController extends Controller
{
    public function __construct(private LinkEventToProject $links, private LinkReview $linkReview) {}

    public function index(Request $request)
    {
        $query = Event::query()
            // Eager-load favorites (Event appends isFavorited, which reads the
            // favorites relation) and the category's events_count (Category
            // appends hasEvent). Without these, paginating 20 events fires a
            // favorites query and a category COUNT per row — see EI-LARAVEL-C.
            ->with([
                'organizer',
                'images',
                'location',
                'curatedCheck',
                'currentUserFavorite',
                'category' => fn ($q) => $q->withCount('events'),
                // Who submitted it: the admin Events table links name/email to
                // the Users tab. Columns only; User's appends need just id/type.
                'user:id,name,email,type',
            ])
            ->withCount('clicks as total_clicks')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                switch ($status) {
                    case 'published':
                        $query->whereIn('status', Event::LIVE_STATUSES);
                        break;
                    case 'in_progress':
                        $query->whereNotIn('status', Event::LIVE_STATUSES)
                            ->whereNull('deleted_at');
                        break;
                    case 'deleted':
                        $query->onlyTrashed();
                        break;
                }
            })
            ->when($request->ending_soon, function ($query) {
                $query->where(function ($q) {
                    $q->where('closingDate', '<=', now()->addDays(10))
                        ->where('closingDate', '>', now())
                        ->where(function ($subQ) {
                            $subQ->where('showtype', 'a')
                                ->orWhereHas('shows', function ($showQ) {
                                    $showQ->select('event_id')
                                        ->groupBy('event_id')
                                        ->havingRaw('COUNT(*) > 30');
                                });
                        });
                });
            })
            ->when($request->sort, function ($query, $sort) {
                switch ($sort) {
                    case 'oldest':
                        $query->orderBy('published_at', 'asc');
                        break;
                    case 'newest':
                    default:
                        $query->orderBy('published_at', 'desc');
                        break;
                }
            }, function ($query) {
                $query->orderBy('published_at', 'desc'); // Default sort by approval date
            });

        return $query->paginate(20);
    }

    public function show(Event $event)
    {
        $event->load([
            'location',
            'contentAdvisories',
            'contactLevels',
            'mobilityAdvisories',
            'advisories',
            'interactive_level',
            'remotelocations',
            'genres',
            'priceranges',
            'shows',
            'tickets',
            'age_limits',
            'images',
            'category',
            'organizer',
            // The account that created the event (and gets the approve/reject
            // email) — shown on the review page so a moderator can tell WHO is
            // submitting, not just which organizer.
            'user:id,name,email',
            'eventreviews',
            'videos',
            'staffpick',
        ])->loadCount([
            'clicks as total_clicks',
        ]);

        // Find any events with the same name (case-insensitive)
        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($event->name)])
            ->where('id', '!=', $event->id)
            ->select('id', 'name', 'slug', 'project_id', 'instance_slug')
            ->get();

        $event->duplicateEvents = $duplicateEvents;

        // The venue it names, so the review shows it beside the organizer and
        // says when approving this event approves the venue too.
        $venue = $event->location?->venue_id
            ? Venue::select('id', 'name', 'slug', 'status', 'merged_into_id', 'street', 'home', 'city', 'region', 'country', 'website', 'created_at')->find($event->location->venue_id)
            : null;
        $event->reviewVenue = $venue ? [
            'id' => $venue->id, 'name' => $venue->name, 'slug' => $venue->slug,
            'street' => trim($venue->home.' '.$venue->street), 'city' => $venue->city,
            'region' => $venue->region, 'country' => $venue->country, 'website' => $venue->website,
            'created_at' => $venue->created_at,
            'new' => $venue->status === Venue::PENDING && $venue->merged_into_id === null,
            'secret' => (bool) $event->location->hiddenLocationToggle,
            'draft' => $venue->status === Venue::DRAFT,
        ] : null;

        // The review screen reloads through here, so it never sees getPending's copy.
        $this->linkReview->attachTo(collect([$event]), request()->user(), withMatches: true);

        return response()->json($event);
    }

    /**
     * Events awaiting moderation (status 'r' — under review, not rejected).
     *
     * Newest-first clusters near-identical listings. A single organizer enters
     * a multi-city chain in one sitting — six "The Drunken Lab <city>" rows
     * created within six seconds — so a date sort hands the moderator six
     * near-identical events back to back and attention slides straight off
     * them.
     *
     * So the queue is interleaved by organizer instead: never two from the
     * same organizer in a row while any other organizer still has one left.
     *
     * Deterministic, NOT shuffled. Random ordering clumps — with half the
     * queue from one organizer a shuffle would routinely still deal three of
     * them in a row, which is the exact thing this exists to prevent. Being
     * deterministic also means it paginates correctly with no seed, and does
     * not reorder under a moderator mid-session.
     *
     * Reverting is an env change, not a deploy of new code: set
     * EI_INTERLEAVE_REVIEW_QUEUE=false and re-cache config.
     */
    /** The AI crawler's drafts waiting for a moderator (see Event::scopeCrawlerDrafts). */
    public function crawled()
    {
        return Event::crawlerDrafts()
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->with('organizer:id,name,slug,status')
            ->latest('updated_at')
            ->paginate(20)
            // Plain rows: Event's appended accessors would query per row.
            ->through(fn (Event $e) => [
                'id' => $e->id, 'name' => $e->name, 'slug' => $e->slug,
                'sent_back' => $e->status === Event::STATUS_REJECTED,
                'organizer' => $e->organizer ? ['name' => $e->organizer->name, 'slug' => $e->organizer->slug,
                    'new' => $e->organizer->status !== 'p'] : null,
                'websiteUrl' => $e->websiteUrl, 'ticketUrl' => $e->ticketUrl,
                'created_at' => $e->created_at, 'updated_at' => $e->updated_at,
            ]);
    }

    public function getPending()
    {
        $query = Event::where('status', 'r')
            ->with(['organizer', 'images', 'category', 'location', 'currentUserFavorite'])
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->latest();

        if (! config('ei.interleave_review_queue')) {
            $page = $query->paginate(20);
            $this->linkReview->attachTo($page->getCollection());

            return $page;
        }

        // Reordering by organizer cannot be expressed as a single SQL sort, so
        // the queue is loaded and arranged in PHP. Safe at this size: this is a
        // work-in-progress list a moderator is actively draining (12 events at
        // time of writing, and it has never held more than ~114). Worth
        // revisiting if it ever runs to thousands.
        $ordered = self::interleaveByOrganizer($query->get());

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $shown = $ordered->forPage($page, $perPage)->values();
        $this->linkReview->attachTo($shown);

        return new LengthAwarePaginator(
            $shown,
            $ordered->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Arrange the events so same-organizer ones sit as far apart as possible.
     *
     * Works from the smallest batch up: each batch is cut into evenly-sized
     * chunks and dealt around everything arranged so far, so the biggest batch
     * is spread across the whole queue last and most thinly.
     *
     * Six events from one organizer among six others come out perfectly
     * alternating. When one organizer holds MORE than half the queue its events
     * cannot all be separated — arithmetic, not a bug — and this splits them
     * into the most even runs the spacers allow: nine events with two others to
     * break them up become three runs of three, not one run of seven.
     *
     * Deterministic: batches are ordered by size and then by organizer id, so
     * two identical requests produce the identical order. Pagination slices a
     * re-derived arrangement, so anything less would repeat and skip events
     * between pages. Order within one organizer is left as it arrived, newest
     * first.
     */
    private static function interleaveByOrganizer(Collection $events): Collection
    {
        // sortKeys() before sortByDesc so equally-sized batches tie-break on
        // organizer id rather than on hash order.
        $batches = $events->groupBy('organizer_id')
            ->map->values()
            ->sortKeys()
            ->sortByDesc->count();

        $arranged = collect();

        foreach ($batches->values()->reverse() as $batch) {
            $chunks = self::chunkEvenly($batch, $arranged->count() + 1);
            $merged = collect();

            foreach ($chunks as $i => $chunk) {
                $merged = $merged->concat($chunk);

                // One already-arranged event between consecutive chunks; the
                // last chunk has nothing to follow it.
                if ($i < $arranged->count()) {
                    $merged->push($arranged->get($i));
                }
            }

            $arranged = $merged;
        }

        return $arranged;
    }

    /**
     * Cut a batch into exactly $chunks pieces of as near the same size as
     * possible, remainder on the front. Emptier chunks than items is fine and
     * expected — that is what produces a clean alternation when a batch is
     * smaller than the queue it is being dealt into.
     *
     * @return array<int, Collection>
     */
    private static function chunkEvenly(Collection $items, int $chunks): array
    {
        $total = $items->count();
        $out = [];
        $offset = 0;

        for ($i = 0; $i < $chunks; $i++) {
            $size = intdiv($total, $chunks) + ($i < $total % $chunks ? 1 : 0);
            $out[] = $items->slice($offset, $size)->values();
            $offset += $size;
        }

        return $out;
    }

    public function update(Request $request, $id)
    {
        // $id is the event's number. Anything else is refused: MySQL reads a
        // slug like "2024-haunted-house" as 2024 and would edit event #2024.
        abort_unless(ctype_digit((string) $id), 404);

        // Find the event even if it's deleted
        $event = Event::withTrashed()->findOrFail($id);

        if ($request->has('restore')) {
            $event->restore();

            return response()->json(['message' => 'Event restored successfully']);
        }

        // Handle organizer update action
        if ($request->action === 'update_organizer') {
            $validated = $request->validate([
                'organizer_id' => ['required', 'exists:organizers,id'],
            ]);

            $event->update([
                'organizer_id' => $validated['organizer_id'],
            ]);

            return $event->fresh(['organizer', 'images', 'category', 'location']);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:0,p,r,e,n'],
        ]);

        $event->update($validated);

        return $event->fresh(['organizer', 'images', 'category', 'location']);
    }

    /**
     * Approve the listing, and with it decide any link: `link.action` is
     * confirm (join what it asked for), change (join `link.project_id` or
     * `link.source_event_id` instead) or reject (the listing alone). No
     * `link` leaves a pending request pending. A refused link undoes the
     * whole approval: the moderator sees why and nothing has half happened.
     */
    public function approve(Event $event, Request $request)
    {
        $event->load(['user', 'organizer']);

        $link = $this->validatedLink($request, $event);

        try {
            $status = DB::transaction(function () use ($event, $link, $request) {
                // Approve organizer if not already approved
                if ($event->organizer->status !== 'p') {
                    $event->organizer->update(['status' => 'p']);
                }

                // Approve its venue too, if one is waiting: the listing would
                // otherwise go live without naming it. Not a draft (its team
                // has not sent it), not a merged one, and never for a secret
                // location (approving would publish the address it keeps).
                $venue = $event->location?->venue_id && ! $event->location->hiddenLocationToggle
                    ? Venue::whereKey($event->location->venue_id)->lockForUpdate()->first()
                    : null;
                if ($venue && $venue->status === Venue::PENDING && $venue->merged_into_id === null) {
                    $venue->update(['status' => Venue::APPROVED]);
                    AuditLog::record($venue, 'venue.approved', $request->user(), ['with_event' => $event->id]);
                }

                // Create curated check record
                $event->curatedCheck()->create();

                // Generate final slug
                $slug = Event::finalSlug($event);

                // Update event with slug first before finalizing images
                $previousSlug = $event->slug;
                $event->slug = $slug;
                $event->save();
                // A re-approval can move a listing that has been public.
                $event->rememberOldAddress($previousSlug);

                // Determine status based on embargo date (a wall-clock time in the
                // event's own timezone; the model decides, same as the publish cron)
                $status = $event->embargoIsPending() ? 'e' : 'p';

                // Format the date explicitly to match Elasticsearch mapping
                $event->update([
                    'status' => $status,
                    'published_at' => now()->format('Y-m-d H:i:s'),
                ]);

                $this->applyLink($event, $link, $request->user());

                return $status;
            });

            // After the commit: it deletes the old files, and a rollback does
            // not bring those back. A failure leaves the pictures where they
            // were, which still works.
            try {
                ImageHandler::finalize($event, $event->slug, 'event');
            } catch (\Exception $e) {
                report($e);
            }

            // Clear caches since we're publishing a new event
            Cache::forget('active-categories');
            Cache::forget('active-genres');

            // Only when actually live now — an embargoed approval ('e') isn't
            // publicly visible yet, so followers shouldn't hear about it until
            // it really publishes (either the embargo cron, or a later edit
            // that lifts the embargo — see UpdateEventAction).
            if ($status === 'p') {
                app(EventNotificationDispatcher::class)->newEventFromFollowedOrganizer($event);
            }

            $message = $event->status === 'e'
                ? Message::MESSAGES['APPROVED_EMBARGOED']
                : Message::MESSAGES['APPROVED'];
            app(ModerateSubmission::class)->notifyOwner($event, $event->user, $message, $message, 'approved');

            return response()->json([
                'message' => 'Event approved successfully',
                'event' => $event->fresh(['images', 'organizer']),
            ]);
        } catch (ValidationException|AuthorizationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);
            \Log::error('Error approving event: '.$e->getMessage());

            return response()->json([
                'message' => 'Error approving event. Please try again.',
            ], 500);
        }
    }

    /** @return array{action: string, pending: ?ProjectLinkRequest, target: Project|Event|null, maker: ?Organizer, slug: ?string, note: ?string}|null */
    private function validatedLink(Request $request, Event $event): ?array
    {
        $validated = $request->validate([
            'link' => ['sometimes', 'array'],
            'link.action' => ['required_with:link', 'in:confirm,change,reject'],
            'link.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'link.source_event_id' => ['nullable', 'integer', 'exists:events,id'],
            'link.organizer_id' => ['nullable', 'integer', 'exists:organizers,id'],
            'link.slug' => ['nullable', 'string', 'max:255', 'regex:'.Project::SLUG_PATTERN],
            'link.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $link = $validated['link'] ?? null;

        if ($link === null) {
            return null;
        }

        $target = $link['action'] === 'change'
            ? $this->links->namedTarget($link['project_id'] ?? null, $link['source_event_id'] ?? null)
            : null;

        if ($link['action'] === 'change' && $target === null) {
            throw ValidationException::withMessages(['link.project_id' => 'Choose the show run to link this listing to.']);
        }

        return [
            'action' => $link['action'],
            'pending' => ProjectLinkRequest::pending()->where('event_id', $event->id)->latest('id')->first(),
            'target' => $target,
            'maker' => ! empty($link['organizer_id']) ? Organizer::find($link['organizer_id']) : null,
            'slug' => $link['slug'] ?? null,
            'note' => $link['note'] ?? null,
        ];
    }

    private function applyLink(Event $event, ?array $link, User $moderator): void
    {
        if ($link === null) {
            return;
        }

        if ($link['action'] === 'reject') {
            if ($link['pending']) {
                $this->links->reject($link['pending'], $moderator, $link['note']);
            }

            return;
        }

        // A confirm with nothing pending has nothing to confirm.
        if ($link['pending'] || $link['target']) {
            $this->links->decide($event, $link['pending'], $link['target'], $moderator, $link['maker'], $link['slug']);
        }
    }

    public function reject(Event $event, Request $request)
    {
        $validated = $request->validate(ModerateSubmission::REASON_RULES);

        app(ModerateSubmission::class)
            ->reject($event, $event->user, $validated['reason'], 'event', Message::MESSAGES['REJECTED']);

        return response()->json([
            'message' => 'Event rejected successfully',
            'event' => $event->fresh(),
        ]);
    }

    public function destroy(Request $request, Event $event)
    {
        $event->delete();
        app(\App\Actions\Projects\DissolveProject::class)->afterEventDeleted($event, $request->user());

        return response()->json(['message' => 'Event deleted successfully']);
    }

    public function dateHistory(Event $event)
    {
        return $event->showChangeLogs()
            ->with('user:id,name')
            ->paginate(20);
    }

    public function toggleCheck(Request $request, Event $event)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:curated,social,newsletter',
        ]);

        // Load the event with its curated check
        $event->load('curatedCheck');

        // If no curated check exists yet, create one
        if (! $event->curatedCheck) {
            $event->curatedCheck()->create([
                'curated' => null,
                'social' => null,
                'newsletter' => null,
            ]);
            $event->refresh();
        }

        // Cycle through the three states: null -> false -> true -> null
        $type = $validated['type'];
        $currentValue = $event->curatedCheck->$type;

        // Determine the next state
        $nextValue = null;
        if ($currentValue === null) {
            $nextValue = false;
        } elseif ($currentValue === false) {
            $nextValue = true;
        } else {
            $nextValue = null;
        }

        // Update the check
        $event->curatedCheck->update([
            $type => $nextValue,
        ]);

        return response()->json([
            'message' => $type.' status updated successfully',
            'check' => $event->curatedCheck->fresh(),
            'event' => $event->fresh(['curatedCheck', 'organizer', 'images', 'category', 'location']),
        ]);
    }
}
