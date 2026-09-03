<?php

use App\Mcp\Servers\EiServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
|--------------------------------------------------------------------------
| MCP Server
|--------------------------------------------------------------------------
|
| The Everything Immersive MCP server: lets AI clients (Claude, ChatGPT,
| Cursor…) create organizers and event drafts on behalf of a signed-in user.
|
| Auth is OAuth 2.1 through Laravel Passport. A client discovers the
| authorization server from the WWW-Authenticate challenge on a 401
| (/.well-known/oauth-protected-resource), registers itself (POST
| /oauth/register — public PKCE client, redirect domains allowlisted in
| config/mcp.php), sends the user to /oauth/authorize (routes/oauth.php:
| sign in, consent), and exchanges the code at /oauth/token. Personal
| access tokens minted on the API keys page work the same way for scripts.
|
| Every token needs the `mcp:use` scope. Moderator powers need
| `mcp:moderate`, which the consent screen never grants — see
| User::credentialAllowsModeration.
|
| None of these routes is in the maintenance-mode allow-list in
| bootstrap/app.php, so all MCP and OAuth access 503s while the site is down.
|
*/

Mcp::web('/mcp', EiServer::class)
    // Order matters: the per-IP throttle and the audit log run before
    // authentication (neither is a priority-sorted class), so a flood is
    // refused cheaply and a refused request is still recorded.
    ->middleware(['mcp.ip-throttle', 'mcp.audit', 'auth:api', 'scope:mcp:use', 'throttle:mcp'])
    ->name('mcp.ei');

// OAuth discovery metadata and dynamic client registration. Registered
// inside a throttled group so the well-known documents cannot be hammered…
Route::middleware('throttle:oauth-metadata')->group(function () {
    Mcp::oauthRoutes();
});

// …and registration re-declared last with its own, much tighter limit: the
// package registers it bare, and the last definition of a URI wins.
Route::post('oauth/register', OAuthRegisterController::class)
    ->middleware('throttle:oauth-register')
    ->name('mcp.oauth.register');

// The token endpoint is called by the client program, not a browser: no
// session, no CSRF. It lives here, outside the web group, for that reason.
Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle:oauth-token')
    ->name('passport.token');
