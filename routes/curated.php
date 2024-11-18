<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Curated\CommunityController;
use App\Http\Controllers\Curated\PostController;
use App\Http\Controllers\Curated\ShelfController;
use App\Http\Controllers\Curated\CardController;

// Resource Routes
Route::middleware('curator')->group(function () {
    Route::resource('communities', CommunityController::class);
});
Route::resource('posts', PostController::class);
Route::resource('cards', CardController::class);

// Communities
Route::controller(CommunityController::class)->group(function () {
    Route::get('/communities/{community}', 'show')
        ->middleware('can:preview,community');
    Route::get('/communities/{community}/shelves/paginate', 'paginate')
        ->middleware('can:preview,community');
    Route::get('/communities/{community}/edit', 'edit');
    Route::put('/communities/{community}', 'update')
        ->middleware('can:update,community');
    Route::delete('/communities/{community}', 'destroy')
        ->middleware('can:destroy,community');
    
    // Curator Management
    Route::post('/communities/{community}/curators/add', 'addCurator')
        ->middleware('can:owner,community');
    Route::post('/communities/{community}/curators/remove', 'removeCurator')
        ->middleware('can:update,community');
    Route::post('/communities/{community}/curators/owner', 'updateOwner')
        ->middleware('can:owner,community');
    Route::get('/create/communities/thanks', 'submitted');
});

// Shelves
Route::controller(ShelfController::class)->group(function () {
    Route::post('/shelves/{community}', 'store')
        ->middleware('can:update,community');
    Route::put('/shelves/{shelf}', 'update')
        ->middleware('can:update,shelf');
    Route::put('/shelves/{community}/order', 'order')
        ->middleware('can:update,community');
    Route::delete('/shelves/{shelf}', 'destroy')
        ->middleware('can:destroy,shelf');
    Route::get('/shelves/{shelf}/paginate', 'paginate');
});

// Posts
Route::controller(PostController::class)->group(function () {
    Route::get('/posts/{community}/create', 'create');
    Route::post('/posts/{community}/store', 'store');
    Route::get('/communities/{community}/{post}', 'show');
    Route::get('/communities/{community}/{post}/edit', 'edit');
    Route::put('/communities/{community}/{post}/update', 'update');
    Route::delete('/posts/{post}', 'destroy');
    Route::put('/posts/{community}/order', 'order');
});

// Cards
Route::controller(CardController::class)->group(function () {
    Route::post('/cards/{card}', 'update');
    Route::post('/cards/{post}/create', 'store');
    Route::put('/cards/{post}/order', 'order');
    Route::delete('/cards/{card}', 'destroy');
});