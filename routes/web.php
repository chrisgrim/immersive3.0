<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\OrganizerController;

Route::GET('/', [IndexController::class, 'index']);


Route::GET('/events/{event}', [EventController::class, 'show']);


Route::get('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('Organizers.show');



require __DIR__.'/auth.php';
