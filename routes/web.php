<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Creation\HostController;
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\Search\ListingsController;
use App\Http\Controllers\User\ConversationsController;
use App\Http\Controllers\User\ProfilesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::GET('/', [IndexController::class, 'index'])->name('home');
Route::GET('/index/search', [ListingsController::class, 'index'])->name('search');

// Primary canonical routes
Route::GET('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::GET('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('organizers.show');

// Redirect singular versions to plural with 301 redirects
Route::GET('/event/{slug}', function ($slug) {
    return redirect('/events/'.$slug, 301);
});
Route::GET('/organizer/{slug}', function ($slug) {
    return redirect('/organizers/'.$slug, 301);
});

// Legal and Site Information Pages
Route::view('/terms', 'terms')->name('terms');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/sitemap', 'sitemap')->name('sitemap');
Route::view('/help', 'help')->name('help');

// Public profile viewing — ProfilesController::show() is already guest-aware
// ($isOwner is false for a guest/other viewer, and the Blade view guards
// every owner-only bit behind auth()->check()), and there's a dedicated
// unauthenticated API route group for a viewed-by-someone-else profile's
// public extras (routes/api.php, the "profile.*" group without auth:sanctum).
// This was the one piece still gating the page itself behind login.
//
// The optional /{tab?}/{itemKey?} segments are the Dashboard-into-Profile
// consolidation's URL scheme (Liked Events / Saved Searches move under here
// as more owner-only sidebar sections) — same tab|itemKey shape the old
// /dashboard/{tab?}/{itemKey?} route used. Constrained the same way so a
// malformed URL 404s instead of silently mis-parsing as a profile lookup.
Route::prefix('users')->name('users.')->group(function () {
    Route::get('/{user}/{tab?}/{itemKey?}', [ProfilesController::class, 'show'])
        ->where('tab', 'events|search-preferences')
        ->where('itemKey', '[a-zA-Z0-9-]+')
        ->name('show');
});

// XML Sitemap for SEO
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.xml');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Basic auth routes (no email verification needed)
    Route::view('/menu', 'nav.menu-mobile')->name('menu');

    // Dashboard is retired — Liked Events / Saved Searches now live on
    // Profile (see users.show above). Old links/bookmarks still work via a
    // permanent redirect to the equivalent /users/{id}/... URL, same
    // pattern as the /event/{slug} -> /events/{slug} redirect near the top
    // of this file.
    Route::get('/dashboard/{tab?}/{itemKey?}', function (\Illuminate\Http\Request $request, $tab = null, $itemKey = null) {
        $path = '/users/'.$request->user()->id;
        if ($tab) {
            $path .= '/'.$tab;
            if ($itemKey) {
                $path .= '/'.$itemKey;
            }
        }

        return redirect($path, 301);
    })
        ->where('tab', 'events|search-preferences')
        ->where('itemKey', '[a-zA-Z0-9-]+')
        ->name('hub.index');

    // Account Settings shell — Personal info / Login & security / Privacy / Notifications.
    // Same tab-in-path pattern as the Hub above.
    Route::get('/account-settings/{tab?}', [AccountSettingsController::class, 'index'])
        ->where('tab', 'personal-info|login-security|privacy|notifications')
        ->name('account-settings.index');

    Route::get('/notifications', [NotificationsController::class, 'index'])
        ->name('notifications.index');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/{user}/edit', [ProfilesController::class, 'edit'])->name('edit')->middleware('can:update,user');
        Route::post('/{user}', [ProfilesController::class, 'update'])->name('update')->middleware('can:update,user');
        // Self-account deletion is implemented (ProfilesController@destroy detaches the
        // user's conversations, then deletes them) but intentionally not exposed yet.
        // Uncomment to enable a "delete my account" action:
        // Route::delete('/{user}', [ProfilesController::class, 'destroy'])->name('destroy')->middleware('can:update,user');
    });

    // Routes requiring email verification
    Route::middleware(['verified'])->group(function () {
        // API tokens for the MCP server (moderator-only until MCP_TOKEN_UI_PUBLIC)
        Route::prefix('settings')->middleware('mcp.tokens')->group(function () {
            Route::get('/api-tokens', [\App\Http\Controllers\User\ApiTokenController::class, 'index'])->name('api-tokens.index');
            Route::get('/api-tokens/list', [\App\Http\Controllers\User\ApiTokenController::class, 'list'])->name('api-tokens.list');
            Route::post('/api-tokens', [\App\Http\Controllers\User\ApiTokenController::class, 'store'])->middleware('throttle:10,1')->name('api-tokens.store');
            Route::delete('/api-tokens/{tokenId}', [\App\Http\Controllers\User\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        });

        // Organizer Management
        Route::prefix('organizers')->name('organizers.')->middleware('can:edit,organizer')->group(function () {
            Route::GET('/{organizer}/edit', [OrganizerController::class, 'edit'])->name('edit');
            Route::POST('/{organizer}', [OrganizerController::class, 'update'])->name('update');
            Route::POST('/{organizer}/image', [OrganizerController::class, 'updateImage'])->name('image.update');
            Route::POST('/{organizer}/name-change', [OrganizerController::class, 'requestNameChange'])
                ->middleware('throttle:5,60')
                ->name('name.change');
            Route::POST('/{organizer}/submit', [OrganizerController::class, 'submit'])->name('submit');
        });

        // Keep store outside since anyone can create
        Route::POST('/organizers', [OrganizerController::class, 'store'])->name('organizers.store');

        // Team Management
        Route::prefix('teams')->name('teams.')->group(function () {
            Route::GET('/', [OrganizerController::class, 'teams'])->name('index')->middleware('can:viewAny,App\Models\Organizer');
            Route::POST('/switch/{organizer}', [OrganizerController::class, 'switchTeam'])->name('switch')->middleware('can:switchTeam,organizer');
        });

        // Hosting & Event Management
        Route::prefix('hosting')->name('hosting.')->group(function () {
            // Routes that don't require hosting permissions
            Route::GET('/getting-started', [HostController::class, 'intro'])->name('intro');
            Route::GET('/events', [HostController::class, 'show'])->name('dashboard');

            // Routes that require hosting permissions (user must have teams)
            Route::middleware('can:host,App\Models\Event')->group(function () {
                Route::prefix('event')->name('event.')->group(function () {
                    Route::POST('/create', [HostEventController::class, 'create'])->name('create');
                    Route::GET('/user/has-created-events', [HostEventController::class, 'hasCreatedEvents'])->name('user.has-created-events');

                    Route::middleware('can:manage,event')->group(function () {
                        Route::GET('/{event}/edit', [HostEventController::class, 'edit'])->name('edit');
                        Route::POST('/{event}/submit', [HostEventController::class, 'submit'])->name('submit');
                        Route::DELETE('/{event}', [HostEventController::class, 'destroy'])->name('destroy');
                        Route::POST('/{event}/name-change', [HostEventController::class, 'nameChange'])->name('name.change');
                    });
                });
            });
        });

        // Messaging System
        Route::prefix('inbox')->name('inbox.')->group(function () {
            Route::GET('/', [ConversationsController::class, 'index'])->name('index')->middleware('can:viewAny,App\Models\Messaging\Conversation');
            Route::GET('/fetch/conversation/{conversation}', [ConversationsController::class, 'show'])->name('conversation.show')->middleware('can:view,conversation');
            Route::POST('/conversation/{conversation}', [ConversationsController::class, 'update'])->name('conversation.update')->middleware('can:update,conversation');
            Route::POST('/fetch/conversations', [ConversationsController::class, 'search'])->name('conversations.search')->middleware('can:viewAny,App\Models\Messaging\Conversation');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {
    Route::GET('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.index');
});

/*
|--------------------------------------------------------------------------
| Fallback Route
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    if (app()->environment('production')) {
        return redirect('/');
    }

    abort(404);
});

/*
|--------------------------------------------------------------------------
| Additional Route Files
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
require __DIR__.'/curated.php';
