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

// Model Imports
use App\Models\Category;
use App\Models\Genre;
use App\Models\Events\{
    RemoteLocation,
    ContactLevel,
    ContentAdvisory,
    MobilityAdvisory,
    InteractiveLevel
};

// Cached Data Routes
use App\Http\Controllers\CachedDataController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication Routes
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Public Routes
Route::get('/index/search', [ListingsController::class, 'apiIndex']);
Route::post('/hosting/event/{event}', [HostEventController::class, 'update'])->name('event.update');

// Event Attributes Routes
Route::controller(EventAttributesController::class)->group(function () {
    Route::get('/categories', 'categories');
    Route::get('/genres', 'genres');
    Route::get('/remotelocations', 'remoteLocations');
    Route::get('/contactlevels', 'contactLevels');
    Route::get('/interactivelevels', 'interactiveLevels');
    Route::get('/contentadvisories', 'contentAdvisories');
    Route::get('/mobilityadvisories', 'mobilityAdvisories');
    Route::get('/agelimits', 'ageLimits');
});

// Navigation Search Routes
Route::controller(SearchController::class)->group(function () {
    Route::get('search/nav/events', 'navEvents');
    Route::get('search/nav/organizers', 'navOrganizers');
    Route::get('search/nav/names', 'navNames');
    Route::get('search/nav/genres', 'navGenres');
});

// Teams Search Route
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/teams/search', [OrganizerController::class, 'searchTeams'])->name('api.teams.search');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/approval-counts', [DashboardController::class, 'getApprovalCounts']);
    
    // Organizers - All non-approval organizer routes
    Route::controller(AdminOrganizerController::class)->group(function () {
        Route::get('/organizers/{organizer}', 'show');
        Route::prefix('manage')->group(function () {
            Route::get('/organizers', 'index');
            Route::patch('/organizers/{organizer}', 'update');
            Route::delete('/organizers/{organizer}', 'destroy');
        });
    });

    // Communities
    Route::controller(AdminCommunityController::class)->group(function () {
        Route::get('/communities/{community}', 'show');
    });

    // Approval Routes
    Route::prefix('approve')->group(function () {
        // Events
        Route::controller(AdminEventController::class)->group(function () {
            Route::get('/events', 'getPending');
            Route::post('/events/{event}/approve', 'approve');
            Route::post('/events/{event}/reject', 'reject');
        });
        
        // Organizers approval routes
        Route::controller(AdminOrganizerController::class)->group(function () {
            Route::get('/organizers', 'getPending');
            Route::post('/organizers/{organizer}/approve', 'approve');
            Route::post('/organizers/{organizer}/reject', 'reject');
        });
        
        // Communities approval routes
        Route::controller(AdminCommunityController::class)->group(function () {
            Route::get('/communities', 'getPending');
            Route::post('/communities/{community}/approve', 'approve');
            Route::post('/communities/{community}/reject', 'reject');
        });

        // Name Change Requests
        Route::controller(AdminRequestsController::class)->group(function () {
            Route::get('/requests', 'index');
            Route::post('/requests/{request}/approve', 'approve');
            Route::post('/requests/{request}/reject', 'reject');
        });
    });

    // Management Routes
    Route::prefix('manage')->group(function () {
        // Users Management
        Route::controller(AdminUserController::class)->group(function () {
            Route::get('/users', 'index');
            Route::patch('/users/{user}', 'update');
            Route::delete('/users/{user}', 'destroy');
        });
        
        // Events Management
        Route::controller(AdminEventController::class)->group(function () {
            Route::get('/events', 'index');
            Route::patch('/events/{event}', 'update');
            Route::delete('/events/{event}', 'destroy');
        });
        
        // Reviews Management
        Route::controller(AdminReviewController::class)->group(function () {
            Route::get('/reviews', 'index');
            Route::post('/reviews', 'store');
            Route::patch('/reviews/{review}', 'update');
            Route::delete('/reviews/{review}', 'destroy');
        });
    });

    // Settings Routes
    Route::prefix('settings')->group(function () {
        // Categories
        Route::controller(AdminCategoryController::class)->group(function () {
            Route::get('categories', 'index');
            Route::post('categories', 'store');
            Route::post('categories/{category}', 'update');
            Route::delete('categories/{category}', 'destroy');
        });
        
        // Genres
        Route::controller(AdminGenreController::class)->group(function () {
            Route::get('genres', 'index');
            Route::post('genres', 'store');
            Route::post('genres/{genre}', 'update');
            Route::patch('genres/{genre}', 'update');
            Route::delete('genres/{genre}', 'destroy');
        });
        
        // Advisories
        Route::controller(AdminAdvisoryController::class)->group(function () {
            Route::get('advisories/{type}', 'index');
            Route::post('advisories', 'store');
            Route::patch('advisories/{type}/{id}', 'update');
            Route::delete('advisories/{type}/{id}', 'destroy');
        });
    });

    // Events
    Route::controller(AdminEventController::class)->group(function () {
        Route::get('/events/{event}', 'show');
        Route::post('/events/{event}/duplicate', [HostEventController::class, 'duplicate'])
            ->middleware(['can:moderate,App\Models\Event']);
    });

    // Docks
    Route::controller(AdminDocksController::class)->group(function () {
        Route::get('/docks', 'index');
        Route::post('/docks', 'store');
        Route::post('/docks/{dock}', 'update');
        Route::delete('/docks/{dock}', 'destroy');
        
        // Content management routes
        Route::get('/docks/available-shelves', 'getAvailableShelves');
        Route::get('/docks/available-communities', 'getAvailableCommunities');
        Route::post('/docks/{dock}/shelves', 'toggleShelf');
    });
});

// Cached Data Routes
Route::get('/categories/active/cached', [CachedDataController::class, 'getActiveCategories']);
Route::get('/genres/active/cached', [CachedDataController::class, 'getActiveGenres']);
Route::get('/price/max/cached', [CachedDataController::class, 'getMaxPrice']);


