<?php

/*
 * First-party analytics (see App\Support\Analytics\Analytics). Requests only
 * push a note onto a buffer; ei:analytics-flush writes them to
 * analytics_events once a minute.
 */
return [

    // Kill switch: false and nothing is recorded or flushed. Off by default
    // on staging (dev), which runs no scheduler: nothing would ever flush,
    // and the buffer would keep raw IPs in Redis indefinitely.
    'enabled' => env('ANALYTICS_ENABLED', in_array(env('APP_ENV'), ['production', 'local', 'testing'], true)),

    // 'redis' on the servers; 'array' (this process's memory) in tests.
    'buffer' => env('ANALYTICS_BUFFER', 'redis'),

    'buffer_key' => 'analytics:events',

    // The buffer keeps at most this many notes (~4 MB); older ones are
    // dropped if the flusher stops running.
    'buffer_max' => 20000,

    // Hits per visitor per day before the rest are flagged as a bot.
    'daily_cap' => 300,

    // Raw rows older than this are deleted by ei:analytics-prune.
    'raw_days' => 180,

];
