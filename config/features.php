<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Saved-search "notify me about new events" pilot
    |--------------------------------------------------------------------------
    |
    | The toggle on a saved search's editor page, and the twice-daily
    | NotifySavedSearchMatchesCommand that acts on it, are both restricted to
    | this one email — a pilot, not a general release. Enforced server-side
    | in both SavedSearchController::update() (rejects enabling the toggle
    | for anyone else) and the scheduled command's own query (only ever
    | processes this user's searches), not just by hiding the toggle in the
    | UI — see the "Pilot restriction" section of the feature's own
    | discussion for why UI-only gating isn't enough.
    |
    */
    'saved_search_notifications_user' => env('SAVED_SEARCH_NOTIFICATIONS_PILOT_EMAIL', 'chgrim@gmail.com'),

];
