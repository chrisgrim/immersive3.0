<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controller Imports - Search
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\Search\ListingsController;
use App\Http\Controllers\Search\EventAttributesController;

// Controller Imports - Admin
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOrganizerController;
use App\Http\Controllers\Admin\AdminCommunityController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminGenreController;
use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminPicksController;
use App\Http\Controllers\Admin\AdminDocksController;
use App\Http\Controllers\Admin\AdminRequestsController;
use App\Http\Controllers\Admin\DashboardController;

// Controller Imports - Other
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\CachedDataController;
use App\Http\Controllers\Creation\EventClickController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::GET('/index/search', [ListingsController::class, 'apiIndex']);
Route::POST('/hosting/event/{event}', [HostEventController::class, 'update'])->name('event.update');

// Event duplication route for organizer owners
Route::POST('/events/{event}/duplicate', [HostEventController::class, 'duplicate'])
    ->middleware(['auth:sanctum'])
    ->middleware('can:duplicate,event')
    ->name('event.duplicate');

// Event click tracking routes
Route::POST('/events/{eventId}/track-click', [EventClickController::class, 'trackClick'])
    ->name('event.track.click');

// Event click statistics (protected route)
Route::GET('/events/{eventId}/click-stats', [EventClickController::class, 'getStats'])
    ->middleware(['auth:sanctum'])
    ->name('event.click.stats');

// Event Attributes Routes
Route::controller(EventAttributesController::class)->group(function () {
    Route::GET('/categories', 'categories');
    Route::GET('/genres', 'genres');
    Route::GET('/remotelocations', 'remoteLocations');
    Route::GET('/contactlevels', 'contactLevels');
    Route::GET('/interactivelevels', 'interactiveLevels');
    Route::GET('/contentadvisories', 'contentAdvisories');
    Route::GET('/mobilityadvisories', 'mobilityAdvisories');
    Route::GET('/agelimits', 'ageLimits');
});

// Navigation Search Routes
Route::controller(SearchController::class)->group(function () {
    Route::GET('search/nav/events', 'navEvents');
    Route::GET('search/nav/organizers', 'navOrganizers');
    Route::GET('search/nav/names', 'navNames');
    Route::GET('search/nav/genres', 'navGenres');
});

// Cached Data Routes
Route::GET('/categories/active/cached', [CachedDataController::class, 'getActiveCategories']);
Route::GET('/genres/active/cached', [CachedDataController::class, 'getActiveGenres']);
Route::GET('/price/max/cached', [CachedDataController::class, 'getMaxPrice']);

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

// Add this route before the moderator middleware group
Route::middleware(['auth:sanctum'])->group(function () {
    Route::GET('/teams/search', [OrganizerController::class, 'searchTeams'])
        ->name('api.teams.search')
        ->middleware('can:viewAny,App\Models\Organizer');
});

// Keep your existing moderator routes
Route::middleware(['auth:sanctum', 'moderator'])->group(function () {
    Route::GET('/user', fn (Request $request) => $request->user());
    
    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    */
    
    Route::prefix('admin')->group(function () {
        Route::GET('/approval-counts', [DashboardController::class, 'getApprovalCounts']);
        
        // Organizers
        Route::controller(AdminOrganizerController::class)->group(function () {
            Route::GET('/organizers/{organizer}', 'show');
            Route::prefix('manage')->group(function () {
                Route::GET('/organizers', 'index');
                Route::PATCH('/organizers/{organizer}', 'update');
                Route::DELETE('/organizers/{organizer}', 'destroy');
            });
        });

        // Communities
        Route::controller(AdminCommunityController::class)->group(function () {
            Route::GET('/communities/{community}', 'show');
        });

        // Approval Routes
        Route::prefix('approve')->group(function () {
            // Events
            Route::controller(AdminEventController::class)->group(function () {
                Route::GET('/events', 'getPending');
                Route::POST('/events/{event}/approve', 'approve');
                Route::POST('/events/{event}/reject', 'reject');
            });
            
            // Organizers
            Route::controller(AdminOrganizerController::class)->group(function () {
                Route::GET('/organizers', 'getPending');
                Route::POST('/organizers/{organizer}/approve', 'approve');
                Route::POST('/organizers/{organizer}/reject', 'reject');
            });
            
            // Communities
            Route::controller(AdminCommunityController::class)->group(function () {
                Route::GET('/communities', 'getPending');
                Route::POST('/communities/{community}/approve', 'approve');
                Route::POST('/communities/{community}/reject', 'reject');
            });

            // Name Change Requests
            Route::controller(AdminRequestsController::class)->group(function () {
                Route::GET('/requests', 'index');
                Route::POST('/requests/{request}/approve', 'approve');
                Route::POST('/requests/{request}/reject', 'reject');
            });
        });

        // Management Routes
        Route::prefix('manage')->group(function () {
            // Users
            Route::controller(AdminUserController::class)->group(function () {
                Route::GET('/users', 'index');
                Route::PATCH('/users/{user}', 'update');
                Route::DELETE('/users/{user}', 'destroy');
            });
            
            // Events
            Route::controller(AdminEventController::class)->group(function () {
                Route::GET('/events', 'index');
                Route::PATCH('/events/{event}', 'update');
                Route::DELETE('/events/{event}', 'destroy');
            });
            
            // Reviews
            Route::controller(AdminReviewController::class)->group(function () {
                Route::GET('/reviews', 'index');
                Route::POST('/reviews', 'store');
                Route::PATCH('/reviews/{review}', 'update');
                Route::DELETE('/reviews/{review}', 'destroy');
            });
        });

        // Settings Routes
        Route::prefix('settings')->group(function () {
            // Categories
            Route::controller(AdminCategoryController::class)->group(function () {
                Route::GET('categories', 'index');
                Route::POST('categories', 'store');
                Route::POST('categories/{category}', 'update');
                Route::DELETE('categories/{category}', 'destroy');
            });
            
            // Genres
            Route::controller(AdminGenreController::class)->group(function () {
                Route::GET('genres', 'index');
                Route::POST('genres', 'store');
                Route::POST('genres/{genre}', 'update');
                Route::PATCH('genres/{genre}', 'update');
                Route::DELETE('genres/{genre}', 'destroy');
            });
            
            // Advisories
            Route::controller(AdminAdvisoryController::class)->group(function () {
                Route::GET('advisories/{type}', 'index');
                Route::POST('advisories', 'store');
                Route::PATCH('advisories/{type}/{id}', 'update');
                Route::DELETE('advisories/{type}/{id}', 'destroy');
            });
        });

        // Events
        Route::controller(AdminEventController::class)->group(function () {
            Route::GET('/events/{event}', 'show');
            Route::POST('/events/{event}/duplicate', [HostEventController::class, 'duplicate'])
                ->middleware(['can:moderate,App\Models\Event']);
        });

        // Docks
        Route::controller(AdminDocksController::class)->group(function () {
            Route::GET('/docks', 'index');
            Route::POST('/docks', 'store');
            Route::POST('/docks/{dock}', 'update');
            Route::DELETE('/docks/{dock}', 'destroy');
            Route::GET('/docks/available-shelves', 'getAvailableShelves');
            Route::GET('/docks/available-communities', 'getAvailableCommunities');
            Route::POST('/docks/{dock}/shelves', 'toggleShelf');
        });
    });
});


