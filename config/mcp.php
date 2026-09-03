<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Where a dynamically registered OAuth client may send the browser after
    | consent. The package default is '*', which would let anyone register a
    | client that redirects an approved user's authorization code to their
    | own server. Keep this to the assistants that are actually supported;
    | the loopback entries are what Claude Code and other CLIs use.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'https://chatgpt.com',
        'http://localhost',
        'http://127.0.0.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom URI Schemes
    |--------------------------------------------------------------------------
    |
    | Desktop editors register a private scheme rather than an https URL for
    | their callback. A scheme is only ever handled by an app installed on the
    | user's own machine, which is the trust boundary anyway.
    |
    */

    'custom_schemes' => [
        'cursor',
        'vscode',
        'vscode-insiders',
        'windsurf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | The issuer advertised in the OAuth metadata. Null means this application
    | itself, which is the case: Passport issues the tokens.
    |
    */

    'authorization_server' => null,

];
