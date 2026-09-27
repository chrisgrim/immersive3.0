<?php

namespace App\Http\Controllers;

use App\Models\EventSuggestion;
use App\Support\Spam\ProofOfWork;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The footer's "Suggest an event" form. Open to everyone; spam is held off by a
 * honeypot field, the proof-of-work check, and the route's per-hour throttle.
 */
class EventSuggestionController extends Controller
{
    public const HONEYPOT = 'ei_hp_extra';

    public function challenge(ProofOfWork $pow)
    {
        return response()->json($pow->challenge());
    }

    public function store(Request $request, ProofOfWork $pow)
    {
        // Honeypot: a field hidden from people that naive bots fill in. Answer
        // exactly like a real success so the bot learns nothing. Deliberately
        // not called "website"/"email" etc., which browser autofill could fill.
        if (filled($request->input(self::HONEYPOT))) {
            return response()->json(['message' => 'Thanks for the suggestion!'], 201);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:'.EventSuggestion::MAX_LENGTH,
            'altcha' => 'required|string',
        ]);

        if (! $pow->verify($validated['altcha'])) {
            throw ValidationException::withMessages([
                'altcha' => 'We couldn\'t confirm you\'re not a robot. Please try again.',
            ]);
        }

        $user = $request->user();

        EventSuggestion::create([
            'message' => trim($validated['message']),
            // Guests are anonymous. For a logged-in user we keep only the link
            // (their email is read through it), so deleting the account
            // leaves nothing personal behind.
            'user_id' => $user?->id,
        ]);

        return response()->json(['message' => 'Thanks for the suggestion!'], 201);
    }
}
