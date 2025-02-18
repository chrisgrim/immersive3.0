<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginCodeController extends Controller
{
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

        try {
            // Send code email
            Mail::to($user)->send(new LoginCode($code));
            
            // Log successful send
            \Log::info('Login code email sent', [
                'email' => $validated['email']
            ]);
            
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Failed to send login code email', [
                'error' => $e->getMessage(),
                'email' => $validated['email']
            ]);
            
            throw $e;
        }

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

        // Find user and verify email if not already verified
        $user = User::findOrFail($cached['user_id']);
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }
        
        // Login with remember me
        auth()->login($user, true); // The true parameter enables "remember me"
        
        // Remove used code
        Cache::forget($cacheKey);

        // Generate session
        $request->session()->regenerate();

        return response()->json([
            'redirect' => '/'
        ]);
    }

    public function autoLogin($code)
    {
        // Get the email from the query string
        $email = request()->query('email');
        
        // Store both code and email in the session
        session()->flash('auto_code', $code);
        session()->flash('auto_email', $email);
        
        // Log for debugging
        \Log::info('Auto Login Data', [
            'code' => $code,
            'email' => $email
        ]);
        
        // Redirect to login page
        return redirect()->route('login');
    }
}