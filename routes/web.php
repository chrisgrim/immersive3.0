<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\ProfilesController;

Route::GET('/', [IndexController::class, 'index']);


Route::GET('/events/{event}', [EventController::class, 'show']);


Route::GET('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('Organizers.show');


Route::GET('/users/{user}', [ProfilesController::class, 'show']);
Route::GET('/account-settings', [ProfilesController::class, 'account']);
Route::POST('/users/{user}', [ProfilesController::class, 'update']);


require __DIR__.'/auth.php';
