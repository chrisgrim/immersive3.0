<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Where a dynamically registered OAuth client may send the browser after
    | consent — exact origins (scheme and host, no port), matched on the
    | parsed URL by App\Http\Controllers\Oauth\RegisterClientController.
    | Loopback hosts (localhost, 127.0.0.1, [::1]) are always allowed on any
    | port; that is what Claude Code and other CLIs use. The package default
    | was '*', which would let anyone register a client that sends approved
    | users' authorization codes to their own server.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'https://chatgpt.com',
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
