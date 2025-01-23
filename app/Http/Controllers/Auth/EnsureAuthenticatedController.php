<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Curated\CuratorInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\ValidationException;



class EnsureAuthenticatedController extends Controller
{

	public function login(LoginRequest $request)
    {
        
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Check if a password was provided in the request and the user has a password set
            if (!empty($request->password) && !empty($user->password)) {
                // Attempt to authenticate the user with the provided credentials
                try {
                    $request->authenticate();
                    $request->session()->regenerate();

                    return response()->noContent();
                } catch (ValidationException $e) {
                    // Password is incorrect, add additional logic if needed
                    // For now, we rethrow the exception which Laravel will handle
                    throw $e;
                }
            } else if ($user->provider === 'google') {
                // If no password is provided or the user does not have a password set, but the account is linked to Google OAuth
                return response()->json([
                    'errors' => [
                        'email' => ['This account uses Google OAuth. Please log in using Google. If you would like to set up a password, click Forgot Password below.'],
                    ]
                ], 422);
            }
        }

        // If the user does not exist or another condition applies, handle accordingly
        if (!$user) {
            // Attempt to register the user or perform another action
            return $this->register($request);
        }

        // Generic error response if none of the above conditions are met
        return response()->json([
            'errors' => [
                'email' => ['Unable to log in with provided credentials.'],
            ]
        ], 422);
    }

    protected function register(LoginRequest $request)
    {
        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        Auth::login($user);

        $request->session()->regenerate();

        return response()->noContent();
    }



}