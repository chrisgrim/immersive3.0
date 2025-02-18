<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email']
        ]);
        
        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => explode('@', $validated['email'])[0]]
        );

        // Generate 6 digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code with user ID in cache for 15 minutes
        Cache::put(
            "login_code_{$validated['email']}", 
            ['code' => $code, 'user_id' => $user->id],
            now()->addMinutes(15)
        );

        // Send code email
        Mail::to($user)->send(new LoginCode($code));

        return response()->json([
            'message' => 'We sent you a login code! Please check your email.',
            'email' => $validated['email']
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6']
        ]);

        $cacheKey = "login_code_{$validated['email']}";
        
        $cached = Cache::get($cacheKey);
        
        if (!$cached) {
            throw ValidationException::withMessages([
                'code' => ['This code has expired. Please request a new one.']
            ]);
        }

        if ($cached['code'] !== $validated['code']) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code. Please try again.']
            ]);
        }

        // Find user and log them in
        $user = User::findOrFail($cached['user_id']);
        auth()->login($user);
        
        // Remove used code
        Cache::forget($cacheKey);

        // Generate session
        $request->session()->regenerate();

        return response()->json([
            'redirect' => '/'
        ]);
    }

    public function logout(Request $request)
    {
        auth()->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}