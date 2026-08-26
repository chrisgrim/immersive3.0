<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Comments;
use App\Models\Event;
use App\Models\Messaging\Message;
use App\Scopes\LatestPublishedFirstScope;
use App\Services\EventNotificationDispatcher;
use App\Services\ImageHandler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AdminEventController extends Controller
{
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
            ])
            ->withCount('clicks as total_clicks')
            ->withCount(['clicks as unique_visitors' => function ($q) {
                $q->select(\DB::raw('COUNT(DISTINCT ip_address)'));
            }])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                switch ($status) {
                    case 'published':
                        $query->whereIn('status', ['p', 'e']);
                        break;
                    case 'in_progress':
                        $query->whereNotIn('status', ['p', 'e'])
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
            'shows.tickets',
            'age_limits',
            'images',
            'category',
            'organizer',
            'eventreviews',
            'videos',
            'staffpick',
        ])->loadCount([
            'clicks as total_clicks',
            'clicks as unique_visitors' => function ($q) {
                $q->select(\DB::raw('COUNT(DISTINCT ip_address)'));
            },
        ]);

        // Find any events with the same name (case-insensitive)
        $duplicateEvents = Event::whereRaw('LOWER(name) = ?', [strtolower($event->name)])
            ->where('id', '!=', $event->id)
            ->select('id', 'name', 'slug')
            ->get();

        $event->duplicateEvents = $duplicateEvents;

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
    public function getPending()
    {
        $query = Event::where('status', 'r')
            ->with(['organizer', 'images', 'category', 'location', 'currentUserFavorite'])
            ->withoutGlobalScope(LatestPublishedFirstScope::class)
            ->latest();

        if (! config('ei.interleave_review_queue')) {
            return $query->paginate(20);
        }

        // Reordering by organizer cannot be expressed as a single SQL sort, so
        // the queue is loaded and arranged in PHP. Safe at this size: this is a
        // work-in-progress list a moderator is actively draining (12 events at
        // time of writing, and it has never held more than ~114). Worth
        // revisiting if it ever runs to thousands.
        $ordered = self::interleaveByOrganizer($query->get());

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $ordered->forPage($page, $perPage)->values(),
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

    public function approve(Event $event)
    {
        $event->load(['user', 'organizer']);

        try {
            // Approve organizer if not already approved
            if ($event->organizer->status !== 'p') {
                $event->organizer->update(['status' => 'p']);
            }

            // Create curated check record
            $event->curatedCheck()->create();

            // Generate final slug
            $slug = Event::finalSlug($event);

            // Update event with slug first before finalizing images
            $event->slug = $slug;
            $event->save();

            // Finalize images with the new slug
            ImageHandler::finalize($event, $slug, 'event');

            // Determine status based on embargo date
            $status = $event->embargo_date && $event->embargo_date > Carbon::now()
                ? 'e'
                : 'p';

            // Format the date explicitly to match Elasticsearch mapping
            $event->update([
                'status' => $status,
                'published_at' => now()->format('Y-m-d H:i:s'),
            ]);

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

            // Send notifications if not self-approving
            if (auth()->id() !== $event->user->id) {
                $message = $event->status === 'e'
                    ? Message::MESSAGES['APPROVED_EMBARGOED']
                    : Message::MESSAGES['APPROVED'];

                Message::notification($event, $message, $event->slug);
                Mail::to($event->user)->send(new Comments($event, $message, 'approved'));
            }

            return response()->json([
                'message' => 'Event approved successfully',
                'event' => $event->fresh(['images', 'organizer']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error approving event: '.$e->getMessage());

            return response()->json([
                'message' => 'Error approving event. Please try again.',
            ], 500);
        }
    }

    public function reject(Event $event, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        // Update event status
        $event->update([
            'status' => 'n',
            'rejection_reason' => $validated['reason'],
        ]);

        // Create rejection message with reason
        $message = "We've reviewed your event and have some feedback that needs to be addressed.\n\nFeedback: {$validated['reason']}";
        $inAppMessage = "We've reviewed your event and have some feedback that needs to be addressed.\n\nFeedback: {$validated['reason']}";

        if (auth()->id() !== $event->user->id) {
            $message = Message::MESSAGES['REJECTED']."\n\nReason: {$validated['reason']}";

            // Send in-app notification
            Message::notification($event, $inAppMessage, $event->slug);

            // Send email notification
            Mail::to($event->user)->send(new Comments($event, $message, 'rejected'));
        }

        return response()->json([
            'message' => 'Event rejected successfully',
            'event' => $event->fresh(),
        ]);
    }

    public function destroy(Event $event)
    {
        $event->delete();

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
