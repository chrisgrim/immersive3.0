<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminOrganizerController;
use App\Http\Controllers\Admin\AdminCommunityController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminGenreController;
use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminPicksController;
use App\Http\Controllers\Search\ListingsController;
use App\Models\Category;
use App\Models\Events\RemoteLocation;
use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\InteractiveLevel;
use App\Models\Genre;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/index/search', [ListingsController::class, 'apiIndex']);

Route::POST('/hosting/event/{event}', [HostEventController::class, 'update'])->name('event.update');


//LISTS
Route::GET('/categories', function () {
    $categories = Category::orderBy('name')->when(request()->has('remote'), function ($query) { $query->where('remote', request()->query('remote')); })->get();
    return response()->json($categories);
});

Route::GET('/genres', function () { 
    return Genre::where('admin', true)->orWhere('user_id', auth()->user()->id)->orderBy('name')->get(); 
});

Route::GET('/remotelocations', function () { return RemoteLocation::all(); });
Route::GET('/contactlevels', function () { return ContactLevel::all(); });
Route::GET('/interactivelevels', function () { return InteractiveLevel::all(); });
Route::GET('/contentadvisories', function () { return ContentAdvisory::where('admin', true)->orWhere('user_id', auth()->user()->id)->get(); });
Route::GET('/mobilityadvisories', function () { return MobilityAdvisory::where('admin', true)->orWhere('user_id', auth()->user()->id)->get(); });





//Nav Search 

Route::GET('search/nav/events', [SearchController::class, 'navEvents']);
Route::GET('search/nav/organizers', [SearchController::class, 'navOrganizers']);
Route::GET('search/nav/name', [SearchController::class, 'navNames']);
Route::GET('search/nav/genres', [SearchController::class, 'navGenres']);



// Admin API Routes - removed admin middleware
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Approval Routes
    Route::prefix('approve')->group(function () {
        Route::get('/events', [AdminEventController::class, 'getPending']);
        Route::post('/events/{event}/approve', [AdminEventController::class, 'approve']);
        Route::post('/events/{event}/reject', [AdminEventController::class, 'reject']);
        Route::get('/organizers', [AdminOrganizerController::class, 'getPending']);
        Route::post('/organizers/{organizer}', [AdminOrganizerController::class, 'approve']);
        Route::get('/communities', [AdminCommunityController::class, 'getPending']);
        Route::post('/communities/{community}', [AdminCommunityController::class, 'approve']);
    });

    // Management Routes
    Route::prefix('manage')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        
        Route::get('/organizers', [AdminOrganizerController::class, 'index']);
        Route::patch('/organizers/{organizer}', [AdminOrganizerController::class, 'update']);
        Route::delete('/organizers/{organizer}', [AdminOrganizerController::class, 'destroy']);
        
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::patch('/events/{event}', [AdminEventController::class, 'update']);
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy']);
        
        // Reviews management
        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::post('/reviews', [AdminReviewController::class, 'store']);
        Route::patch('/reviews/{review}', [AdminReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy']);
    });

    // Admin Settings Routes
    Route::prefix('settings')->group(function () {
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::post('categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
        
        Route::get('genres', [AdminGenreController::class, 'index']);
        Route::post('genres', [AdminGenreController::class, 'store']);
        Route::post('genres/{genre}', [AdminGenreController::class, 'update']);
        Route::patch('genres/{genre}', [AdminGenreController::class, 'update']);
        Route::delete('genres/{genre}', [AdminGenreController::class, 'destroy']);
        
        Route::get('advisories/{type}', [AdminAdvisoryController::class, 'index']);
        Route::post('advisories', [AdminAdvisoryController::class, 'store']);
        Route::patch('advisories/{type}/{id}', [AdminAdvisoryController::class, 'update']);
        Route::delete('advisories/{type}/{id}', [AdminAdvisoryController::class, 'destroy']);
    });

    Route::get('/events/{event}', [AdminEventController::class, 'show']);

});


