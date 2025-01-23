<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\OrganizerController;
use App\Http\Controllers\User\ProfilesController;
use App\Http\Controllers\User\ConversationsController;
use App\Http\Controllers\Search\ListingsController;
use App\Http\Controllers\Creation\HostController;
use App\Http\Controllers\Creation\HostEventController;
use App\Http\Controllers\Admin\DashboardController;


Route::GET('/', [IndexController::class, 'index']);

Route::GET('/index/search', [ListingsController::class, 'index']);

Route::view('/menu', 'Nav.menu-mobile')
    ->middleware(['auth'])
    ->name('menu');

Route::GET('/events/{event}', [EventController::class, 'show']);


Route::GET('/organizers/{organizer}', [OrganizerController::class, 'show'])->name('Organizers.show');
Route::GET('/organizers/{organizer}/edit', [OrganizerController::class, 'edit'])->name('Organizers.edit');
Route::GET('/teams', [OrganizerController::class, 'teams'])->name('Organizers.teams');
Route::POST('/teams/switch/{organizer}', [OrganizerController::class, 'switchTeam'])->name('team.switch');



//Event Creation
Route::GET('/hosting/events', [HostController::class, 'show']);
Route::GET('/hosting/getting-started', [HostController::class, 'intro']);
Route::GET('/hosting/event/{event}/edit', [HostEventController::class, 'edit'])->name('event.edit');
Route::POST('/hosting/event/{event}/submit', [HostEventController::class, 'submit'])->name('event.submit');
Route::DELETE('/hosting/event/{event}', [HostEventController::class, 'destroy'])->name('event.destroy');
Route::POST('/hosting/event/create', [HostEventController::class, 'create'])->name('event.create');
Route::POST('/hosting/event/{event}/name-change', [HostEventController::class, 'nameChange'])->name('event.nameChange');


Route::post('/organizers', [OrganizerController::class, 'store'])->name('organizers.store');
Route::post('/organizers/{organizer}', [OrganizerController::class, 'update'])->name('organizers.update');
Route::post('/organizers/{organizer}/image', [OrganizerController::class, 'updateImage'])->name('organizers.updateImage');
Route::post('/organizers/{organizer}/name-change', [OrganizerController::class, 'requestNameChange'])->name('organizers.name-change');

Route::GET('/users/{user}', [ProfilesController::class, 'show']);
Route::GET('/account-settings', [ProfilesController::class, 'account']);
Route::POST('/users/{user}', [ProfilesController::class, 'update']);


//User Messages
Route::GET('/inbox', [ConversationsController::class, 'index']);
Route::GET('/inbox/fetch/conversation/{conversation}', [ConversationsController::class, 'show']);
Route::POST('/inbox/conversation/{conversation}', [ConversationsController::class, 'update']);
Route::POST('/inbox/fetch/events', [ConversationsController::class, 'events']);

// Admin Routes
Route::GET('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.index');

require __DIR__.'/auth.php';
require __DIR__.'/curated.php';
