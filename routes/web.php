<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\User\ProfilesController;
use App\Http\Controllers\User\ConversationsController;
use App\Http\Controllers\Search\ListingsController;
use App\Http\Controllers\Creation\HostController;
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Curated\CommunityController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::GET('/', [IndexController::class, 'index'])->name('home');
Route::GET('/index/search', [ListingsController::class, 'index'])->name('search');
Route::GET('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::GET('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('organizers.show');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Basic auth routes (no email verification needed)
    Route::view('/menu', 'Nav.menu-mobile')->name('menu');
    
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/account-settings', [ProfilesController::class, 'account'])->name('account.settings');
        Route::get('/{user}', [ProfilesController::class, 'show'])->name('show');
        Route::post('/{user}', [ProfilesController::class, 'update'])->name('update')->middleware('can:update,user');
    });

    // Routes requiring email verification
    Route::middleware(['verified'])->group(function () {
        // Organizer Management
        Route::prefix('organizers')->name('organizers.')->middleware('can:edit,organizer')->group(function () {
            Route::GET('/{organizer}/edit', [OrganizerController::class, 'edit'])->name('edit');
            Route::POST('/{organizer}', [OrganizerController::class, 'update'])->name('update');
            Route::POST('/{organizer}/image', [OrganizerController::class, 'updateImage'])->name('image.update');
            Route::POST('/{organizer}/name-change', [OrganizerController::class, 'requestNameChange'])->name('name.change');
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
        Route::prefix('hosting')->name('hosting.')->middleware('can:host,App\Models\Event')->group(function () {
            Route::GET('/events', [HostController::class, 'show'])->name('dashboard');
            Route::GET('/getting-started', [HostController::class, 'intro'])->name('intro');
            
            Route::prefix('event')->name('event.')->group(function () {
                Route::POST('/create', [HostEventController::class, 'create'])->name('create');
                
                Route::middleware('can:manage,event')->group(function () {
                    Route::GET('/{event}/edit', [HostEventController::class, 'edit'])->name('edit');
                    Route::POST('/{event}/submit', [HostEventController::class, 'submit'])->name('submit');
                    Route::DELETE('/{event}', [HostEventController::class, 'destroy'])->name('destroy');
                    Route::POST('/{event}/name-change', [HostEventController::class, 'nameChange'])->name('name.change');
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
| Additional Route Files
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
require __DIR__.'/curated.php';
