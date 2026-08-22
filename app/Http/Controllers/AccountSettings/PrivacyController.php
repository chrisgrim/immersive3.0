<?php

namespace App\Http\Controllers\AccountSettings;

use App\Http\Controllers\Controller;
use App\Mail\PersonalDataRequestInternalNotice;
use App\Mail\PersonalDataRequestReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PrivacyController extends Controller
{
    /**
     * The two profile-visibility toggles — both opt-OUT (default on); a user
     * has to explicitly turn one off to hide it. See User::showsPublicly().
     */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'privacy_preferences' => [
                'followed_organizers' => $user->showsPublicly('followed_organizers'),
                'saved_events_count' => $user->showsPublicly('saved_events_count'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'followed_organizers' => 'required|boolean',
            'saved_events_count' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->update([
            // Merge, not replace — this column could grow more keys later
            // (same reasoning as notification_preferences elsewhere).
            'privacy_preferences' => array_merge($user->privacy_preferences ?? [], $validated),
        ]);

        return response()->json(['privacy_preferences' => $validated]);
    }

    /**
     * "Request my personal data" — an honest, real action rather than a
     * fake instant-export button: confirms receipt to the user and routes
     * the request to the team's existing contact address (see terms.blade.php)
     * for manual fulfillment. A full automated export is a larger feature
     * than fits in this pass.
     */
    public function requestData(Request $request)
    {
        $user = $request->user();

        Mail::to($user->email)->send(new PersonalDataRequestReceived($user));
        Mail::to(config('mail.legal_email'))->send(new PersonalDataRequestInternalNotice($user));

        return response()->json(['message' => 'Request received']);
    }
}
