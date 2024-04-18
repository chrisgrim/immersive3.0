<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
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
        try {
            if ($request->hasFile('image')) {
                ImageHandler::saveImage($request, $user, 400, 400, 'user');
            }

            $userData = $request->only('name', 'email') + [
                'newsletter_type' => $request->input('newsletter_type', 'n'),
                'silence' => $request->input('silence', 'y')
            ];

            if ($request->filled('email') && $request->email != $user->email) {
                $userData['email_verified_at'] = null;
                $user->update($userData);
                $user->sendEmailVerificationNotification();
            } else {
                $user->update($userData);
            }

            return $user->fresh();

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(User $user)
    {
        $this->authorize('update', $user);
        $user->conversations()->detach();
        $user->delete();
    }


}
