<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);

                // Save Google avatar if available
                if ($googleUser->avatar) {
                    $user->update([
                        'largeImagePath' => $googleUser->avatar,
                        'thumbImagePath' => $googleUser->avatar
                    ]);
                }
            }
            
            Auth::login($user);
            
            return redirect()->intended('/');
        } catch (\Exception $e) {
            Log::error('Google login error: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with Google. Please try again.']);
        }
    }

    public function redirectToApple()
    {
        return Socialite::driver('sign-in-with-apple')
            ->scopes(['name', 'email'])
            ->redirect();
    }

    public function handleAppleCallback(Request $request)
    {
        try {
            $appleUser = Socialite::driver('sign-in-with-apple')->user();
            
            $user = User::where('email', $appleUser->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'apple',
                    'provider_id' => $appleUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                // Note: Apple only sends the name on first login
                $user = User::create([
                    'name' => $appleUser->name ?? explode('@', $appleUser->email)[0],
                    'email' => $appleUser->email,
                    'provider' => 'apple',
                    'provider_id' => $appleUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);
            }
            
            Auth::login($user);
            
            return redirect()->intended('/');
            
        } catch (\Exception $e) {
            Log::error('Apple login error: ' . $e->getMessage());
            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with Apple. Please try again.']);
        }
    }

    public function redirectToGithub()
    {
        return Socialite::driver('github')->redirect();
    }

    public function handleGithubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();
            
            $user = User::where('email', $githubUser->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'github',
                    'provider_id' => $githubUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $githubUser->name ?? $githubUser->nickname,
                    'email' => $githubUser->email,
                    'provider' => 'github',
                    'provider_id' => $githubUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);

                // Save GitHub avatar if available
                if ($githubUser->avatar) {
                    $user->update([
                        'largeImagePath' => $githubUser->avatar,
                        'thumbImagePath' => $githubUser->avatar
                    ]);
                }
            }
            
            Auth::login($user);
            
            return redirect()->intended('/');
            
        } catch (\Exception $e) {
            Log::error('GitHub login error: ' . $e->getMessage());
            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with GitHub. Please try again.']);
        }
    }
}
