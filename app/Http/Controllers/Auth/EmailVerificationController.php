<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerificationCode;

class EmailVerificationController extends Controller
{
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:users,email,' . auth()->id()],
        ]);

        // Generate a 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store the code in cache with the user's ID and email
        $key = 'email_verification_' . auth()->id();
        Cache::put($key, [
            'code' => $code,
            'email' => $request->email
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

        $key = 'email_verification_' . auth()->id();
        $cached = Cache::get($key);

        if (!$cached || 
            $cached['code'] !== $request->code || 
            $cached['email'] !== $request->email) {
            return response()->json(['message' => 'Invalid verification code'], 422);
        }

        // Update the user's email
        auth()->user()->update(['email' => $request->email]);
        
        // Clear the verification code
        Cache::forget($key);

        return response()->json(['message' => 'Email updated successfully']);
    }
}
