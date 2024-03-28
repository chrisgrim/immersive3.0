<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;

Route::GET('/', [IndexController::class, 'index']);


Route::GET('/events/{eventName}', [EventController::class, 'showEvent']);



require __DIR__.'/auth.php';
