<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shuffle the approval queue
    |--------------------------------------------------------------------------
    |
    | The moderation queue is normally newest-first, which means the same
    | handful of events sit at the top until someone clears them. Shuffling
    | gives whoever is working through it a different slice each day.
    |
    | The order is seeded, not re-rolled per query: paginating must not
    | reshuffle, or page 2 shows some events twice and hides others entirely —
    | worse than not shuffling at all. See AdminEventController::getPending().
    |
    | Set EI_SHUFFLE_REVIEW_QUEUE=false in the server .env and deploy (or run
    | config:cache) to put it straight back to newest-first. No code change.
    |
    */

    'shuffle_review_queue' => env('EI_SHUFFLE_REVIEW_QUEUE', true),

];
