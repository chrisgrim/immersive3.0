<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class EmailVerificationController extends Controller
{
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:users,email,'.auth()->id()],
        ]);

        // Generate a 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store the code in cache with the user's ID and email
        $key = 'email_verification_'.auth()->id();
        Cache::put($key, [
            'code' => $code,
            'email' => $request->email,
        ], now()->addMinutes(10));

        // Send the code via email
        Mail::to($request->email)->send(new EmailVerificationCode($code));

        return response()->json(['message' => 'Verification code sent']);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $key = 'email_verification_'.auth()->id();
        $attemptsKey = $key.'_attempts';
        $cached = Cache::get($key);

        if (! $cached ||
            $cached['code'] !== $request->code ||
            $cached['email'] !== $request->email) {
            // Burn the code after too many wrong guesses so it can't be brute-forced
            // within its 10-minute window (defence-in-depth on top of route throttling).
            $attempts = (int) Cache::get($attemptsKey, 0) + 1;

            if ($attempts >= 5) {
                Cache::forget($key);
                Cache::forget($attemptsKey);

                return response()->json([
                    'message' => 'Too many invalid attempts. Please request a new code.',
                ], 429);
            }

            Cache::put($attemptsKey, $attempts, now()->addMinutes(10));

            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        // Receiving and confirming this code at the new address IS proof of
        // ownership, so the change also marks the email verified — otherwise a
        // user who verifies their new address here would incorrectly show as
        // "not verified" until some unrelated future re-verification.
        auth()->user()->update([
            'email' => $request->email,
            'email_verified_at' => now(),
        ]);

        // Clear the verification code + attempt counter
        Cache::forget($key);
        Cache::forget($attemptsKey);

        return response()->json(['message' => 'Email updated successfully']);
    }
}
