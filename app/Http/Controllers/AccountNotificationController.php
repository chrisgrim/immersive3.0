<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Account Settings > Notifications: the email switches (User::NOTIFICATION_KEYS).
 * Every switch is opt-out; a never-set one reads as its fallback
 * (User::wantsNotification). Turning a switch off only stops that email: the
 * in-app feed and inbox are unchanged.
 */
class AccountNotificationController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($this->present($request->user()));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $allowed = $this->shownKeys($user);

        $validated = $request->validate(
            collect($allowed)->mapWithKeys(fn ($key) => [$key => ['sometimes', 'boolean']])->all()
        );

        DB::transaction(function () use ($user, $validated) {
            // Read-modify-write on one JSON column: locked, so two quick
            // toggles cannot lose one.
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $before = $locked->notificationSettings();
            $prefs = $locked->notification_preferences ?? [];

            foreach ($validated as $key => $value) {
                $prefs[$key] = (bool) $value;
            }
            // Fallbacks are frozen on the first write, so they never shift later:
            // claims keeps what it was before organizers changed, and the old
            // "don't email me" answer stays with messages and closing_soon.
            foreach (['claims', 'messages', 'closing_soon'] as $key) {
                if (! array_key_exists($key, $prefs)) {
                    $prefs[$key] = $before[$key];
                }
            }

            $locked->forceFill(['notification_preferences' => $prefs])->save();
        });

        return response()->json($this->present($user->refresh()));
    }

    /** Which switches this person sees (and may change). */
    private function shownKeys(User $user): array
    {
        $keys = ['messages', 'saved_event_dates', 'followed_organizer_events'];

        // The saved-search alert is a staff pilot (NotifySavedSearchMatchesCommand).
        if (in_array($user->type, ['m', 'a'], true)) {
            $keys[] = 'saved_search_matches';
        }
        // The "ending soon" reminder goes to an organization's owner.
        if (Organizer::where('user_id', $user->id)->exists()) {
            $keys[] = 'closing_soon';
        }
        if ($user->isAdmin()) {
            array_push($keys, ...User::ADMIN_NOTIFICATION_KEYS);
        }

        return $keys;
    }

    private function present(User $user): array
    {
        $settings = $user->notificationSettings();

        return [
            'settings' => collect($this->shownKeys($user))->mapWithKeys(fn ($key) => [$key => $settings[$key]])->all(),
        ];
    }
}
