<?php

use App\Http\Controllers\AccountSettings\AccountDeletionController;
use App\Http\Controllers\AccountSettings\LoginSecurityController;
use App\Http\Controllers\AccountSettings\PersonalInformationController;
use App\Http\Controllers\AccountSettings\PrivacyController;
use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminCategoryController;
// Controller Imports - Search
use App\Http\Controllers\Admin\AdminCommunityController;
use App\Http\Controllers\Admin\AdminDocksController;
use App\Http\Controllers\Admin\AdminEventController;
// Controller Imports - Admin
use App\Http\Controllers\Admin\AdminGenreController;
use App\Http\Controllers\Admin\AdminOrganizerController;
use App\Http\Controllers\Admin\AdminOwnershipClaimController;
use App\Http\Controllers\Admin\AdminRequestsController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Api\EventScraperController;
use App\Http\Controllers\Api\GeonamesController;
use App\Http\Controllers\Api\SimilarEventsController;
use App\Http\Controllers\CachedDataController;
use App\Http\Controllers\Creation\EventClickController;
// Controller Imports - Other
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\NotificationFeedController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\OwnershipClaimController;
use App\Http\Controllers\Profile\ProfileExtrasController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\Search\EventAttributesController;
use App\Http\Controllers\Search\ListingsController;
use App\Http\Controllers\Search\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Critical: Click tracking - Generous but prevents spam (30 clicks/min per IP)
Route::middleware(['throttle:30,1'])->group(function () {
    Route::POST('/events/{eventId}/track-click', [EventClickController::class, 'trackClick'])
        ->name('event.track.click');
});

// Resource-intensive: Search & recommendations - Very generous (180/min = 3/sec)
Route::middleware(['throttle:180,1'])->group(function () {
    Route::GET('/index/search', [ListingsController::class, 'apiIndex']);
    Route::GET('/events/{event}/similar', [SimilarEventsController::class, 'getSimilar'])
        ->name('events.similar');
    Route::GET('/events/similar-by-location', [SimilarEventsController::class, 'getSimilarByLocation'])
        ->name('events.similar-by-location');
    // The non-location results page's (all.vue) empty-state fallback.
    Route::GET('/events/latest-remote', [SimilarEventsController::class, 'getLatestRemote'])
        ->name('events.latest-remote');
});

// Validation endpoints - Generous for real-time typing (60/min = 1/sec)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::POST('/organizers/check-name', [OrganizerController::class, 'checkNameAvailability'])
        ->name('organizers.check-name');
});

// Standard public endpoints - Very generous
Route::middleware(['throttle:180,1'])->group(function () {
    Route::GET('/organizers/{organizer}/events', [App\Http\Controllers\EventController::class, 'getOrganizerPaginatedEvents'])
        ->name('organizers.events.paginated');
});

// Authenticated user operations - Very high limits (300/min = 5/sec)
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
    Route::POST('/hosting/event/{event}', [HostEventController::class, 'update'])
        ->middleware('can:manage,event')
        ->name('event.update');

    // Admin-only AI scheduling assistant for the Dates step. Uses {slug} (not
    // implicit binding) so it resolves drafts past the PublishedScope; admin +
    // per-event authorization are enforced in the controller. Tight throttle —
    // each call fans out to the Claude API.
    Route::POST('/hosting/event/{slug}/schedule-assistant', \App\Http\Controllers\Admin\ScheduleAssistantController::class)
        ->middleware('throttle:20,1')
        ->name('event.schedule-assistant');

    Route::GET('/events/{eventId}/click-stats', [EventClickController::class, 'getStats'])
        ->name('event.click.stats');
});

// Geonames timezone proxy — keeps the GeoNames username out of the JS bundle.
// Auth-gated because only logged-in event creators hit this; tight throttle
// since GeoNames itself rate-limits per username.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::GET('/geonames/timezone', [GeonamesController::class, 'timezone'])
        ->name('geonames.timezone');
});

// Event duplication - Generous for legitimate use (20/min)
Route::middleware(['auth:sanctum', 'throttle:20,1'])->group(function () {
    Route::POST('/events/{event}/duplicate', [HostEventController::class, 'duplicate'])
        ->middleware('can:duplicate,event')
        ->name('event.duplicate');

    // Ownership claims — a logged-in user requests ownership of a pre-entered organizer.
    // Eligibility is re-validated server-side; no policy needed (any authed user may ask).
    Route::POST('/organizers/{organizer}/claim', [OwnershipClaimController::class, 'store'])
        ->name('organizers.claim');
});

// Favoriting — lightweight, frequently-clicked toggle; generous like other real-time
// interaction endpoints (60/min = 1/sec). No policy needed — self-scoped to auth()->id().
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::POST('/events/{event}/favorite', [FavoriteController::class, 'store'])
        ->name('event.favorite');
    Route::DELETE('/events/{event}/favorite', [FavoriteController::class, 'destroy'])
        ->name('event.unfavorite');
});

// Following an organizer — same lightweight-toggle tier as favoriting.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::POST('/organizers/{organizer}/follow', [FollowController::class, 'store'])
        ->name('organizer.follow');
    Route::DELETE('/organizers/{organizer}/follow', [FollowController::class, 'destroy'])
        ->name('organizer.unfollow');
});

// /api/hub/* — Profile's Liked Events / Saved Searches tabs, and (the
// saved-searches and notification-preferences endpoints) reused by the nav
// search bar and Account Settings' Notifications tab. Kept the /hub/*
// namespace despite the page itself moving to Profile, to avoid churning an
// API contract for no user-facing benefit.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::GET('/hub/events', [FavoriteController::class, 'index'])
        ->name('hub.events');
    // Backs deep-linking a single saved event (/users/{id}/events/{slug}) —
    // see FavoriteController::show().
    Route::GET('/hub/events/{event}', [FavoriteController::class, 'show'])
        ->name('hub.events.show');
    // Per-event/organizer "Get updates" override. See FavoriteController::updateNotify().
    Route::PATCH('/hub/events/{event}/notify-updates', [FavoriteController::class, 'updateNotify'])
        ->name('hub.events.notify-updates');
    // Account Settings' Notifications tab — how many currently-notifying
    // saved events/followed organizers exist, and the "Clear all
    // notifications" button, a one-time bulk action on every existing
    // per-item override above, not a persistent setting. See
    // NotificationPreferenceController::notifyingCounts() and
    // ClearAllNotificationsAction.
    Route::GET('/hub/notification-preferences/counts', [NotificationPreferenceController::class, 'counts'])
        ->name('hub.notification-preferences.counts');
    Route::POST('/hub/notification-preferences/clear-all', [NotificationPreferenceController::class, 'clearAll'])
        ->name('hub.notification-preferences.clear-all');
    // Backs the Hub's "Saved Search Preferences" tab. Auto-saved from
    // nav-search.vue's handleLocationSearch/handleAtHomeSearch on every
    // Location/At Home search (see SaveSearchAction for the overwrite-in-
    // place behavior that keeps this from piling up).
    Route::GET('/hub/saved-searches', [SavedSearchController::class, 'index'])
        ->name('hub.saved-searches.index');
    Route::POST('/hub/saved-searches', [SavedSearchController::class, 'store'])
        ->name('hub.saved-searches.store');
    // Deliberate edit of one exact row from the Hub's editor — distinct from
    // the passive auto-save POST above (see UpdateSavedSearchAction).
    Route::PATCH('/hub/saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])
        ->name('hub.saved-searches.update');
    Route::DELETE('/hub/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])
        ->name('hub.saved-searches.destroy');
    Route::PATCH('/hub/saved-searches/{savedSearch}/pin', [SavedSearchController::class, 'togglePin'])
        ->name('hub.saved-searches.pin');
    // Saved-search "notify me about new events" toggle — server-side
    // restricted to moderators/admins (User::isModerator()), see
    // SavedSearchController::toggleNotify().
    Route::PATCH('/hub/saved-searches/{savedSearch}/notify', [SavedSearchController::class, 'toggleNotify'])
        ->name('hub.saved-searches.notify');
});

// Account Settings — Personal Information rows, each saved independently.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('account-settings')->name('account-settings.')->group(function () {
    Route::GET('/personal-info', [PersonalInformationController::class, 'show'])
        ->name('personal-info.show');
    Route::PATCH('/personal-info/legal-name', [PersonalInformationController::class, 'updateLegalName'])
        ->name('personal-info.legal-name');
    Route::PATCH('/personal-info/preferred-name', [PersonalInformationController::class, 'updatePreferredName'])
        ->name('personal-info.preferred-name');
    Route::PATCH('/personal-info/location', [PersonalInformationController::class, 'updateLocation'])
        ->name('personal-info.location');

    Route::GET('/login-security', [LoginSecurityController::class, 'show'])
        ->name('login-security.show');
    Route::DELETE('/login-security/devices/{device}', [LoginSecurityController::class, 'destroy'])
        ->name('login-security.devices.destroy');

    Route::GET('/privacy', [PrivacyController::class, 'show'])
        ->name('privacy.show');
    Route::PATCH('/privacy', [PrivacyController::class, 'update'])
        ->name('privacy.update');
    // Sends a pair of emails (user + legal) per call — tighter than the
    // group's default 60/min so it can't be used to spam either inbox.
    Route::POST('/privacy/data-request', [PrivacyController::class, 'requestData'])
        ->middleware('throttle:5,60')
        ->name('privacy.data-request');

    Route::DELETE('/', [AccountDeletionController::class, 'destroy'])
        ->name('destroy');
});

// Profile page extras. stats/followed-organizers/bio are scoped to the
// authenticated viewer's own profile, so those stay behind auth:sanctum.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('profile')->name('profile.')->group(function () {
    Route::GET('/stats', [ProfileExtrasController::class, 'stats'])
        ->name('stats');
    Route::GET('/followed-organizers', [ProfileExtrasController::class, 'followedOrganizers'])
        ->name('followed-organizers');
    Route::PATCH('/bio', [ProfileExtrasController::class, 'updateBio'])
        ->name('bio');
});

// {user}/public-extras is the one exception — it's for viewing SOMEONE
// ELSE'S profile (the profile page itself, ProfilesController::show, has no
// auth requirement either), gated by that user's own Privacy toggles (see
// User::showsPublicly()), not the viewer's. Requiring auth:sanctum here
// would 401 for exactly the logged-out visitors a public profile is for.
Route::middleware('throttle:60,1')->prefix('profile')->name('profile.')->group(function () {
    Route::GET('/{user}/public-extras', [ProfileExtrasController::class, 'publicExtras'])
        ->name('public-extras');
});

// In-app notification feed — every notification a trigger creates lands here
// regardless of the recipient's mail preference (mail is a separate,
// independently-gated channel — see each Notification class's via()). Named
// "api.notifications.*" (not just "notifications.*") because the page-shell
// route in web.php already claims that name — a duplicate route name is
// silently last-registration-wins in Laravel, so route('notifications.index')
// would resolve to whichever of the two happened to load last.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('notifications')->name('api.notifications.')->group(function () {
    Route::GET('/', [NotificationFeedController::class, 'index'])
        ->name('index');
    Route::POST('/read-all', [NotificationFeedController::class, 'markAllRead'])
        ->name('read-all');
    Route::POST('/{notification}/read', [NotificationFeedController::class, 'markRead'])
        ->name('read');
});

// Navigation Search Routes - Autocomplete (fired on keystroke, very generous 120/min = 2/sec)
Route::middleware(['throttle:120,1'])->group(function () {
    Route::controller(SearchController::class)->group(function () {
        Route::GET('search/nav/events', 'navEvents');
        Route::GET('search/nav/organizers', 'navOrganizers');
        Route::GET('search/nav/names', 'navNames');
        Route::GET('search/nav/genres', 'navGenres');
    });
});

// Event Attributes Routes - Reference data (very high limits, typically cached)
Route::middleware(['throttle:300,1'])->group(function () {
    Route::controller(EventAttributesController::class)->group(function () {
        Route::GET('/categories', 'categories');
        Route::GET('/genres', 'genres');
        Route::GET('/remotelocations', 'remoteLocations');
        Route::GET('/remotelocations/public', 'publicRemoteLocations');
        Route::GET('/contactlevels', 'contactLevels');
        Route::GET('/interactivelevels', 'interactiveLevels');
        Route::GET('/contentadvisories', 'contentAdvisories');
        Route::GET('/mobilityadvisories', 'mobilityAdvisories');
        Route::GET('/agelimits', 'ageLimits');
    });

    // Cached Data Routes
    Route::GET('/categories/active/cached', [CachedDataController::class, 'getActiveCategories']);
    Route::GET('/genres/active/cached', [CachedDataController::class, 'getActiveGenres']);
    Route::GET('/price/max/cached', [CachedDataController::class, 'getMaxPrice']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

// Authenticated user routes - Very generous for logged-in users (300/min = 5/sec)
Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
    Route::GET('/teams/search', [OrganizerController::class, 'searchTeams'])
        ->name('api.teams.search')
        ->middleware('can:viewAny,App\Models\Organizer');
});

// Admin/Moderator routes - High limits for trusted users (600/min = 10/sec)
Route::middleware(['auth:sanctum', 'moderator', 'throttle:600,1'])->group(function () {
    Route::GET('/user', fn (Request $request) => $request->user());

    /*
    |--------------------------------------------------------------------------
    | Admin Routes - High limits for admin operations (600/min = 10/sec)
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {
        Route::GET('/approval-counts', [DashboardController::class, 'getApprovalCounts']);

        // Organizers
        Route::controller(AdminOrganizerController::class)->group(function () {
            Route::GET('/organizers/{organizer}', 'show');
            Route::prefix('manage')->group(function () {
                Route::GET('/organizers', 'index');
                Route::GET('/organizers/{organizer}/events', 'events');
                Route::PATCH('/organizers/{organizer}', 'update');
                Route::DELETE('/organizers/{organizer}', 'destroy');
                Route::POST('/organizers/{organizer}/move-events', 'moveEvents');
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

            // Ownership Claims
            Route::controller(AdminOwnershipClaimController::class)->group(function () {
                Route::GET('/claims', 'index');
                Route::POST('/claims/{claim}/approve', 'approve');
                Route::POST('/claims/{claim}/reject', 'reject');
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
                Route::PATCH('/events/{event}/toggle-check', 'toggleCheck');
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
                Route::PATCH('categories/{category}', 'update');
                Route::DELETE('categories/{category}', 'destroy');
            });

            // Attendance Types
            Route::GET('attendance-types', function () {
                return \App\Models\AttendanceType::orderBy('rank')->get();
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
            Route::GET('/events/{event}/date-history', 'dateHistory');
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
            Route::GET('/docks/available-posts', 'getAvailablePosts');
            Route::POST('/docks/{dock}/shelves', 'toggleShelf');
            Route::POST('/docks/{dock}/posts', 'togglePost');
            Route::POST('/docks/{dock}/cards', 'toggleCard');
        });

        // Event Scraper (AI-assisted event data extraction)
        Route::controller(EventScraperController::class)->group(function () {
            Route::POST('/scraper/extract', 'extract');
            Route::GET('/scraper/test', 'test');
        });
    });
});
