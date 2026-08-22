<?php

namespace App\Http\Middleware;

use App\Models\LoginHistory;
use Closure;
use hisorange\BrowserDetect\Facade as Browser;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordLoginHistory
{
    /**
     * ~1 year — long enough that "the same device" holds across the
     * SESSION_LIFETIME gaps this middleware exists to collapse, without
     * being a permanent, unrenewable identifier.
     */
    private const DEVICE_COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Records one LoginHistory row per session, on the first authenticated
     * request that session makes — not in a Login event listener, since
     * some login paths (e.g. LoginCodeController) call
     * $request->session()->regenerate() right after auth()->login(), which
     * would make an event-time session id stale by the time the browser
     * actually holds it. Reading session()->getId() here, on a normal
     * request, is always the final, live id.
     *
     * A session expiring (SESSION_LIFETIME) and starting fresh is NOT the
     * same thing as a new device — the same laptop coming back the next day
     * would otherwise pile up a near-identical "Chrome on Mac" row every
     * time. So a new session first looks for an existing row for the same
     * browser install and reuses it (bumping updated_at, i.e. "last seen")
     * instead of inserting a new one; only a genuinely new device gets its
     * own row.
     *
     * "Same browser install" is a long-lived, random `ei_device` cookie —
     * NOT browser+platform+ip_address (an earlier version of this matched
     * on that triple), which can collide for two actually-different devices
     * that happen to share all three, e.g. two separate Macs both on Chrome
     * behind the same home router's IP. That would silently merge them into
     * one row, and the second device's session_id would overwrite the
     * first's — the first device stays logged in but disappears from
     * Device History with no way to see or log out of it there. A cookie is
     * unique per browser install regardless of network, so it doesn't have
     * that failure mode — and as a side benefit, it also survives an IP
     * change (e.g. wifi to home network) that would have split the OLD
     * matching into two rows for what's genuinely the same device.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->session()->has('login_history_id')) {
            $sessionId = $request->session()->getId();
            $userId = $request->user()->id;
            $deviceId = $request->cookie('ei_device');

            if (! $deviceId) {
                $deviceId = (string) Str::uuid();
                Cookie::queue(cookie(
                    'ei_device',
                    $deviceId,
                    self::DEVICE_COOKIE_MINUTES,
                    null,
                    null,
                    $request->secure(),
                    true, // httpOnly — never needs to be read by JS
                ));
            }

            $history = LoginHistory::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->first();

            if ($history) {
                $history->update([
                    'session_id' => $sessionId,
                    'ip_address' => $request->ip(),
                    'browser' => Browser::browserName(),
                    'platform' => Browser::platformName(),
                    'device_type' => Browser::deviceType(),
                ]);
            } else {
                try {
                    $history = LoginHistory::create([
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'device_id' => $deviceId,
                        'ip_address' => $request->ip(),
                        'browser' => Browser::browserName(),
                        'platform' => Browser::platformName(),
                        'device_type' => Browser::deviceType(),
                    ]);
                } catch (QueryException $e) {
                    // Two parallel first-requests of a brand new session can both
                    // pass the has('login_history_id') check above before either
                    // has written it — the session_id unique constraint (see
                    // migration 2026_08_17_073154) turns the loser into a
                    // duplicate-key error here instead of a duplicate row; fall
                    // back to the row the winner already created.
                    if (! str_contains($e->getMessage(), '1062') && ! str_contains(strtolower($e->getMessage()), 'unique')) {
                        throw $e;
                    }

                    $history = LoginHistory::where('session_id', $sessionId)->firstOrFail();

                    // If neither request had an ei_device cookie yet, both
                    // generated their own random UUID above and both queued
                    // it as this response's Set-Cookie. Only the winner's
                    // UUID actually made it into the DB row — re-queue the
                    // winning value here so the loser's response (which may
                    // well be the one the browser keeps) doesn't leave the
                    // browser holding a device_id that matches no row,
                    // which would otherwise mint a brand new "device" on the
                    // user's very next session.
                    if ($history->device_id !== $deviceId) {
                        Cookie::queue(cookie(
                            'ei_device',
                            $history->device_id,
                            self::DEVICE_COOKIE_MINUTES,
                            null,
                            null,
                            $request->secure(),
                            true,
                        ));
                    }
                }
            }

            $request->session()->put('login_history_id', $history->id);

            // Keep it bounded — a device history is for recognizing recent
            // sign-ins, not an unbounded audit log. Ordered by updated_at
            // (not created_at/id — a reused row above can be much older than
            // its last-seen bump) so the row just touched is never the one
            // pruned.
            $idsToKeep = LoginHistory::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(20)
                ->pluck('id');

            LoginHistory::where('user_id', $userId)
                ->whereNotIn('id', $idsToKeep)
                ->delete();
        }

        return $next($request);
    }
}
