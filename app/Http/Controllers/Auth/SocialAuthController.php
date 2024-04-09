<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite as FacadesSocialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return FacadesSocialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = FacadesSocialite::driver('google')->user();
            
            $user = User::updateOrCreate([
                'email' => $googleUser->email,
            ], [
                'google_id' => $googleUser->id,
                // Additional fields as necessary
            ]);
            
            Auth::login($user);
            
            return redirect('/dashboard');
        } catch (\Exception $e) {
            // Handle exception, possibly log it and redirect with an error message
            return redirect('/')->withErrors(['error' => 'Failed to login with Google.']);
        }
    }
}
