<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Event;

trait FormatsEvents
{
    /**
     * Human-readable label for an event status char.
     */
    protected function statusLabel(?string $status): string
    {
        if ($status === null) {
            return 'unknown';
        }

        return match (true) {
            $status === 'p' => 'published',
            $status === 'e' => 'embargoed (approved, publishes automatically on the embargo date)',
            $status === 'r' => 'in review (awaiting admin approval)',
            $status === 'n' => 'needs revision (rejected — edit and resubmit)',
            default => 'draft (in progress)',
        };
    }

    /**
     * Human-readable label for an event showtype char. 'l' is a retired type
     * that still exists on older rows: the website rewrites it to 's' when the
     * wizard opens one, and the MCP tools make the caller convert it explicitly.
     */
    protected function showtypeLabel(?string $showtype): string
    {
        return match ($showtype) {
            's' => 'specific dates',
            'o' => 'ongoing/recurring',
            'a' => 'always available',
            'l' => 'limited (retired type — set an explicit showtype to edit the schedule)',
            default => 'not set',
        };
    }

    protected function eventSummary(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'status' => $event->status,
            'status_label' => $this->statusLabel($event->status),
            'organizer' => $event->organizer?->name,
            'organizer_id' => $event->organizer_id,
        ];
    }

    /**
     * Advisory chips the wizard adds automatically from the yes/no answers.
     * The "at least one advisory" requirements count entries BEYOND these.
     */
    protected const SEXUAL_CHIP_SLUGS = ['sexual-content', 'no-sexual-content'];

    protected const WHEELCHAIR_CHIP_SLUGS = ['wheelchair-accessible', 'not-wheelchair-accessible'];

    /**
     * What still needs to be filled in before the event can be submitted.
     * Full parity with the web wizard's step-by-step requirements — an MCP
     * submission must be at least as complete as a wizard one.
     */
    protected function readiness(Event $event): array
    {
        $hasShows = $event->shows->isNotEmpty();
        $hasTickets = $hasShows && $event->shows->first()->tickets->isNotEmpty();

        $locationReady = $event->hasLocation
            ? (bool) ($event->location?->latitude && $event->location?->longitude)
            : $event->remotelocations->isNotEmpty();

        return [
            'name' => ! str_starts_with((string) $event->slug, 'new-event-') || filled($event->name),
            'tag_line' => filled($event->tag_line),
            'category' => $event->category_id !== null,
            'genres' => $event->genres->isNotEmpty(),
            'description' => filled($event->description),
            'location_or_remote' => $locationReady,
            // A secret location needs an explanation of how attendees learn it.
            'secret_location_explained' => ! $event->location?->hiddenLocationToggle
                || filled($event->location?->hiddenLocation),
            'dates' => $hasShows,
            'tickets' => $hasTickets,
            'ticket_url' => filled($event->ticketUrl),
            'ticket_button_text' => filled($event->call_to_action),
            'primary_image' => filled($event->largeImagePath) || $event->images->contains(fn ($image) => (int) $image->rank === 0),
            'contact_level' => $event->contactLevels->isNotEmpty(),
            'age_limit' => $event->age_limits_id !== null,
            'interactive_level' => $event->interactive_level_id !== null,
            'audience_role' => filled($event->advisories?->audience),
            // The wizard forces explicit yes/no answers (nullable columns, so
            // null means "never answered").
            'sexual_content_answered' => $event->advisories?->sexual !== null,
            'wheelchair_answered' => $event->advisories?->wheelchairReady !== null,
            // At least one advisory beyond the automatic yes/no chips.
            'content_advisories' => $event->contentAdvisories
                ->reject(fn ($a) => in_array($a->slug, self::SEXUAL_CHIP_SLUGS))->isNotEmpty(),
            'mobility_advisories' => $event->mobilityAdvisories
                ->reject(fn ($a) => in_array($a->slug, self::WHEELCHAIR_CHIP_SLUGS))->isNotEmpty(),
        ];
    }

    /**
     * Wizard step markers in order, low → high. The website's creation wizard
     * packs the current step into the overloaded `status` column ('1'-'9',
     * 'A'-'D'); '0'/'d' mean "before step 1". Keep in sync with STEP_MAP in
     * resources/js/PageComponents/Creation/Core/index.vue.
     */
    protected const STEP_MARKERS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D'];

    protected const STEP_NAMES = [
        '1' => 'EventType', '2' => 'Name', '3' => 'Category', '4' => 'Genres',
        '5' => 'Location', '6' => 'Description', '7' => 'Dates', '8' => 'Tickets',
        '9' => 'Images', 'A' => 'Advisories', 'B' => 'Content', 'C' => 'Mobility',
        'D' => 'Review',
    ];

    /**
     * The furthest CONTIGUOUSLY-complete wizard step, as a status marker.
     * The step→data grouping mirrors the wizard's per-step page components
     * (Core/Pages/*), so a draft resumes on the first unfinished step exactly
     * like a human-built one. Returns '0' when not even the event type is set.
     */
    protected function wizardStepMarker(Event $event, array $r): string
    {
        // [marker, complete?] pairs — marker kept as a VALUE, not an array key,
        // because PHP coerces numeric-string keys ('1'..'9') to integers, which
        // would corrupt the string markers on the way out.
        $steps = [
            ['1', $event->attendance_type_id !== null],
            ['2', $r['name'] && $r['tag_line']],
            ['3', $r['category']],
            ['4', $r['genres']],
            ['5', $r['location_or_remote'] && $r['secret_location_explained']],
            ['6', $r['description']],
            ['7', $r['dates']],
            ['8', $r['tickets'] && $r['ticket_url'] && $r['ticket_button_text']],
            ['9', $r['primary_image']],
            ['A', $r['contact_level'] && $r['age_limit'] && $r['interactive_level'] && $r['audience_role']],
            ['B', $r['sexual_content_answered'] && $r['content_advisories']],
            ['C', $r['wheelchair_answered'] && $r['mobility_advisories']],
        ];

        $marker = '0';
        foreach ($steps as [$char, $done]) {
            if (! $done) {
                break;
            }
            $marker = $char;
        }

        return $marker;
    }

    /**
     * Advance a draft's wizard step marker (stored in `status`) to the furthest
     * contiguously-complete step, monotonically — the same "furthest step
     * reached" high-water mark the website maintains as the user clicks Next.
     * No-op unless the event is still a draft/in-progress ('d' or a step
     * marker): lifecycle states (published, embargoed, in-review, needs-
     * revision) are never overwritten.
     */
    protected function syncWizardStep(Event $event, array $readiness): void
    {
        $current = $event->status === 'd' ? '0' : (string) $event->status;

        if ($current !== '0' && ! in_array($current, self::STEP_MARKERS, true)) {
            return; // p/e/r/n — not a draft; leave the lifecycle status alone.
        }

        $rank = fn (string $m): int => $m === '0' ? -1 : (int) array_search($m, self::STEP_MARKERS, true);
        $target = $this->wizardStepMarker($event, $readiness);

        if ($rank($target) > $rank($current)) {
            $event->status = $target;
            $event->save();
        }
    }

    /**
     * The step the website's creation wizard opens on for a status marker: it
     * resumes at the step AFTER the furthest completed one (Review once
     * everything through Mobility is done). '0'/'d'/unknown → the first step.
     */
    protected function webResumeStep(?string $status, bool $isRemote = false): ?string
    {
        $status = (string) $status;

        // Fresh draft, before any step is complete → the first wizard step.
        if ($status === '0' || $status === 'd') {
            return self::STEP_NAMES['1'];
        }

        // Position via STEP_MARKERS (a list — string values, no key coercion).
        $i = array_search($status, self::STEP_MARKERS, true);

        // A lifecycle status (p/e/r/n) is not a resumable wizard state.
        if ($i === false) {
            return null;
        }

        // Resume at the step after the furthest saved one (or stay on the last).
        $next = self::STEP_MARKERS[$i + 1] ?? self::STEP_MARKERS[$i];
        $name = self::STEP_NAMES[$next];

        // The wizard shows 'Remote' (not 'Location') at step 5 for remote events.
        return ($name === 'Location' && $isRemote) ? 'Remote' : $name;
    }
}
