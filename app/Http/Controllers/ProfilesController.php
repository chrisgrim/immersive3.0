<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ImageHandler;
use App\Http\Requests\StoreProfileRequest;
use Illuminate\Support\Facades\Log;


class ProfilesController extends Controller
{

    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except('show');
    }


    public function show(User $user)
    {
        $user
        // ->load('location', 'favouritedEvents')
        ->makeHidden(['newsletter_type', 'type', 'hasMessages', 'hasCreatedOrganizers', 'current_team_id', 'card_brand', 'card_last_four', 'email', 'stripe_id']);
        return view('Auth.user-profile', compact('user'));
    }

    public function account()
    {
        return view('Auth.user-account');
    }


    public function update(StoreProfileRequest $request, User $user)
    {
        Log::info($request->file('image'));
        Log::info($request->all());
        if ($request->hasFile('image')) {
            ImageHandler::saveImage($request, $user, 400, 400, 'user');
        } else {
            return response()->json(['error' => 'No image file uploaded'], 400);
        }

        $userData = $request->only('name', 'email', 'newsletter_type') +
                    ['silence' => $request->has('messages') ? 'n' : 'y'];

        if ($request->filled('email') && $request->email != $user->email) {
            $userData['email_verified_at'] = null;
            $user->update($userData);
            $user->sendEmailVerificationNotification(); // Send only if email changed
        } else {
            $user->update($userData);
        }

        return $user->fresh();
    }

    public function destroy(User $user)
    {
        $this->authorize('update', $user);
        $user->conversations()->detach();
        $user->delete();
    }


}
