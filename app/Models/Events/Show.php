<?php

namespace App\Models\Events;

use App\Models\Event;
use App\Scopes\DateScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Show extends Model
{
    use HasFactory;

    /** Rows per bulk insert — keeps any single statement well under the DB's bind-parameter limit. */
    private const INSERT_CHUNK = 500;

    /**
     * What protected variables are allowed to be passed to the database
     *
     * @var array
     */
    protected $fillable = ['date', 'event_id'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new DateScope);
    }

    /**
     * Show Model belongs to the Event Model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Each Show has many tickets
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->morphMany(Ticket::class, 'ticket');
    }

    public static function saveShows($request, $event, ?string $previousShowtype = null)
    {
        // The type the rows being replaced were created under. UpdateEventAction
        // has mass-assigned the NEW type onto $event by the time this runs, so
        // callers that know the old one pass it in (as they do for updateEvent);
        // anyone else falls back to the model, matching the old behaviour.
        $previousShowtype = func_num_args() >= 3 ? $previousShowtype : $event->showtype;

        // Capture current show dates before any changes (for change logging)
        $oldDates = $event->shows()->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->sort()->values()->toArray();

        // Capture the event's ticket tiers before any deletes so they can be
        // re-applied to newly created shows (tickets are uniform across shows).
        $firstShow = $event->shows()->first();
        $oldTickets = $firstShow && $firstShow->tickets()->exists() ? $firstShow->tickets()->get() : null;

        // The exact set of show datetimes this save should end with, normalised
        // to UTC "Y-m-d H:i:s" so they compare byte-for-byte with stored dates.
        $targetDates = self::targetDatesFor($request);
        // Against the PREVIOUS type, not $event->showtype: UpdateEventAction has
        // already mass-assigned the new one, so that comparison never saw a
        // switch — the "wipe on switch" below never ran, and a dateArray that
        // echoed an expired sentinel's exact datetime kept that row as a "show"
        // of the new dated event: an availability end marker rebranded as a
        // past performance, with no guard fired.
        $showtypeChanged = $request->showtype !== $previousShowtype;

        // Defence-in-depth: a specific/ongoing event must never be left with zero
        // shows. If the resolved target set is empty — a caller that echoed
        // showtype without real schedule data, or an upstream guard miss — skip
        // the save entirely rather than letting `whereNotIn('date', [])` (which
        // matches EVERY row) wipe the schedule. The MCP tool and web wizard both
        // reject an empty s/o schedule upstream; this is the last line of defence.
        if (empty($targetDates) && in_array($request->showtype, ['s', 'o'], true)) {
            return ['preserved' => [], 'rejected' => []];
        }

        // Populated inside the transaction below if any already-past show
        // the caller asked to remove was kept instead — the caller uses
        // this to tell a non-staff editor why their save didn't fully match
        // what they asked for, rather than silently reporting success.
        $preservedPastDates = [];
        $rejectedPastDates = [];

        // Days in those two lists are named in the EVENT's timezone — the day
        // the organizer picked, not the UTC day the row is stored under (an
        // evening show in Los Angeles is stored on the next UTC day). The
        // creation guard below tests by the same local day.
        $tz = $request->timezone ?? $event->timezone ?? 'UTC';
        $localDay = fn ($d) => Carbon::parse((string) $d, 'UTC')->setTimezone($tz)->toDateString();

        DB::transaction(function () use ($request, $event, $previousShowtype, $targetDates, $showtypeChanged, $oldDates, $oldTickets, $tz, $localDay, &$preservedPastDates, &$rejectedPastDates) {
            // --- Remove obsolete shows in ONE pass. This was an N+1 (a delete
            //     per show plus a delete per show's tickets), which made saving a
            //     large recurring schedule crawl. A show-type switch wipes all;
            //     otherwise only shows whose date left the target set are dropped. ---
            $idsToDelete = $showtypeChanged
                ? $event->shows()->pluck('id')
                : $event->shows()->whereNotIn('date', $targetDates)->pluck('id');

            // A show that's already happened is a historical record, not a
            // schedule entry to be edited away — only staff can remove one
            // (matches isModerator()'s use everywhere else in event-editing
            // permissions, e.g. EventPolicy::manage()). auth()->user() is
            // safe here: this always runs within the same authenticated
            // request as the web wizard's or MCP's own call, never a queued
            // job or console context.
            //
            // Only rows of the dated types ARE records, though. An always-
            // available or limited event carries a single sentinel row whose
            // date is just its end date (targetDatesFor) — nothing happened
            // on it. Protecting an expired sentinel kept it as a phantom
            // "past performance" when the event was converted to dates, and
            // warned the organizer about a date they never chose.
            if (! auth()->user()?->isModerator() && in_array($previousShowtype, ['s', 'o'], true)) {
                $pastShows = $event->shows()->where('date', '<', now())->get(['id', 'date']);
                $protectedIds = $idsToDelete->intersect($pastShows->pluck('id'));

                if ($protectedIds->isNotEmpty()) {
                    $preservedPastDates = $pastShows->whereIn('id', $protectedIds)
                        ->pluck('date')
                        ->map($localDay)
                        ->unique()->sort()->values()->all();
                }

                $idsToDelete = $idsToDelete->diff($protectedIds);
            }

            self::deleteShowsByIds($idsToDelete);

            // --- Create the missing shows in bulk (was an updateOrCreate per
            //     date). Only dates without a surviving show are inserted, so
            //     re-saving an unchanged schedule writes nothing. Chunked so an
            //     enormous list can never exceed the DB's bind-parameter limit. ---
            $survivingDates = $event->shows()->pluck('date')->map(fn ($d) => (string) $d)->all();
            $datesToCreate = collect($targetDates)
                ->reject(fn ($d) => in_array($d, $survivingDates, true))
                ->values();

            // The mirror of the deletion guard above: a non-moderator may not
            // INVENT a show that already happened, any more than they may
            // erase one. Only NEW dates are tested — the surviving ones were
            // filtered out just above, so a running event's real past
            // occurrences pass through untouched.
            //
            // This matters most on the ongoing editor, which regenerates the
            // whole date set from (days, start, end): ticking an extra weekday
            // on an event whose run has ended would otherwise create real show
            // rows for days it never ran — and the deletion guard would then
            // make them permanent. The MCP tool rejects these earlier with its
            // own message; this is the floor under every other write path.
            //
            // "Past" is by calendar day where the event is, NOT by the minute
            // in UTC. The wizard pins every show to 12:00 local
            // (dateUtils.formatDateForAPI) and lets today be picked, so a
            // to-the-second test against now() refused tonight's show to
            // anyone saving after local noon — and, because the obsolete
            // shows were already deleted above, could leave the event with
            // none. Today is today until midnight where the event is. Same
            // rule and basis as the MCP tool's own pre-check.
            //
            // Sentinel rows are exempt here too: setting an always-available
            // event's end date to yesterday is how its organizer closes it,
            // and refusing that row (after the old one was already deleted)
            // left the event with no shows and its ticket tiers gone.
            if (! auth()->user()?->isModerator() && in_array($request->showtype, ['s', 'o'], true)) {
                $today = Carbon::now($tz)->toDateString();
                [$current, $past] = $datesToCreate->partition(fn ($d) => $localDay($d) >= $today);

                $rejectedPastDates = $past->map($localDay)->unique()->sort()->values()->all();

                $datesToCreate = $current->values();
            }

            if ($datesToCreate->isNotEmpty()) {
                $now = now();
                $datesToCreate
                    ->map(fn ($d) => [
                        'event_id' => $event->id,
                        'date' => $d,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->chunk(self::INSERT_CHUNK)
                    ->each(fn ($chunk) => self::insert($chunk->values()->all()));

                // Copy the ticket tiers onto the freshly created shows in bulk.
                if ($oldTickets && $oldTickets->isNotEmpty()) {
                    $newShowIds = self::withoutGlobalScope(DateScope::class)
                        ->where('event_id', $event->id)
                        ->whereIn('date', $datesToCreate->all())
                        ->pluck('id');
                    self::copyTicketsToShows($oldTickets, $newShowIds);
                }
            }

            // Log date changes only for published events
            if ($event->status === 'p') {
                self::logDateChanges($event, $oldDates);
            }

            // Update the event's showtype to the new value
            $event->update(['showtype' => $request->showtype]);
        });

        // Reindex in Elasticsearch AFTER the transaction commits — an external
        // side effect must not run inside the DB transaction.
        if ($event->shouldBeSearchable()) {
            $event->searchable();
        }

        // Two lists, two different refusals: dates that already happened and
        // were KEPT against the caller's wishes, and dates in the past the
        // caller tried to create and did not get. Both are reported rather
        // than applied silently — see UpdateEventAction's docblock.
        return ['preserved' => $preservedPastDates, 'rejected' => $rejectedPastDates];
    }

    private static function logDateChanges($event, array $oldDates): void
    {
        $newDates = $event->shows()->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->sort()->values()->toArray();

        $added = array_values(array_diff($newDates, $oldDates));
        $removed = array_values(array_diff($oldDates, $newDates));
        $userId = auth()->id();

        if (! empty($added)) {
            ShowChangeLog::create([
                'event_id' => $event->id,
                'user_id' => $userId,
                'action' => 'added',
                'dates' => $added,
            ]);
        }

        if (! empty($removed)) {
            ShowChangeLog::create([
                'event_id' => $event->id,
                'user_id' => $userId,
                'action' => 'removed',
                'dates' => $removed,
            ]);
        }
    }

    /**
     * The normalised set of show datetimes a save should end with, as UTC
     * "Y-m-d H:i:s". Specific ('s') and ongoing ('o') events use the supplied
     * date list; always ('a') and limited ('l') collapse to one sentinel show.
     *
     * @return array<int, string>
     */
    private static function targetDatesFor($request): array
    {
        if ($request->showtype === 's' || $request->showtype === 'o') {
            return collect($request->dateArray ?? [])
                ->map(fn ($d) => Carbon::parse($d, 'UTC')->format('Y-m-d H:i:s'))
                ->unique()
                ->values()
                ->all();
        }

        if ($request->showtype === 'a') {
            $end = isset($request->always_config) && $request->always_config['endDate']
                ? Carbon::parse($request->always_config['endDate'])->format('Y-m-d H:i:s')
                : Carbon::now()->addMonths(6)->format('Y-m-d H:i:s');

            return [$end];
        }

        if ($request->showtype === 'l') {
            return [Carbon::now()->addMonths(6)->format('Y-m-d H:i:s')];
        }

        return [];
    }

    /**
     * Hard-delete the given shows and their (polymorphic) tickets in two
     * queries instead of iterating row by row. No-op on an empty set.
     */
    private static function deleteShowsByIds($ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        Ticket::where('ticket_type', self::class)->whereIn('ticket_id', $ids)->delete();
        self::withoutGlobalScope(DateScope::class)->whereIn('id', $ids)->delete();
    }

    /**
     * Bulk-copy each ticket tier onto every newly created show.
     *
     * This is the SECOND writer of tickets, alongside Ticket::handleTickets(),
     * and it needs the same protection for the same reasons — a point missed
     * when handleTickets was hardened, so it kept a plain insert() for a while
     * after the other path stopped using one.
     *
     * Two ways a plain insert breaks here, both now impossible since
     * (ticket_type, ticket_id, name) is unique:
     *
     *  - Two saves adding dates at the same time each build rows for shows the
     *    other just created. Before the constraint that silently duplicated;
     *    with it, one of them would 500 instead.
     *  - $oldTickets is read from an existing show, so if THAT show carried
     *    duplicate names (as 148 shows in production did), every new date
     *    inherited the duplicates — which is how a data bug spread itself
     *    across a schedule every time someone added dates.
     *
     * The upsert is what makes both cases safe. keyBy('name') is an efficiency
     * measure on top, not a second guard: a duplicated source tier would
     * otherwise build two rows per date and send both, which on a 4,000-date
     * schedule is 4,000 redundant rows for the database to collapse one at a
     * time. Matching columns are kept in step with the unique index by
     * tests/Feature/Models/TicketDuplicationTest.php.
     */
    private static function copyTicketsToShows($oldTickets, $showIds): void
    {
        $now = now();
        $rows = [];

        // Same collapse handleTickets does. The upsert below would fold these
        // anyway; doing it here keeps the payload the size it should be.
        $tiers = collect($oldTickets)->keyBy('name');

        foreach ($showIds as $showId) {
            foreach ($tiers as $ticket) {
                $rows[] = [
                    'ticket_type' => self::class,
                    'ticket_id' => $showId,
                    'name' => $ticket->name,
                    'description' => $ticket->description,
                    'currency' => $ticket->currency,
                    'ticket_price' => $ticket->ticket_price,
                    'type' => $ticket->type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        collect($rows)
            ->chunk(self::INSERT_CHUNK)
            ->each(fn ($chunk) => Ticket::upsert(
                $chunk->values()->all(),
                ['ticket_type', 'ticket_id', 'name'],
                ['description', 'currency', 'ticket_price', 'updated_at'],
            ));
    }

    /**
     * Get the showtimes and price range to update the event model
     *
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public static function updateEvent($request, Event $event, ?string $previousShowtype = null)
    {
        // Determine show type
        $type = self::determineShowType($request);

        // saveShows() runs first and already wrote the new type onto $event, so
        // comparing against $event->showtype here can only ever say "unchanged".
        // Callers that know the type they started with pass it in; anyone else
        // falls back to the (post-save) value, matching the old behaviour.
        $previousShowtype = func_num_args() >= 3 ? $previousShowtype : $event->showtype;

        // Prepare update data
        $updateData = [
            'showtype' => $type,
        ];

        // show_times and embargo_date are plain event fields, not schedule data,
        // so carry them over only when the caller actually sent them. Reading
        // them unconditionally meant every PARTIAL schedule edit (the MCP tools
        // send just the fields being changed) blanked the event's showtimes text,
        // because an absent key reads as null. The web wizard's Dates step always
        // sends both, so clearing them from the wizard still works.
        if (self::requestHas($request, 'show_times')) {
            $updateData['show_times'] = $request->show_times;
        }

        if (self::requestHas($request, 'embargo_date')) {
            $updateData['embargo_date'] = $request->embargo_date;
        }

        // Include timezone if provided
        if ($request->timezone) {
            $updateData['timezone'] = $request->timezone;
        }

        // Only (re)compute the schedule metadata — closingDate, start_date, and
        // the stored recurrence rule — when the caller actually supplied schedule
        // data or changed the show type. A bare showtype echo (e.g. a
        // show_times-only edit that still includes showtype) must NOT recompute
        // them: doing so would null the saved recurrence rule (and, for an
        // always-available event, reset closingDate to a default six months
        // out) even though the schedule is unchanged, silently corrupting the
        // event. Each recipe only counts for
        // its own show type, too — buildShowtypeConfig and calculateLastDate below
        // read ongoing_config only for 'o' and always_config only for 'a', so
        // treating a mismatched one as "schedule supplied" recomputed closingDate
        // and nulled the recurrence rule for a schedule nobody had changed.
        $scheduleProvided = isset($request->dateArray)
            || ($type === 'o' && isset($request->ongoing_config))
            || ($type === 'a' && isset($request->always_config));

        if ($scheduleProvided || $type !== $previousShowtype) {
            $updateData['closingDate'] = self::calculateLastDate($event, $type, $request);

            // Store start date for ongoing events.
            if ($type === 'o' && isset($request->ongoing_config) && isset($request->ongoing_config['startDate'])) {
                $updateData['start_date'] = $request->ongoing_config['startDate'];
            } else {
                $updateData['start_date'] = null;
            }

            // Persist the rule that generated the shows (M11) so we can read it
            // back verbatim on edit instead of reverse-engineering from shows.
            $updateData['showtype_config'] = self::buildShowtypeConfig($type, $request);
        }

        // Update the event
        $event->update($updateData);

        // Force reindex the event in Elasticsearch
        if ($event->shouldBeSearchable()) {
            $event->searchable();
        }
    }

    /**
     * Apply a requested embargo change — with or without a schedule.
     *
     * Only when the caller actually supplied embargo_date: inferring "no
     * embargo" from an absent key published an embargoed event immediately
     * (and dropped its date) on any unrelated partial edit. Clearing stays an
     * explicit act: send embargo_date=null.
     *
     * This used to live inside updateEvent(), which only runs when the save
     * carries a showtype — so an embargo_date sent on its own (the MCP tool,
     * or a direct POST) was mass-assigned by UpdateEventAction and never
     * examined: on a finished run it was stored with no refusal and no
     * report, and on an upcoming one the status never flipped. Callers run
     * this AFTER the schedule has been saved, so the guard sees the closing
     * date this save produced.
     *
     * Returns true when the embargo was refused (and the date cleared).
     */
    public static function applyEmbargo($request, Event $event): bool
    {
        if (! self::requestHas($request, 'embargo_date')) {
            return false;
        }

        if ($event->status === 'e' && ! $request->embargo_date) {
            $event->update(['status' => 'p']);

            if ($event->shouldBeSearchable()) {
                $event->searchable();
            }

            return false;
        }

        if ($event->status !== 'p' || ! $request->embargo_date) {
            return false;
        }

        // Not on a run that has already ended. Published → embargoed →
        // published is how an event announces itself: clearing the embargo
        // fires newEventFromFollowedOrganizer, mailing every follower of the
        // organizer. The one-time claim keys on events.organizer_notified_at,
        // added in Aug 2026 with no backfill — so every older event is
        // unmarked and the claim succeeds again. While finished events were
        // locked this was unreachable; now that they are editable, the whole
        // archive would be a re-announcement away. Staff keep it for
        // legitimate re-launches.
        //
        // A null closingDate does NOT prove the run is upcoming. On a DRAFT
        // it means "no schedule yet" — but this only runs for an already-
        // published event, where it means we cannot tell when the run ends,
        // and the permissive reading would hand the whole announcement path
        // back to any organizer whose event happens to have one. Refuse
        // unless we can positively see a future closing date.
        $closing = $event->closingDate;
        $hasEnded = $closing === null || Carbon::parse((string) $closing)->isPast();

        if (! $hasEnded || auth()->user()?->isModerator()) {
            $event->update(['status' => 'e']);
            $event->unsearchable();

            return false;
        }

        // Refused — so the date must not be stored either. Left in place it
        // sat on a published event as an embargo that never took effect, and
        // the wizard's Dates step resends event.embargo_date on every save,
        // so once that date had passed, `after:now` 422'd unrelated edits
        // until the organizer cleared an embargo they never got. Cleared
        // rather than skipped: UpdateEventAction's mass-assign has already
        // written it by the time we run.
        $event->update(['embargo_date' => null]);

        return true;
    }

    private static function determineShowType($request): string
    {
        return $request->showtype ?? 's';
    }

    /**
     * Whether the caller actually supplied a field, as opposed to it merely
     * reading as null. Distinguishing the two is what keeps a partial update
     * from blanking fields it never mentioned. Tolerates the plain objects some
     * callers hand the static save helpers alongside real Requests.
     */
    private static function requestHas($request, string $key): bool
    {
        return $request instanceof \Illuminate\Http\Request
            ? $request->has($key)
            : property_exists($request, $key);
    }

    /**
     * Build the showtype_config payload from a request.
     *
     * Returns null for showtypes whose "rule" is just the list of dates themselves
     * (specific 's' and limited 'l'). For 'o' and 'a', returns the inputs the user
     * actually chose so the form can rehydrate exactly on edit.
     */
    private static function buildShowtypeConfig(string $type, $request): ?array
    {
        if ($type === 'o' && isset($request->ongoing_config)) {
            $cfg = $request->ongoing_config;

            return array_filter([
                'type' => 'ongoing',
                'days_of_week' => isset($cfg['daysOfWeek']) ? array_values(array_map('intval', $cfg['daysOfWeek'])) : null,
                'start_date' => $cfg['startDate'] ?? null,
                'end_date' => $cfg['endDate'] ?? null,
            ], fn ($v) => $v !== null);
        }

        if ($type === 'a' && isset($request->always_config)) {
            return array_filter([
                'type' => 'always',
                'end_date' => $request->always_config['endDate'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return null;
    }

    private static function calculateLastDate(Event $event, string $type, $request = null): string
    {
        // Get the event's timezone, default to UTC
        $timezone = $request->timezone ?? $event->timezone ?? 'UTC';

        // The sentinel types have no real performances: their one show row IS
        // the end date (targetDatesFor), so the config is the schedule.
        if ($type === 'a') {
            // For 'always available' shows, check if there's a specific end date in the configuration
            if ($request && isset($request->always_config) && $request->always_config['endDate']) {
                // Parse in UTC (frontend already converted)
                return Carbon::parse($request->always_config['endDate'], 'UTC')->endOfDay()->format('Y-m-d H:i:s');
            }

            // Default for always shows: 6 months from now in the event's timezone
            return Carbon::now($timezone)->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
        }

        if ($type === 'l') {
            // For 'limited availability' shows
            return Carbon::now($timezone)->addMonths(6)->endOfDay()->format('Y-m-d H:i:s');
        }

        // Specific and ongoing: the end of the last show that actually exists
        // after this save — never a config field. ongoing_config.endDate is
        // an INPUT to the recurrence that generated the shows, and taking
        // closingDate straight from it let one field revive a finished run:
        // an organizer could post endDate two years out with no future show
        // behind it and be back in listings, the very thing dropping
        // closingDate from the request rules was meant to stop. Derived from
        // the rows, the two cannot disagree, and the stored recurrence rule
        // (showtype_config) still says what the organizer asked for.
        $lastShow = $event->shows()->withoutGlobalScope(DateScope::class)->max('date');

        if ($lastShow) {
            return Carbon::parse((string) $lastShow, 'UTC')->endOfDay()->format('Y-m-d H:i:s');
        }

        // No shows at all (a draft mid-creation): end of today.
        return Carbon::now($timezone)->endOfDay()->format('Y-m-d H:i:s');
    }
}
