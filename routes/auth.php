<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginCodeController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Main auth routes
Route::get('/login', function () {
    return view('auth.access');
})
    ->middleware('guest')
    ->name('login');

Route::get('/register', function () {
    return view('auth.access');
})
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

// Social login - Google
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])
    ->name('auth.google');

Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

// Social login - GitHub
Route::get('/auth/github', [SocialAuthController::class, 'redirectToGithub'])
    ->name('auth.github');

Route::get('/auth/github/callback', [SocialAuthController::class, 'handleGithubCallback']);

// Social login - Apple. The callback is a POST because requesting name/email scopes
// makes Apple use response_mode=form_post (it POSTs the result from apple.com), so the
// callback route is also CSRF-exempt (see bootstrap/app.php).
Route::get('/auth/apple', [SocialAuthController::class, 'redirectToApple'])
    ->name('auth.apple');

Route::post('/auth/apple/callback', [SocialAuthController::class, 'handleAppleCallback']);

// Logout
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Email verification routes
Route::post('/users/email/verify', [EmailVerificationController::class, 'sendVerificationCode'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

Route::post('/users/email/confirm', [EmailVerificationController::class, 'verifyCode'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.verify');
