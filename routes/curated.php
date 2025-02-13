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

// Community Features
Route::prefix('communities')->name('communities.')->group(function () {
    // Public routes
    Route::GET('/', [CommunityController::class, 'index'])->name('index');
    Route::GET('/{community}', [CommunityController::class, 'show'])->name('show')->middleware('can:preview,community');

    // Protected routes
    Route::middleware(['auth', 'verified'])->group(function () {
        // Create
        Route::GET('/create', [CommunityController::class, 'create'])->name('create');
        Route::POST('/', [CommunityController::class, 'store'])->name('store');

        // Update
        Route::GET('/{community}/edit', [CommunityController::class, 'edit'])->name('edit')->middleware('can:update,community');
        Route::POST('/{community}', [CommunityController::class, 'update'])->name('update')->middleware('can:update,community');
        Route::GET('/{community}/listings', [CommunityController::class, 'listings'])->name('listings')->middleware('can:update,community');
        Route::GET('/submitted', [CommunityController::class, 'submitted'])->name('submitted');

        // Curator management
        Route::POST('/{community}/curators/invite', [CommunityController::class, 'inviteCurator'])->name('curators.invite')->middleware('can:manageCurators,community');
        Route::GET('/curator-invitations/{token}', [CommunityController::class, 'acceptInvitation'])->name('curators.accept');
        Route::POST('/{community}/curators', [CommunityController::class, 'updateCurators'])->name('curators.update')->middleware('can:manageCurators,community');
        Route::POST('/{community}/curators/remove', [CommunityController::class, 'removeCurator'])->name('curators.remove')->middleware('can:manageCurators,community');
        Route::DELETE('/{community}/curators/self', [CommunityController::class, 'removeSelf'])->name('curators.remove-self')->middleware('can:removeSelf,community');

        // Additional actions
        Route::POST('/{community}/submit', [CommunityController::class, 'submit'])->name('submit')->middleware('can:update,community');
        Route::POST('/{community}/name-change', [CommunityController::class, 'requestNameChange'])->name('name.change')->middleware('can:update,community');
        Route::GET('/{community}/paginate', [CommunityController::class, 'paginate'])->name('paginate');

        // Shelves
        Route::controller(ShelfController::class)->middleware('can:update,community')->group(function () {
            Route::POST('/{community}/shelves', 'store');
            Route::POST('/{community}/shelves/order', 'order');
            Route::PUT('/{community}/shelves/{shelf}', 'update');
            Route::DELETE('/{community}/shelves/{shelf}', 'destroy');
            Route::GET('/{community}/shelves/{shelf}/paginate', 'paginate');
        });

        // Posts
        Route::controller(PostController::class)->group(function () {
            Route::GET('/{community}/posts/create', 'create')->middleware('can:update,community');
            Route::POST('/{community}/posts', 'store')->middleware('can:update,community');
            Route::GET('/{community}/posts/{post}', 'show')->middleware('can:preview,community');
            Route::GET('/{community}/posts/{post}/edit', 'edit')->middleware('can:update,community');
            Route::POST('/{community}/posts/{post}', 'update')->middleware('can:update,community');
            Route::DELETE('/{community}/posts/{post}', 'destroy')->middleware('can:update,community');
            Route::PUT('/{community}/posts/order', 'order')->middleware('can:update,community');
        });

        // Cards
        Route::controller(CardController::class)->middleware('can:update,community')->group(function () {
            Route::POST('/{community}/posts/{post}/cards', 'store');
            Route::POST('/{community}/posts/{post}/cards/{card}', 'update');
            Route::PUT('/{community}/posts/{post}/cards/order', 'order');
            Route::DELETE('/{community}/posts/{post}/cards/{card}', 'destroy');
        });
    });
});