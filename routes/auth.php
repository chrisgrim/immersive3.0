<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\EnsureAuthenticatedController;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Auth\SocialAuthController;



Route::post('/authenticate', [EnsureAuthenticatedController::class, 'login'])
                ->middleware('guest')
                ->name('authenicate');

Route::post('/register', [RegisteredUserController::class, 'store'])
                ->middleware('guest')
                ->name('register');

Route::get('/login', function () { return view('auth.access'); })
                ->middleware('guest')
                ->name('login');

Route::get('/register', function () { return view('auth.access'); })
                ->middleware('guest')
                ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('guest')
                ->name('login');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
                ->middleware('guest')
                ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
                ->middleware('guest')
                ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['auth', 'signed', 'throttle:6,1'])
                ->name('verification.verify');

Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware(['auth', 'throttle:6,1'])
                ->name('verification.send');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth')
                ->name('logout');

Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

Route::get('/reset-password/{token}', function () { return view('auth.reset-password'); })
                ->middleware('guest')
                ->name('password.reset');

Route::get('/verification/sent', function () { return view('Auth.email-verification'); })
                ->name('verification.sent');    

Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])
                ->name('auth.google');

Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

