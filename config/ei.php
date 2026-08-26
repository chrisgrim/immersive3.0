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

];
