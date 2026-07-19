<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],
    'analytics' => [
        'id' => env('ANALYTICS_ID'),
    ],

    'geonames' => [
        'username' => env('GEONAMES_USERNAME'),
    ],

    'mcp' => [
        // While false, the API-token page is only visible to moderators/admins.
        // Flip to true (env) when the MCP server opens to the public.
        'token_ui_public' => env('MCP_TOKEN_UI_PUBLIC', false),
    ],

    'google_geocoding' => [
        // Server-side Google Geocoding API key (must NOT be referrer-restricted;
        // the existing VITE_GOOGLE_MAPS_KEY is browser-only). When absent, the
        // MCP geocode-address tool falls back to OpenStreetMap Nominatim.
        'key' => env('GOOGLE_GEOCODING_KEY'),
    ],

    'anthropic' => [
        // Anthropic Claude API key. A config entry (not a bare env() call) so it
        // still resolves after `config:cache` on the server. Powers the
        // event-scheduling AI assistant and the event-scraper's Claude path.
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

];
