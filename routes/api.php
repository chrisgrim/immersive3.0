<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});



//Nav Search 

Route::GET('search/nav/events', [SearchController::class, 'navEvents']);
Route::GET('search/nav/genres', [SearchController::class, 'navGenres']);
