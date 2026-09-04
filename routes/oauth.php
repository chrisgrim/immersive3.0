<?php

use App\Http\Controllers\Oauth\ApproveAuthorizationController;
use App\Http\Controllers\User\ConnectedAppController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

/*
|--------------------------------------------------------------------------
| OAuth consent + connected apps (browser side)
|--------------------------------------------------------------------------
|
| Required from routes/web.php, so everything here runs in the web group:
| session, CSRF, the guest redirect that remembers the authorize URL across
| sign-in. The token endpoint and client registration are in routes/ai.php,
| which has none of that on purpose. Route names are Passport's, because the
| OAuth metadata the MCP package publishes resolves them by name.
|
| Passport's own routes are ignored (AppServiceProvider::register): only what
| is declared here exists. No device flow, no client management, no
| transient-token refresh.
|
*/

Route::prefix('oauth')->name('passport.')->group(function () {
    // deny-framing: the consent screen must never render inside another
    // site's frame (clickjacking of Approve / the moderator checkbox).
    Route::middleware(['auth', 'mcp.consent', 'throttle:30,1', 'deny-framing'])->group(function () {
        Route::get('/authorize', [AuthorizationController::class, 'authorize'])->name('authorizations.authorize');
        Route::post('/authorize', [ApproveAuthorizationController::class, 'approve'])->name('authorizations.approve');
        Route::delete('/authorize', [DenyAuthorizationController::class, 'deny'])->name('authorizations.deny');
    });

    // The signed-in user's own grants: what the "Connected apps" list reads
    // and what its Disconnect button calls. Ours, not Passport's /oauth/tokens:
    // that one hides tokens of unowned clients, which every assistant is.
    Route::middleware(['auth', 'throttle:60,1'])->group(function () {
        Route::get('/connections', [ConnectedAppController::class, 'index'])->name('connections.index');
        Route::delete('/connections/{tokenId}', [ConnectedAppController::class, 'destroy'])->name('connections.destroy');
    });
});
