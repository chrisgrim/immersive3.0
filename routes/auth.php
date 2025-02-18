<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Main auth routes
Route::get('/login', function () { return view('auth.access'); })
                ->middleware('guest')
                ->name('login');

Route::get('/register', function () { return view('auth.access'); })
                ->middleware('guest')
                ->name('register');

// Code-based login
Route::post('/login/code', [LoginCodeController::class, 'sendCode'])
                ->middleware('guest')
                ->name('login.code.send');

Route::post('/login/verify', [LoginCodeController::class, 'verify'])
                ->middleware('guest')
                ->name('login.code.verify');

// Auto-login from email
Route::get('/login/auto/{code}', [LoginCodeController::class, 'autoLogin'])
                ->middleware('guest')
                ->name('login.auto');

// Social login
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])
                ->name('auth.google');

Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

// Logout
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth')
                ->name('logout');

