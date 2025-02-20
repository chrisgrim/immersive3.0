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
    
    // Protected routes that need to come BEFORE the {community} wildcard
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::GET('/create', [CommunityController::class, 'create'])->name('create');
        Route::POST('/', [CommunityController::class, 'store'])->name('store');
        Route::GET('/submitted', [CommunityController::class, 'submitted'])->name('submitted');
        Route::GET('/curator-invitations/{token}', [CommunityController::class, 'acceptInvitation'])->name('curators.accept');
    });

    // Community-specific routes
    Route::prefix('{community}')->group(function () {
        // Public routes
        Route::GET('', [CommunityController::class, 'show'])->name('show');
        
        // Protected routes
        Route::middleware(['auth', 'verified'])->group(function () {
            // Community management
            Route::GET('/edit', [CommunityController::class, 'edit'])->name('edit')->middleware('can:update,community');
            Route::POST('', [CommunityController::class, 'update'])->name('update')->middleware('can:update,community');
            Route::GET('/listings', [CommunityController::class, 'listings'])->name('listings')->middleware('can:update,community');
            Route::GET('/paginate', [CommunityController::class, 'paginate'])->name('paginate');

            // Curator management
            Route::POST('/curators/invite', [CommunityController::class, 'inviteCurator'])->name('curators.invite')->middleware('can:manageCurators,community');
            Route::POST('/curators', [CommunityController::class, 'updateCurators'])->name('curators.update')->middleware('can:manageCurators,community');
            Route::POST('/curators/remove', [CommunityController::class, 'removeCurator'])->name('curators.remove')->middleware('can:manageCurators,community');
            Route::DELETE('/curators/self', [CommunityController::class, 'removeSelf'])->name('curators.remove-self')->middleware('can:removeSelf,community');

            // Additional actions
            Route::POST('/submit', [CommunityController::class, 'submit'])->name('submit')->middleware('can:update,community');
            Route::POST('/name-change', [CommunityController::class, 'requestNameChange'])->name('name.change')->middleware('can:update,community');

            // Posts
            Route::controller(PostController::class)->group(function () {
                Route::GET('/posts/create', 'create')->name('posts.create')->middleware('can:update,community');
                Route::POST('/posts', 'store')->name('posts.store')->middleware('can:update,community');
                Route::GET('/posts/{post}/edit', 'edit')->name('posts.edit')->middleware('can:update,community');
                Route::POST('/posts/{post}', 'update')->name('posts.update')->middleware('can:update,community');
                Route::DELETE('/posts/{post}', 'destroy')->name('posts.destroy')->middleware('can:update,community');
                Route::PUT('/posts/order', 'order')->name('posts.order')->middleware('can:update,community');
            });
            Route::GET('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

            // Shelves
            Route::controller(ShelfController::class)->middleware('can:update,community')->group(function () {
                Route::POST('/shelves', 'store');
                Route::POST('/shelves/order', 'order');
                Route::PUT('/shelves/{shelf}', 'update');
                Route::DELETE('/shelves/{shelf}', 'destroy');
                Route::GET('/shelves/{shelf}/paginate', 'paginate');
            });

            // Cards
            Route::controller(CardController::class)->middleware('can:update,community')->group(function () {
                Route::POST('/posts/{post}/cards', 'store');
                Route::POST('/posts/{post}/cards/{card}', 'update');
                Route::PUT('/posts/{post}/cards/order', 'order');
                Route::DELETE('/posts/{post}/cards/{card}', 'destroy');
            });
        });
    });
});