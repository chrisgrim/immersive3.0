<?php

namespace App\Http\Controllers\AccountSettings;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\Request;

class LoginSecurityController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        // This route sits behind auth:sanctum, not the web session middleware
        // directly — a session is normally still available for it (the SPA's
        // own same-origin axios calls ride Sanctum's stateful-domain cookie
        // bridge), but that bridge depends on request headers a test client
        // doesn't send the way a browser does, so guard rather than assume.
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        return response()->json([
            'provider' => $user->provider,
            // updated_at, not created_at/id — RecordLoginHistory reuses a row
            // (bumping updated_at) for the same browser/platform/ip instead of
            // inserting a new one each time a session expires and restarts, so
            // updated_at is "last seen," the thing worth sorting/displaying by.
            'devices' => LoginHistory::where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (LoginHistory $device) => [
                    'id' => $device->id,
                    'browser' => $device->browser,
                    'platform' => $device->platform,
                    'device_type' => $device->device_type,
                    'last_seen_at' => $device->updated_at,
                    'isCurrent' => $device->session_id === $currentSessionId,
                ]),
        ]);
    }

    /**
     * Ends the given device's session for real — not just deleting the
     * history row, but invalidating the actual session it points to via the
     * configured session handler's own destroy(), so that device is signed
     * out on its next request regardless of which driver (Redis, database,
     * etc.) SESSION_DRIVER is set to.
     */
    public function destroy(Request $request, LoginHistory $device)
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        if ($device->session_id) {
            // Resolved independently of the current request's own session
            // (see the same guard in show()) — this only needs a working
            // handler for the configured driver, not one bootstrapped for
            // this specific request.
            app('session')->driver()->getHandler()->destroy($device->session_id);
        }

        $device->delete();

        return response()->json(['message' => 'Device logged out']);
    }
}
