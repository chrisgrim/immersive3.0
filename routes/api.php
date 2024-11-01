<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\Creation\HostEventController;
use App\Models\Category;
use App\Models\Events\RemoteLocation;
use App\Models\Events\ContactLevel;
use App\Models\Events\ContentAdvisory;
use App\Models\Events\MobilityAdvisory;
use App\Models\Events\InteractiveLevel;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::POST('/hosting/event/{event}', [HostEventController::class, 'update'])->name('event.update');


//LISTS
Route::GET('/categories', function () {
    $categories = Category::orderBy('name')->when(request()->has('remote'), function ($query) { $query->where('remote', request()->query('remote')); })->get();
    return response()->json($categories);
});

Route::GET('/remotelocations', function () { return RemoteLocation::all(); });
Route::GET('/contactlevels', function () { return ContactLevel::all(); });
Route::GET('/interactivelevels', function () { return InteractiveLevel::all(); });
Route::GET('/contentadvisories', function () { return ContentAdvisory::where('admin', true)->orWhere('user_id', auth()->user()->id)->get(); });
Route::GET('/mobilityadvisories', function () { return MobilityAdvisory::where('admin', true)->orWhere('user_id', auth()->user()->id)->get(); });






//Nav Search 

Route::GET('search/nav/events', [SearchController::class, 'navEvents']);
Route::GET('search/nav/genres', [SearchController::class, 'navGenres']);


