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

    // Phase 2 captures, each off until switched on: in .env, or without a
    // deploy with `php artisan ei:analytics-capture <name> on|off` (an
    // override kept in capture_file, read once per request). Turn on one at
    // a time and watch.
    'capture_file' => storage_path('app/analytics-capture.json'),

    'capture' => [
        'page_views' => env('ANALYTICS_PAGE_VIEWS', false),   // every public page (RecordPageView)
        'device' => env('ANALYTICS_DEVICE', false),           // device/browser/OS families, at flush
        'utm' => env('ANALYTICS_UTM', false),                 // campaign tags on page views
        'city' => env('ANALYTICS_CITY', false),               // city + region, at flush (DB-IP City Lite)
        'duration' => env('ANALYTICS_DURATION', false),       // time on page beacon
        'live' => env('ANALYTICS_LIVE', false),               // "on the site now" set in Redis
        'nav_search' => env('ANALYTICS_NAV_SEARCH', false),   // what is typed in the nav search
    ],

    // 'redis' on the servers; 'array' (this process's memory) in tests.
    'buffer' => env('ANALYTICS_BUFFER', 'redis'),

    'buffer_key' => 'analytics:events',

    // The buffer keeps at most this many notes (~40 MB worst case); older ones are
    // dropped if the flusher stops running.
    'buffer_max' => 50000,

    // Hits per visitor per day before the rest are flagged as a bot.
    'daily_cap' => 300,

    // Hits per IP address per day (any user agent) before the rest are
    // flagged. Higher than daily_cap: an office or campus shares one IP.
    'ip_daily_cap' => 1000,

    // Rows older than this are deleted by ei:analytics-prune: 13 months, the
    // most CNIL allows for audience measurement without consent.
    'raw_days' => 395,

    // Rows flagged as bots are deleted after this many days; their counts
    // stay in analytics_daily (ei:analytics-rollup).
    'bot_raw_days' => 30,

    // DB-IP Lite country + ASN databases (free, CC BY 4.0, monthly), kept
    // fresh by ei:analytics-geo-update. Without them rows just have no
    // country and no datacenter flag.
    'geo_path' => storage_path('app/geo'),

    // Networks of cloud and hosting companies: real people browse from home
    // and mobile networks, so a hit from one of these is almost always a
    // bot (Analytics::BOT_DATACENTER). The Singapore bot is Tencent 132203.
    'hosting_asns' => [
        132203, 45090, 133478,          // Tencent
        45102, 37963, 134963, 24429,    // Alibaba
        136907, 55990, 265443,          // Huawei Cloud
        16509, 14618, 8987,             // Amazon AWS
        396982, 19527,                  // Google Cloud
        8075,                           // Microsoft Azure
        31898,                          // Oracle Cloud
        14061,                          // DigitalOcean
        16276,                          // OVH
        24940, 213230,                  // Hetzner
        63949,                          // Linode / Akamai
        20473,                          // Vultr
        51167,                          // Contabo
        12876,                          // Scaleway
        60781, 28753, 7203,             // Leaseweb
        9009,                           // M247
        40021, 36352, 62904,            // Contabo US, ColoCrossing, Eonix
        212238, 210644,                 // Datacamp, Aeza
    ],

];
