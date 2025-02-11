<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Curated\CommunityController;
use App\Http\Controllers\Curated\PostController;
use App\Http\Controllers\Curated\ShelfController;
use App\Http\Controllers\Curated\CardController;

// Resource Routes
    // Route::resource('communities', CommunityController::class);
    // Route::resource('posts', PostController::class);
    // Route::resource('cards', CardController::class);

// Communities
Route::controller(CommunityController::class)->group(function () {
    Route::get('/communities', 'index');
    Route::get('/communities/{community}', 'show');
    Route::get('/communities/{community}/shelves/paginate', 'paginate');
    Route::get('/communities/{community}/edit', 'edit');
    Route::get('/communities/{community}/listings', 'listings');
    Route::post('/communities/{community}', 'update')
        ->middleware('can:update,community');
    Route::post('/communities/{community}/curators', 'updateCurators')
        ->middleware('can:manageCurators,community');
    Route::delete('/communities/{community}', 'destroy');
    
    // Curator Management
    Route::post('/communities/{community}/curators/add', 'addCurator')
        ->middleware('can:manageCurators,community');
    Route::post('/communities/{community}/curators/remove', 'removeCurator')
        ->middleware('can:manageCurators,community');
    Route::post('/communities/{community}/curators/remove-self', 'removeSelf')
        ->middleware('can:removeSelf,community');
    Route::post('/communities/{community}/curators/owner', 'updateOwner')
        ->middleware('can:manageCurators,community');
    Route::post('/communities/{community}/curators/invite', 'inviteCurator')
        ->middleware('can:manageCurators,community');
    Route::get('/curator-invitations/{token}', 'acceptInvitation')->name('curator.accept-invitation');
    Route::get('/create/communities/thanks', 'submitted');
    
    // Name Change Request
    Route::post('/communities/{community}/name-change', 'requestNameChange')
        ->middleware('can:update,community');
});

// Shelves
Route::controller(ShelfController::class)->group(function () {
    Route::post('/shelves/{community}', 'store');
    Route::put('/shelves/{shelf}', 'update');
    Route::put('/shelves/{community}/order', 'order');
    Route::delete('/shelves/{shelf}', 'destroy');
    Route::get('/shelves/{shelf}/paginate', 'paginate');
});

// Posts
Route::controller(PostController::class)->group(function () {
    Route::get('/posts/{community}/create', 'create');
    Route::post('/posts/{community}/store', 'store');
    Route::get('/communities/{community}/{post}', 'show');
    Route::get('/communities/{community}/{post}/edit', 'edit');
    Route::post('/communities/{community}/{post}/update', 'update');
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