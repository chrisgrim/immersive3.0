<?php

namespace App\Mcp\Tools;

use App\Actions\Events\CheckDuplicateEventNames;
use App\Actions\Events\UpdateEventAction;
use App\Mcp\Tools\Concerns\BuildsSyntheticRequests;
use App\Mcp\Tools\Concerns\FormatsEvents;
use App\Models\Event;
use App\Scopes\LatestPublishedFirstScope;
use App\Support\Currency;
use App\Support\RecurringDates;
use App\Support\Validation\EventUpdateRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Partially update an event: send only the fields you are changing. Works on any event you can manage — for moderators and admins that is EVERY event on the platform, not just your own organizers (find slugs with list-all-events). Uses the same validation as the website. All dates are UTC "Y-m-d H:i:s". Set showtype + dates before or together with tickets. Publishing is impossible from here — use submit-event-for-review when the draft is complete.')]
class UpdateEvent extends Tool
{
    use BuildsSyntheticRequests;
    use FormatsEvents;

    /**
     * Input keys the MCP surface never accepts: status transitions have
     * dedicated flows (submit/approve) and file uploads have attach tools.
     */
    protected const STRIPPED_KEYS = [
        'status', 'images', 'ranks', 'currentImages', 'deletedImages', 'archived', 'closingDate', 'published_at', 'rank', 'slug',
    ];

    /**
     * What to do instead, per stripped key. Dropping these silently is what made
     * a rejected call look like the platform losing the edit: a caller trying to
     * extend a run by setting closingDate had its only field removed before
     * validation and got the bare "No updatable fields were provided".
     */
    protected const STRIPPED_KEY_ALTERNATIVES = [
        'closingDate' => 'closingDate is derived from the schedule — change the shows instead (dateArray for showtype "s", ongoing_config for "o", always_config for "a") and the closing date follows',
        'status' => 'status changes go through submit-event-for-review; an admin approves or rejects from there',
        'images' => 'use attach-event-image to add or replace an image',
        'ranks' => 'use attach-event-image, which takes the rank directly',
        'currentImages' => 'use attach-event-image to add or replace an image',
        'deletedImages' => 'use attach-event-image to replace the image at a rank',
        'archived' => 'archiving is not available from here',
        'published_at' => 'publishing is not available from here — use submit-event-for-review',
        'rank' => 'rank is set by admins on the website',
        'slug' => 'the slug is fixed once the event is created',
    ];

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $stripped = array_values(array_intersect(
            array_keys($request->all()),
            self::STRIPPED_KEYS
        ));

        $input = collect($request->all())
            ->except(array_merge(self::STRIPPED_KEYS, ['event_slug', 'acknowledge_duplicate', 'confirm_live_edit', 'confirm_schedule_replace']))
            ->all();

        // The videos array is accepted as a real array here; the shared action
        // expects the web wizard's JSON-encoded string.
        if (isset($input['videos']) && is_array($input['videos'])) {
            $input['videos'] = json_encode($input['videos']);
        }

        // Currency is stored as an ISO 4217 code. A client sending a symbol
        // out of habit ("$", "A$", "₩") gets it mapped to the code rather
        // than bounced; anything unrecognised fails validation with a message
        // that says what shape is wanted.
        if (isset($input['tickets']) && is_array($input['tickets'])) {
            foreach ($input['tickets'] as $i => $tier) {
                if (is_array($tier) && isset($tier['currency'])) {
                    $input['tickets'][$i]['currency'] = Currency::normalize($tier['currency']);
                }
            }
        }

        $slugValidated = $request->validate(['event_slug' => 'required|string']);

        $event = Event::withoutGlobalScope(LatestPublishedFirstScope::class)
            ->where('slug', $slugValidated['event_slug'])
            ->first();

        // One message whether the slug is unknown or the event is someone
        // else's — see GetEvent.
        if (! $event || ! $user->can('manage', $event)) {
            return Response::error('No event with that slug that you can edit. Slugs come from list-my-events.');
        }

        // A tier sent without a currency takes the currency of the event's
        // location country — a Singapore event priced in SGD without the
        // client having to know that — and USD for remote events. This is
        // the same default the web wizard applies (tickets.vue).
        if (isset($input['tickets']) && is_array($input['tickets'])) {
            $default = Currency::forCountry($event->location?->country) ?? Currency::DEFAULT;
            foreach ($input['tickets'] as $i => $tier) {
                if (is_array($tier) && ($tier['currency'] ?? '') === '') {
                    $input['tickets'][$i]['currency'] = $default;
                }
            }
        }

        // A finished run is editable — see the note in HostEventController
        // where the matching web-wizard lock used to be. What stops history
        // being rewritten is Show::saveShows() refusing to delete an
        // already-passed show for a non-moderator, not a lock on the event.

        // Site rule: once submitted, an event is locked until an admin
        // approves or rejects it (moderators can still edit).
        if ($event->status === 'r' && ! $user->isModerator()) {
            return Response::error('This event is under review and cannot be edited until an admin approves or rejects it.');
        }

        // A specific-dates or ongoing event must keep at least one show — the web
        // wizard enforces this too. "Remove all the dates" (an empty dateArray)
        // would otherwise fail with an opaque validation error; return an
        // actionable one so the client asks what should replace the old dates.
        // Exception: an ongoing event sending an (empty) dateArray alongside a
        // real ongoing_config is fine — the recurrence expands into the dates
        // below, so let it through rather than rejecting a valid recipe.
        $resolvedShowtypeRaw = $input['showtype'] ?? $event->showtype;
        $hasOngoingRecipe = $resolvedShowtypeRaw === 'o' && ! empty($input['ongoing_config']);
        if (array_key_exists('dateArray', $input)
            && is_array($input['dateArray'])
            && count($input['dateArray']) === 0
            && in_array($resolvedShowtypeRaw, ['s', 'o'], true)
            && ! $hasOngoingRecipe) {
            return Response::json([
                'error' => 'empty_schedule',
                'message' => 'An event needs at least one date — it cannot have an empty schedule. Ask the user which dates should replace the current ones, then send those.',
            ]);
        }

        $validator = Validator::make(
            $input,
            collect(EventUpdateRules::rules())->except(self::STRIPPED_KEYS)->all(),
            EventUpdateRules::messages(),
            EventUpdateRules::attributes()
        );

        if ($validator->fails()) {
            return Response::json([
                'error' => 'validation_failed',
                'errors' => $validator->errors(),
            ]);
        }

        $validated = $validator->validated();

        if (empty($validated)) {
            // Say which fields were dropped and what replaces them, rather than
            // implying nothing was sent — the caller did send something.
            if ($stripped !== []) {
                return Response::json([
                    'error' => 'fields_not_editable_here',
                    'message' => 'None of the fields you sent can be set through this tool: '.implode(', ', $stripped).'. '
                        .implode('. ', array_map(
                            fn ($key) => ucfirst(self::STRIPPED_KEY_ALTERNATIVES[$key] ?? $key.' is not editable here'),
                            $stripped
                        )).'.',
                    'rejected_fields' => $stripped,
                ]);
            }

            return Response::error('No updatable fields were provided.');
        }

        // A malformed timezone would otherwise throw uncaught deep in Carbon
        // (recurrence expansion, the past-date guard, day collapsing) and 500.
        // Reject it here as a clean validation error. DateTimeZone accepts exactly
        // the identifiers Carbon can parse, so this rejects only genuinely-bad
        // values, not BC aliases.
        if (isset($validated['timezone'])) {
            try {
                new \DateTimeZone($validated['timezone']);
            } catch (\Exception $e) {
                return Response::json([
                    'error' => 'validation_failed',
                    'errors' => ['timezone' => ['That is not a recognized timezone. Use an IANA name like "America/New_York".']],
                ]);
            }
        }

        // A schedule change only takes effect alongside `showtype` — that is what
        // drives the shared save-shows path. A client that sends dates but omits
        // showtype (common once the event already has one) would otherwise get a
        // silent no-op while the tool still reports success. Default to the
        // event's current showtype so the change actually applies.
        $changesSchedule = isset($validated['dateArray'])
            || isset($validated['ongoing_config'])
            || isset($validated['always_config']);

        // ...but only a show type this tool understands can be defaulted in.
        // An event with no showtype yet has nothing to default to (the old
        // `!== null` check just skipped the assignment, so the schedule was
        // dropped and the tool still said "Event updated"). A legacy 'l'
        // (limited) event is worse: 'l' would reach Show::saveShows, whose 'l'
        // branch throws away the supplied dates and collapses the schedule to a
        // single sentinel show six months out. The website silently rewrites 'l'
        // to 's' when the wizard opens one; make the caller choose explicitly
        // instead, because here it is a live event being rewritten.
        if ($changesSchedule
            && ! isset($validated['showtype'])
            && ! in_array($event->showtype, ['s', 'o', 'a'], true)) {
            return Response::json([
                'error' => 'showtype_required',
                'message' => $event->showtype === null
                    ? 'This event has no show type yet, so a schedule cannot be saved. Send showtype ("s" for a list of specific dates, "o" for a recurring run, "a" for always available) together with the dates.'
                    : 'This event still uses the retired "'.$event->showtype.'" (limited) show type, which has no schedule editor — sending dates without a showtype would silently discard them. Send an explicit showtype ("s" for a list of specific dates, "o" for a recurring run, "a" for always available) together with the schedule. The website performs the same conversion when one of these events is opened in the wizard. Note that setting showtype replaces the existing shows, so include every date you want to keep.',
                'current_showtype' => $event->showtype,
            ]);
        }

        if ($changesSchedule && ! isset($validated['showtype'])) {
            $validated['showtype'] = $event->showtype;
        }

        $resolvedShowtype = $validated['showtype'] ?? $event->showtype;

        // A schedule recipe only applies to its own show type: Show::updateEvent
        // reads ongoing_config only for 'o' and always_config only for 'a'. A
        // mismatched config used to be accepted and then quietly dropped — the
        // tool answered "Event updated." with the field listed in updated_fields
        // while the schedule was untouched, and for an always-available event it
        // also reset closingDate to a default six months out. Name the field that
        // actually applies instead.
        [$sentConfig, $configType] = match (true) {
            isset($validated['ongoing_config']) && $resolvedShowtype !== 'o' => ['ongoing_config', 'o'],
            isset($validated['always_config']) && $resolvedShowtype !== 'a' => ['always_config', 'a'],
            default => [null, null],
        };

        if ($sentConfig !== null) {
            $field = match ($resolvedShowtype) {
                's' => 'dateArray (the full list of show dates)',
                'o' => 'ongoing_config',
                'a' => 'always_config',
                default => 'dateArray',
            };

            return Response::json([
                'error' => 'showtype_mismatch',
                'message' => "This event's show type is \"{$resolvedShowtype}\" (".$this->showtypeLabel($resolvedShowtype)."), and {$sentConfig} only applies to show type \"{$configType}\". To change this event's schedule, send {$field}. To convert the event to \"{$configType}\" instead, send showtype=\"{$configType}\" in the same call — that REPLACES the existing shows, so confirm it with the user first.",
                'current_showtype' => $resolvedShowtype,
                'use_field' => $field,
            ]);
        }

        // Server-side recurrence expansion: for an ongoing ('o') event the caller
        // may send just the ongoing_config recipe (startDate/endDate/daysOfWeek)
        // and let us materialise the concrete show days — deterministic date math
        // instead of the model enumerating (and miscounting) every occurrence. An
        // explicit dateArray still wins, so a caller can hand-pick dates or carve
        // out exceptions by sending the full list instead.
        if ($resolvedShowtype === 'o'
            && empty($validated['dateArray'])
            && isset($validated['ongoing_config'])) {
            $cfg = $validated['ongoing_config'];
            if (! empty($cfg['startDate']) && ! empty($cfg['endDate']) && ! empty($cfg['daysOfWeek'])) {
                $tz = $validated['timezone'] ?? $event->timezone ?? 'UTC';

                try {
                    $validated['dateArray'] = RecurringDates::expand($cfg['daysOfWeek'], $cfg['startDate'], $cfg['endDate'], $tz);
                } catch (\RangeException $e) {
                    return Response::json([
                        'error' => 'schedule_too_long',
                        'message' => 'That recurrence would create more than '.RecurringDates::MAX_OCCURRENCES.' shows — almost always a wrong end year. Check the end date with the user and try again.',
                    ]);
                }
            }
        }

        // A specific/ongoing schedule must resolve to at least one show. Reject an
        // empty schedule when the caller is actually setting it — either a
        // recurrence that came out empty (ongoing_config given), or a switch INTO
        // s/o with no dates (which would flip showtype while the old shows survive,
        // leaving type and shows describing different schedules). A bare showtype
        // echo on the SAME type is left alone: saveShows treats it as a harmless
        // no-op and preserves the existing shows and recurrence rule. (A show-less
        // draft that stays its current type with no dates is a normal work-in-
        // progress, caught later at submit — not corruption — so it is allowed.)
        $scheduleEmpty = in_array($resolvedShowtype, ['s', 'o'], true) && empty($validated['dateArray']);
        $showtypeChanging = isset($validated['showtype']) && $validated['showtype'] !== $event->showtype;
        if ($scheduleEmpty && (isset($validated['ongoing_config']) || $showtypeChanging)) {
            return Response::json([
                'error' => 'empty_schedule',
                'message' => 'A specific-date or ongoing event needs at least one date. Provide the dates — or, for an ongoing run, a complete ongoing_config (startDate, endDate, daysOfWeek) the server can expand — and try again.',
            ]);
        }

        // One show per calendar day: the web wizard's date picker can't select a
        // day twice, so collapse any datetimes that land on the same day (in the
        // event's timezone) to a single show. The time-of-day belongs in the
        // free-text show_times field, not in duplicate shows.
        if (isset($validated['dateArray']) && is_array($validated['dateArray'])) {
            $tz = $validated['timezone'] ?? $event->timezone ?? 'UTC';
            $validated['dateArray'] = $this->collapseToOneShowPerDay($validated['dateArray'], $tz);
        }

        // Past-date guard, mirroring the web calendar's lookback policy: admins
        // may backfill HISTORICAL shows (the web date picker lets them pick up to
        // 10 years back), while everyone else is limited to today or later. Only
        // NEW dates before that floor are rejected — for a regular user any past
        // day, for an admin only a day more than a decade back, which is almost
        // always a wrong year rather than an intended historical date. Existing
        // shows on a past day are always preserved (a running event legitimately
        // has past occurrences); offenders are named so the caller can correct them.
        if (! empty($validated['dateArray']) && is_array($validated['dateArray'])) {
            $tz = $validated['timezone'] ?? $event->timezone ?? 'UTC';
            $floor = $user->isAdmin()
                ? \Illuminate\Support\Carbon::now($tz)->subYears(10)->toDateString()
                : \Illuminate\Support\Carbon::now($tz)->toDateString();
            $existing = $event->shows()->pluck('date')->map(fn ($d) => (string) $d)->all();
            $tooFarBack = [];
            foreach ($validated['dateArray'] as $datetime) {
                if (in_array($datetime, $existing, true)) {
                    continue; // already-saved show — leave it be
                }
                try {
                    $day = \Illuminate\Support\Carbon::parse($datetime, 'UTC')->setTimezone($tz)->toDateString();
                } catch (\Throwable $e) {
                    continue; // malformed values are handled by field validation
                }
                if ($day < $floor) {
                    $tooFarBack[] = $day;
                }
            }
            if (! empty($tooFarBack)) {
                $tooFarBack = array_values(array_unique($tooFarBack));
                sort($tooFarBack);

                $limit = $user->isAdmin()
                    ? 'more than 10 years in the past'
                    : 'in the past';

                return Response::json([
                    'error' => 'past_dates',
                    'message' => 'These dates are '.$limit.' ('.$tz.'): '.implode(', ', $tooFarBack).'. That is almost always a wrong year or a misresolved relative date — check it with the user and try again.',
                    'past_dates' => $tooFarBack,
                ]);
            }
        }

        // Destructive-replace guard: deleting existing shows (a showtype switch,
        // or a dateArray that drops dates) is irreversible — shows are hard-
        // deleted. Require an explicit confirmation first so a client can never
        // silently wipe a schedule.
        $showsRemoved = $this->showsThatWouldBeRemoved($event, $validated);
        if ($showsRemoved > 0 && ! $request->get('confirm_schedule_replace')) {
            return Response::json([
                'action_required' => 'confirm_schedule_replace',
                'message' => "This change deletes {$showsRemoved} existing show(s), which cannot be undone. Show the user exactly what will be removed, get their explicit confirmation, then call this tool again with the same arguments plus confirm_schedule_replace=true.",
                'event' => $this->eventSummary($event),
                'existing_shows' => $event->shows()->count(),
                'shows_to_remove' => $showsRemoved,
            ]);
        }

        // Duplicate-name guard, same as the web flow.
        if (isset($validated['name']) && $validated['name'] !== $event->name && ! $request->get('acknowledge_duplicate')) {
            $duplicates = app(CheckDuplicateEventNames::class)->handle($validated['name'], $event->id);
            if ($duplicates) {
                return Response::json([
                    'error' => 'duplicate_name',
                    'message' => 'An event with a similar name already exists. Ask the user how to proceed; to use the name anyway, call this tool again with acknowledge_duplicate=true.',
                    'duplicateEvents' => $duplicates,
                ]);
            }
        }

        // Tickets attach to shows — make the dependency explicit for agents.
        if (isset($validated['tickets']) && ! isset($validated['showtype']) && $event->shows()->doesntExist()) {
            return Response::error('This event has no dates yet. Set showtype and the schedule (dateArray / ongoing_config / always_config) before or together with tickets.');
        }

        // The web wizard computes hasLocation client-side from the attendance
        // type; mirror that so in-person/remote state stays consistent.
        if (isset($validated['attendance_type_id']) && ! isset($validated['hasLocation'])) {
            $validated['hasLocation'] = $validated['attendance_type_id'] == 1;
        }

        // The wizard adds advisory chips automatically from the yes/no answers
        // ("Sexual Content"/"No Sexual Content", "Wheelchair Accessible"/...).
        // Mirror that so MCP events carry the same advisory data as wizard ones.
        $this->applyAutoChips($validated, $event);

        // Editing a LIVE (published/embargoed) event applies immediately, so
        // require an explicit confirmation after showing the user what changes.
        if (in_array($event->status, ['p', 'e'], true) && ! $request->get('confirm_live_edit')) {
            return Response::json([
                'action_required' => 'confirm_live_edit',
                'message' => 'This event is LIVE — these changes take effect immediately, with no review step. Show the user exactly what will change (current vs proposed below), get their explicit confirmation, then call this tool again with the same arguments plus confirm_live_edit=true.',
                'event' => $this->eventSummary($event),
                'proposed_changes' => $this->changePreview($event, $validated),
            ]);
        }

        // The shared action mirrors the wizard, where `location` always arrives
        // in its own step: when location is present it skips the top-level
        // mass-assign. MCP clients may combine everything in one call, so apply
        // location separately from the other fields.
        $location = $validated['location'] ?? null;
        $rest = collect($validated)->except('location')->all();
        $preservedPastDates = [];
        $rejectedPastDates = [];
        $embargoRefused = false;

        if ($rest !== []) {
            $updateAction = app(UpdateEventAction::class);
            $event = $updateAction->handle(
                $event,
                $rest,
                $this->syntheticRequest($rest, $user)
            );
            $preservedPastDates = $updateAction->preservedPastDates;
            $rejectedPastDates = $updateAction->rejectedPastDates;
            $embargoRefused = $updateAction->embargoRefused;
        }

        if ($location !== null) {
            $event = app(UpdateEventAction::class)->handle(
                $event,
                ['location' => $location],
                $this->syntheticRequest(['location' => $location], $user)
            );
        }

        $readiness = $this->readiness($event->load([
            'shows.tickets', 'location', 'advisories', 'contentAdvisories', 'mobilityAdvisories',
            'contactLevels', 'interactive_level', 'category', 'genres', 'remotelocations', 'images', 'organizer',
        ]));

        // Keep the wizard step marker (packed into `status`) in sync so the
        // draft opens on the first unfinished step when viewed on the website.
        $this->syncWizardStep($event, $readiness);

        $payload = [
            'message' => 'Event updated.',
            'event' => $this->eventSummary($event),
            'updated_fields' => array_keys($validated),
            // The rest of the call applied, but these were dropped — saying so
            // beats letting the caller assume everything it sent took effect.
            ...($stripped === [] ? [] : ['ignored_fields' => $stripped]),
            // Only staff can remove a show that's already happened — Show::
            // saveShows() kept these instead of deleting them. Same reasoning
            // as ignored_fields: don't let the caller assume its requested
            // schedule was applied exactly as sent.
            ...($preservedPastDates === [] ? [] : ['preserved_past_dates' => $preservedPastDates]),
            // The mirror: dates in the past the caller asked to CREATE and did
            // not get. The pre-check above catches these first in the ordinary
            // case, so this is the floor under it — and when the floor is what
            // fired, "Event updated." with dateArray in updated_fields would
            // have the caller believe a show exists that does not.
            ...($rejectedPastDates === [] ? [] : ['rejected_past_dates' => $rejectedPastDates]),
            // A published event whose run has ended cannot be embargoed by a
            // non-moderator (lifting it again is how an event announces
            // itself to followers), and the date was not stored.
            ...($embargoRefused ? ['embargo_not_applied' => 'This event\'s run has already ended, so the embargo was refused and embargo_date was not stored. Add upcoming dates in the same call to embargo a new run; only admins and moderators can embargo a finished event.'] : []),
            'readiness' => $readiness,
            'missing' => collect($readiness)->reject(fn ($ok) => $ok)->keys()->values(),
        ];

        // Where the website's wizard will resume this draft (null for a
        // published/in-review event, which does not reopen in the wizard). The
        // wizard treats an event as remote unless it is in-person or already
        // has a location — mirror that so step 5 reports Remote vs Location.
        $isRemote = ! ($event->attendance_type_id === 1 || $event->hasLocation);
        if (($resumeAt = $this->webResumeStep($event->status, $isRemote)) !== null) {
            $payload['web_wizard_resumes_at'] = $resumeAt;
        }

        return Response::json($payload);
    }

    /**
     * Collapse a UTC datetime list to one entry per calendar day (in the given
     * timezone), keeping the first occurrence of each day. A show on EI
     * represents a day — the wizard's calendar cannot select the same day twice
     * and the time-of-day lives in the free-text show_times field — so multiple
     * datetimes on one day must not create multiple shows.
     *
     * @param  array<int, mixed>  $dateArray
     * @return array<int, mixed>
     */
    protected function collapseToOneShowPerDay(array $dateArray, string $timezone): array
    {
        $seen = [];
        $result = [];

        foreach ($dateArray as $datetime) {
            try {
                $day = \Illuminate\Support\Carbon::parse($datetime, 'UTC')->setTimezone($timezone)->toDateString();
            } catch (\Throwable $e) {
                // Leave a malformed value in place; validation rejects it upstream.
                $result[] = $datetime;

                continue;
            }

            if (! isset($seen[$day])) {
                $seen[$day] = true;
                $result[] = $datetime;
            }
        }

        return $result;
    }

    /**
     * How many existing shows a schedule change would delete. A showtype switch
     * wipes every show; a same-type dateArray drops shows whose datetime is not
     * in the new list. Shows are hard-deleted, so this gates irreversible
     * replacements behind a confirmation.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function showsThatWouldBeRemoved(Event $event, array $validated): int
    {
        if ($event->shows()->count() === 0) {
            return 0;
        }

        // Switching show type wipes all shows (and their tickets).
        if (isset($validated['showtype']) && $validated['showtype'] !== $event->showtype) {
            return $event->shows()->count();
        }

        $showtype = $validated['showtype'] ?? $event->showtype;

        // The sentinel types — 'a' (always available) and the retired 'l' — keep
        // exactly ONE show standing in for the closing date, so re-saving swaps
        // that sentinel for a new one and loses nothing. But an event of these
        // types that still carries a real multi-show schedule (legacy data)
        // collapses down to that single show, and this used to report 0 and skip
        // the confirmation entirely.
        if (in_array($showtype, ['a', 'l'], true)) {
            return max(0, $event->shows()->count() - 1);
        }

        // Same specific/ongoing type with a new date list: shows not in it drop.
        if (isset($validated['dateArray']) && in_array($showtype, ['s', 'o'], true)) {
            return $event->shows()->whereNotIn('date', $validated['dateArray'])->count();
        }

        return 0;
    }

    /**
     * Mirror the wizard's automatic advisory chips: answering the sexual-content
     * question adds "Sexual Content"/"No Sexual Content" to the content
     * advisories, and the wheelchair question adds "Wheelchair Accessible"/
     * "Not Wheelchair Accessible" to the mobility advisories (replacing the
     * opposite chip if the answer changed).
     */
    protected function applyAutoChips(array &$validated, Event $event): void
    {
        if (isset($validated['advisories']['sexual'])) {
            $validated['contentAdvisories'] = $this->mergeChip(
                $validated['contentAdvisories'] ?? $event->contentAdvisories->map(fn ($a) => ['name' => $a->name])->values()->all(),
                $validated['advisories']['sexual'] ? 'Sexual Content' : 'No Sexual Content',
                self::SEXUAL_CHIP_SLUGS
            );
        }

        if (isset($validated['wheelchairReady'])) {
            $validated['mobilityAdvisories'] = $this->mergeChip(
                $validated['mobilityAdvisories'] ?? $event->mobilityAdvisories->map(fn ($a) => ['name' => $a->name])->values()->all(),
                $validated['wheelchairReady'] ? 'Wheelchair Accessible' : 'Not Wheelchair Accessible',
                self::WHEELCHAIR_CHIP_SLUGS
            );
        }
    }

    protected function mergeChip(array $advisories, string $chipName, array $chipSlugs): array
    {
        $withoutChips = collect($advisories)
            ->reject(fn ($a) => in_array(\Illuminate\Support\Str::slug($a['name'] ?? ''), $chipSlugs))
            ->values();

        return $withoutChips->push(['name' => $chipName])->all();
    }

    /**
     * Current vs proposed values for a live-edit confirmation, so the AI can
     * show the user a concrete diff before anything is applied.
     */
    protected function changePreview(Event $event, array $validated): array
    {
        $event->loadMissing(['location', 'shows.tickets', 'genres', 'contentAdvisories', 'mobilityAdvisories', 'advisories', 'contactLevels', 'interactive_level', 'age_limits', 'remotelocations', 'videos']);

        $current = fn (string $key) => match ($key) {
            'location' => $event->location?->only(['venue', 'home', 'street', 'city', 'region', 'country', 'postal_code', 'latitude', 'longitude', 'hiddenLocationToggle', 'hiddenLocation']),
            'showtype' => $event->showtype,
            'dateArray' => $event->shows->pluck('date'),
            'ongoing_config', 'always_config' => $event->showtype_config,
            'tickets' => $event->shows->first()?->tickets->map->only(['name', 'ticket_price', 'currency', 'description']),
            'genres' => $event->genres->pluck('name'),
            'contentAdvisories' => $event->contentAdvisories->pluck('name'),
            'mobilityAdvisories' => $event->mobilityAdvisories->pluck('name'),
            'advisories' => $event->advisories?->only(['sexual', 'sexualDescription', 'audience']),
            'wheelchairReady' => $event->advisories?->wheelchairReady,
            'contactLevel' => $event->contactLevels->first()?->only(['id', 'name']),
            'interactiveLevel' => $event->interactive_level?->only(['id', 'name']),
            'ageLimit' => $event->age_limits?->only(['id', 'name']),
            'remotelocations' => $event->remotelocations->pluck('name'),
            'videos' => $event->videos->map->only(['platform', 'url', 'rank']),
            default => $event->{$key} ?? null,
        };

        return collect($validated)->mapWithKeys(fn ($proposed, $key) => [
            $key => ['current' => $current($key), 'proposed' => $proposed],
        ])->all();
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_slug' => $schema->string()->description('The event slug from create-event-draft or list-my-events.')->required(),
            'name' => $schema->string()->description('Event name, max 100 chars. Required before submission.'),
            'tag_line' => $schema->string()->description('Short tagline shown under the name, max 250 chars. Required before submission.'),
            'description' => $schema->string()->description('Full plain-text description, max 5000 chars. Required before submission.'),
            'attendance_type_id' => $schema->integer()->description('1 = In Person, 2 = Remote/online. Ask this FIRST — it decides whether the event needs a location or remote platforms, and which categories are valid.'),
            'category_id' => $schema->integer()->description('Category id from list-event-attributes (pass attendance_type_id there to see only compatible categories; an incompatible category gets dissociated).'),
            'genres' => $schema->array()->description('1-10 genre objects, at least 1 required before submission: [{"id": 1, "name": "Horror"}]. Ids from list-event-attributes; a new name without an id creates a user genre — prefer existing ones.'),
            'location' => $schema->object()->description('For in-person events: {venue (display name, max 80 chars — longer is rejected), home (street number), street, city, region, region_long, country, country_long, postal_code, latitude, longitude, hiddenLocationToggle (bool), hiddenLocation}. Use geocode-address to resolve the exact fields from a human address and confirm the match with the user. If the location is a secret (hiddenLocationToggle=true), hiddenLocation must explain how attendees learn the address.'),
            'remotelocations' => $schema->array()->description('For remote events, 1-10 platforms, at least 1 required before submission: [{"name": "Zoom"}].'),
            'remote_description' => $schema->string()->description('For remote events: how attendees join, max 3000 chars.'),
            'timezone' => $schema->string()->description('IANA timezone of the event, e.g. "America/New_York". geocode-address results include coordinates you can infer it from.'),
            'showtype' => $schema->string()->enum(['s', 'o', 'a'])->description('s = specific dates, o = ongoing/recurring, a = always available. WARNING: changing this wipes and recreates all shows and tickets. Always-available events have no embargo on the website, so clear it explicitly with embargo_date=null when switching to "a".'),
            'dateArray' => $schema->array()->description('Show datetimes in UTC "Y-m-d H:i:s". REQUIRED for showtype=s (list every specific date). OPTIONAL for showtype=o: send ongoing_config instead and the server expands the weekly recurrence for you. Only include dateArray for an ongoing event when you need exceptions (e.g. skip a holiday week) — and then send the FULL list of occurrence dates you want, because an explicit dateArray REPLACES the whole schedule rather than subtracting from it.'),
            'ongoing_config' => $schema->object()->description('For showtype=o: {startDate, endDate (UTC "Y-m-d H:i:s", anchored at noon in the event timezone), daysOfWeek: [0-6, Sunday=0]}. The server generates the concrete occurrence dates from this rule — send it alone, WITHOUT dateArray, for a normal weekly run.'),
            'always_config' => $schema->object()->description('For showtype=a: {endDate (UTC "Y-m-d H:i:s")} — when the listing should close. Defaults to 6 months out if omitted.'),
            'show_times' => $schema->string()->description('Human-readable showtimes text, max 500 chars, e.g. "Fridays 8pm, Saturdays 6pm & 9pm".'),
            'tickets' => $schema->array()->description('1-'.EventUpdateRules::MAX_TICKET_TIERS.' ticket tiers applied to every show: [{"name": "General", "ticket_price": 25.00, "currency": "USD", "description": ""}]. Names must be unique; name "Free" requires price 0; name "PWYC" = pay-what-you-can; description shows truncated around 60 chars. Currency is a 3-letter ISO 4217 code (USD, GBP, EUR, AUD, SGD, JPY, INR…) — any current currency is accepted; omit it and the event\'s location country decides (USD for remote events). Requires dates to exist first.'),
            'ticketUrl' => $schema->string()->description('URL where attendees buy tickets. Required before submission.'),
            'websiteUrl' => $schema->string()->description('Event or organizer website URL.'),
            'call_to_action' => $schema->string()->description('Ticket-button text, keep to 20 chars. Required before submission — default to "Get Tickets" if the user has no preference.'),
            'contactLevel' => $schema->object()->description('{id, name} from list-event-attributes contact_levels — how much performers physically contact the audience. Required before submission.'),
            'interactiveLevel' => $schema->object()->description('{id, name, description} from list-event-attributes interactive_levels — how much the audience participates. Required before submission.'),
            'ageLimit' => $schema->object()->description('{id} from list-event-attributes age_limits. Required before submission.'),
            'advisories' => $schema->object()->description('{sexual: bool, sexualDescription, audience}. ALWAYS ask the user whether the event contains sexual content — an explicit yes/no is required before submission, and if yes, sexualDescription (max 1000) must explain it. "audience" (max 1000) describes the role the audience plays and is also required. Answering the sexual question automatically adds the matching "Sexual Content"/"No Sexual Content" chip to the content advisories.'),
            'contentAdvisories' => $schema->array()->description('Content warnings: [{"name": "Loud noises"}]. At least 1 beyond the automatic sexual-content chip is required before submission, max 16 total. Offer the user the options from list-event-attributes first; free-form names are allowed but prefer existing ones.'),
            'mobilityAdvisories' => $schema->array()->description('Mobility/accessibility notes: [{"name": "Extended standing"}]. At least 1 beyond the automatic wheelchair chip is required before submission, max 16 total. Offer options from list-event-attributes first.'),
            'wheelchairReady' => $schema->boolean()->description('ALWAYS ask the user whether the event is wheelchair accessible — an explicit yes/no is required before submission. Answering automatically adds the matching "Wheelchair Accessible"/"Not Wheelchair Accessible" mobility chip.'),
            'embargo_date' => $schema->string()->description('Optional UTC "Y-m-d H:i:s" in the future: if set, the event stays hidden until this date after approval. Send null to lift an embargo and publish immediately. Omitting it leaves any existing embargo untouched.'),
            'videos' => $schema->array()->description('Optional, up to 4: [{"platform": "youtube"|"tiktok", "url": "...", "id": "platform video id", "rank": 0}]. Instagram is not supported.'),
            'acknowledge_duplicate' => $schema->boolean()->description('Set true only after the user confirms a duplicate-name warning.'),
            'confirm_live_edit' => $schema->boolean()->description('Required when editing a PUBLISHED or EMBARGOED event: the first call returns a current-vs-proposed diff instead of applying. Show the user the diff, get their explicit confirmation, then retry with this set to true.'),
            'confirm_schedule_replace' => $schema->boolean()->description('Required when a schedule change would DELETE existing shows (a showtype switch, or a dateArray that drops dates): the first call returns action_required=confirm_schedule_replace with the count. Shows are hard-deleted with no undo — tell the user how many will be removed, get their explicit confirmation, then retry with this set to true.'),
        ];
    }
}
