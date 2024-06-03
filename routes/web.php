<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\User\ProfilesController;
use App\Http\Controllers\User\ConversationsController;
use App\Http\Controllers\Creation\HostController;
use App\Http\Controllers\Creation\HostEventController;

Route::GET('/', [IndexController::class, 'index']);


Route::GET('/events/{event}', [EventController::class, 'show']);


Route::GET('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('Organizers.show');
Route::GET('/teams', [OrganizerController::class, 'teams'])->name('Organizers.teams');
Route::POST('/teams/switch/{organizer}', [OrganizerController::class, 'switchTeam'])->name('team.switch');



//Event Creation
Route::GET('/hosting/events', [HostController::class, 'show']);
Route::GET('/hosting/getting-started', [HostController::class, 'intro']);
Route::GET('/hosting/event/{event}/edit', [HostEventController::class, 'edit'])->name('event.edit');


Route::POST('/organizers', [OrganizerController::class, 'store'])->name('Organizers.store');




Route::GET('/users/{user}', [ProfilesController::class, 'show']);
Route::GET('/account-settings', [ProfilesController::class, 'account']);
Route::POST('/users/{user}', [ProfilesController::class, 'update']);


//User Messages
Route::GET('/inbox', [ConversationsController::class, 'index']);
Route::GET('/inbox/fetch/conversation/{conversation}', [ConversationsController::class, 'show']);
Route::POST('/inbox/conversation/{conversation}', [ConversationsController::class, 'update']);
Route::POST('/inbox/fetch/events', [ConversationsController::class, 'events']);

require __DIR__.'/auth.php';
