<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
        try {
            $request->authenticate();
            $request->session()->regenerate();
            return response()->noContent();

        } catch (ValidationException $e) {

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return $this->register($request);
            }
            throw $e;
        }
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