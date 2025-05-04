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
    // Public routes that don't require authentication
    Route::GET('/submitted', [CommunityController::class, 'submitted'])->name('submitted');
    
    // Routes that require authentication
    Route::middleware(['auth', 'verified'])->group(function () {
        // Index route - requires authentication
        Route::GET('/', [CommunityController::class, 'index'])->name('index');
        
        Route::GET('/create', [CommunityController::class, 'create'])->name('create');
        Route::POST('/', [CommunityController::class, 'store'])->name('store');
        Route::GET('/curator-invitations/{token}', [CommunityController::class, 'acceptInvitation'])->name('curators.accept');
    });

    // Community-specific routes
    Route::prefix('{community}')->group(function () {
        // Public community show route
        Route::GET('', [CommunityController::class, 'show'])->name('show');
        
        // Generic redirect for old post URLs - must be before other routes with parameters
        Route::GET('/{slug}', function($community, $slug) {
            // Find if there's a post with this slug in this community
            $post = \App\Models\Curated\Post::where('slug', $slug)
                ->whereHas('community', function($query) use ($community) {
                    $query->where('slug', $community);
                })
                ->first();
                
            if ($post) {
                return redirect("/communities/{$community}/posts/{$slug}", 301);
            }
            
            // If no post found with that slug, continue to other routes
            return abort(404);
        })->where('slug', '^(?!posts|edit|listings|paginate|curators|submit|name-change|shelves).*$');
        
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

            // Posts - ensure create route is defined before any route with {post} parameter
            Route::GET('/posts/create', [PostController::class, 'create'])->name('posts.create')->middleware('can:update,community');
            Route::POST('/posts', [PostController::class, 'store'])->name('posts.store')->middleware('can:update,community');
            Route::PUT('/posts/order', [PostController::class, 'order'])->name('posts.order')->middleware('can:update,community');
            
            // Post-specific routes
            Route::GET('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit')->middleware('can:update,community');
            Route::POST('/posts/{post}', [PostController::class, 'update'])->name('posts.update')->middleware('can:update,community');
            Route::DELETE('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy')->middleware('can:update,community');

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
        
        // Public routes - place after the protected routes to avoid conflicts
        Route::GET('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    });
});