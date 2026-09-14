<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interleave the approval queue
    |--------------------------------------------------------------------------
    |
    | The moderation queue is normally newest-first, which clusters
    | near-identical listings: an organizer entering a multi-city chain in one
    | sitting produces six near-identical events created seconds apart, and a
    | date sort hands the moderator all six in a row.
    |
    | Interleaving spreads them, taking one event per organizer in rotation so
    | no two from the same organizer sit next to each other while any other
    | organizer still has one left.
    |
    | Deliberately deterministic rather than shuffled: random ordering clumps,
    | and would routinely still deal three of the same organizer in a row. It
    | also has to page correctly — two identical requests must return the same
    | order, or page 2 shows some events twice and hides others entirely.
    | See AdminEventController::getPending().
    |
    | Set EI_INTERLEAVE_REVIEW_QUEUE=false in the server .env and deploy (or run
    | config:cache) to put it straight back to newest-first. No code change.
    |
    */

    'interleave_review_queue' => env('EI_INTERLEAVE_REVIEW_QUEUE', true),

    /*
    |--------------------------------------------------------------------------
    | Event page: upcoming show rows embedded in the page
    |--------------------------------------------------------------------------
    |
    | The event page serializes the event once into window.Laravel.page with
    | only its upcoming show rows (id, event_id, date; ~40 bytes each). This
    | caps that list; show_summary.upcoming_total still reports the true
    | count. No live event comes near it (the recurrence expander stops at
    | 4,000 occurrences and the largest schedule has a few hundred upcoming).
    |
    */

    'event_page_max_shows' => 2000,

    // Past show dates embedded (as bare strings) so the calendars can still
    // highlight a long run's history; the oldest are dropped past this cap.
    'event_page_max_past_dates' => 3000,

];
