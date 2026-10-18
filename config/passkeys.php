<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Relying Party ID
    |--------------------------------------------------------------------------
    |
    | The relying party ID represents your application in the WebAuthn protocol.
    | This is typically your domain (e.g., "example.com"). Passkeys are bound
    | to this ID and can only be verified on matching domains.
    |
    */

    'relying_party_id' => parse_url(config('app.url'), PHP_URL_HOST),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The origins permitted to complete WebAuthn ceremonies. Passkeys bound
    | to the relying party ID above will only verify when the browser
    | reports one of these origins. Defaults to your application URL.
    |
    */

    'allowed_origins' => [
        config('app.url'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Handle Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to derive a stable WebAuthn user handle from each user model.
    | Set this explicitly if you rotate your application key.
    |
    | EI: set PASSKEYS_USER_HANDLE_SECRET once per server and never change it.
    | A new secret gives every user a new handle, so password managers would
    | treat new passkeys as a different account and the browser's "these are
    | the passkeys you still have" signal would stop matching old ones.
    |
    */

    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),

    /*
    |--------------------------------------------------------------------------
    | WebAuthn Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in milliseconds for WebAuthn operations. This determines
    | how long users have to complete passkey registration or verification.
    |
    */

    'timeout' => 60000,

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use when logging in users with passkeys.
    | This should match your application's primary authentication guard.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Passkeys Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Passkeys will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Passkeys Management Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify the middleware applied to passkey management routes
    | that create or delete passkeys. By default, Laravel's password
    | confirmation middleware is used.
    |
    | EI: none. Nobody here has a password to confirm (logins are email
    | codes, social and passkeys), so 'password.confirm' would only bounce
    | people to a page EI does not have.
    |
    */

    'management_middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Passkeys Throttling
    |--------------------------------------------------------------------------
    |
    | Middleware used to throttle passkey endpoints. Set to null to disable.
    |
    | EI: unused. The routes are declared in routes/auth.php with their own
    | named limiters (AppServiceProvider::configureRateLimiting): passkey
    | autofill fetches login options on every login page view, which 6 a
    | minute per IP would soon refuse.
    |
    */

    'throttle' => null,

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    |
    | The path to redirect to after successful passkey verification.
    |
    */

    'redirect' => '/',

];
