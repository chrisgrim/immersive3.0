<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        // Silently redirect if no code (user cancelled, back button, or direct URL access)
        if (! $request->has('code')) {
            return redirect()->route('login');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // H-S4: refuse to link/login if Google hasn't verified the email.
            // Google always verifies for @gmail.com and Workspace, so this is
            // effectively a defense-in-depth check; reject defensively if the
            // claim is missing or false.
            if (! ($googleUser->user['email_verified'] ?? false)) {
                return redirect()->route('login')
                    ->withErrors(['error' => 'Google has not verified this email. Please verify it on your Google account first.']);
            }

            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'provider' => 'google',
                    'provider_id' => $googleUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);

                // Save Google avatar if available
                if ($googleUser->avatar) {
                    $user->update([
                        'largeImagePath' => $googleUser->avatar,
                        'thumbImagePath' => $googleUser->avatar,
                    ]);
                }
            }

            Auth::login($user);

            return redirect()->intended('/');
        } catch (\Exception $e) {
            Log::error('Google login error: ', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with Google. Please try again.']);
        }
    }

    // How long an in-flight Apple sign-in stays valid (cache marker + binding cookie share
    // this TTL so a slow flow expires from both at once — no half-expired noise).
    private const APPLE_STATE_TTL_MINUTES = 10;

    public function redirectToApple()
    {
        try {
            // Apple posts its callback cross-site (form_post response_mode), so our SameSite=lax
            // session cookie isn't sent with it — the usual session-stored OAuth `state` can't
            // bind the flow (and the provider's own check derives `state` from the signed
            // id_token, which is self-confirming). We defend the callback two ways:
            //   1. a single-use server-side cache marker  -> blocks replay + states we never issued
            //   2. a SameSite=None binding cookie         -> binds the state to THIS browser, so an
            //      attacker who mints a valid state in their own browser can't replay it through a
            //      victim (login-CSRF). SameSite=None+Secure is required for the cookie to survive
            //      Apple's cross-site POST.
            $state = Str::random(40);
            Cache::put('apple_oauth_state:'.$state, true, now()->addMinutes(self::APPLE_STATE_TTL_MINUTES));

            $bindingCookie = cookie(
                'apple_oauth_state',
                $state,
                self::APPLE_STATE_TTL_MINUTES,
                null,
                null,
                true,  // secure (required for SameSite=None)
                true,  // httpOnly
                false, // raw
                'none' // sameSite — must be None so the cookie rides Apple's cross-site form_post back
            );

            return Socialite::driver('apple')
                ->stateless()
                ->scopes(['name', 'email'])
                ->with(['state' => $state])
                ->redirect()
                ->withCookie($bindingCookie);
        } catch (\Exception $e) {
            Log::error('Apple redirect error: '.$e->getMessage());

            return redirect()->route('login')
                ->withErrors(['error' => 'Apple sign-in is unavailable right now. Please try another method.']);
        }
    }

    public function handleAppleCallback(Request $request)
    {
        try {
            $state = $request->input('state');
            $cookieState = $request->cookie('apple_oauth_state');

            // The binding cookie is single-use regardless of outcome.
            Cookie::queue(Cookie::forget('apple_oauth_state'));

            // (1) Did this state originate from a redirect we issued? pull() consumes it, so a
            //     captured callback can't be replayed, and a state we never minted is rejected.
            $issuedByUs = $state && Cache::pull('apple_oauth_state:'.$state);

            // (2) Is the state bound to THIS browser? The SameSite=None cookie set at redirect
            //     time must come back and match. This is what stops login-CSRF: an attacker can
            //     mint a valid state in their own browser, but it lives in their cookie, not the
            //     victim's, so a forged callback through the victim fails here.
            $boundToBrowser = $state && $cookieState && hash_equals($cookieState, $state);

            if (! $issuedByUs || ! $boundToBrowser) {
                $this->reportAppleStateFailure($request, $issuedByUs, $boundToBrowser, $cookieState !== null);

                return redirect()->route('login')
                    ->withErrors(['error' => 'Your Apple sign-in could not be verified. Please try again.']);
            }

            $appleUser = Socialite::driver('apple')->stateless()->user();

            // H-S4: Apple only releases an email once they've verified it, but
            // the JWT carries an `email_verified` claim — historically as a
            // string "true"/"false". Accept either form.
            $verified = $appleUser->user['email_verified'] ?? false;
            if ($verified !== true && $verified !== 'true' && $verified !== 1 && $verified !== '1') {
                return redirect()->route('login')
                    ->withErrors(['error' => 'Apple has not verified this email.']);
            }

            $user = User::where('email', $appleUser->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'apple',
                    'provider_id' => $appleUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user
                // Note: Apple only sends the name on first login
                $user = User::create([
                    'name' => $appleUser->name ?? explode('@', $appleUser->email)[0],
                    'email' => $appleUser->email,
                    'provider' => 'apple',
                    'provider_id' => $appleUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);
            }

            Auth::login($user);

            return redirect()->intended('/');

        } catch (\Exception $e) {
            Log::error('Apple login error: '.$e->getMessage());

            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with Apple. Please try again.']);
        }
    }

    /**
     * Report a failed Apple state/binding check.
     *
     * The signal we most want to watch is "we DID issue this state, but the SameSite=None
     * binding cookie didn't come back / didn't match". That's the fingerprint of a browser
     * (most likely Safari) dropping the cookie — which would silently lock real users out of
     * Apple login — and it also fires on genuine login-CSRF attempts. Either way we want to
     * know. Junk callbacks with a state we never issued (bots, expired, forged-from-nothing)
     * are ignored as noise. Sentry is wrapped so a reporting hiccup can never break login.
     */
    protected function reportAppleStateFailure(Request $request, bool $issuedByUs, bool $boundToBrowser, bool $hadCookie): void
    {
        // Only alert on flows we actually started where the browser binding then failed.
        if (! $issuedByUs || $boundToBrowser) {
            return;
        }

        Log::warning('Apple login binding check failed', [
            'had_binding_cookie' => $hadCookie,
            'user_agent' => $request->userAgent(),
        ]);

        if (! app()->bound('sentry')) {
            return;
        }

        try {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($request, $hadCookie): void {
                $scope->setTag('auth_provider', 'apple');
                $scope->setTag('apple_binding_cookie', $hadCookie ? 'present_mismatch' : 'missing');
                $scope->setContext('apple_login', [
                    'had_binding_cookie' => $hadCookie,
                    'user_agent' => $request->userAgent(),
                    'note' => 'State was issued by us but the SameSite=None binding cookie did not '
                        .'validate. A spike here means browsers are dropping the cookie (Apple login '
                        .'broken for real users), not just isolated login-CSRF attempts.',
                ]);
                // error level (not warning) so the project's existing "Error Monitor" picks it
                // up and its alert notifies us — no separate alert rule needed. It's gated above
                // to real failures only (state we issued + cookie missing/mismatched), so this
                // stays low-volume; a single occurrence is already worth a look.
                \Sentry\captureMessage(
                    'Apple login state-binding failed (dropped cookie or login-CSRF)',
                    \Sentry\Severity::error()
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to report Apple binding failure to Sentry: '.$e->getMessage());
        }
    }

    public function redirectToGithub()
    {
        // H-S4: `user:email` scope lets us call /user/emails in the callback
        // to verify the primary email — GitHub's default /user endpoint does
        // not tell us whether the email is verified.
        return Socialite::driver('github')
            ->scopes(['user:email'])
            ->redirect();
    }

    public function handleGithubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            // H-S4: GitHub doesn't tell us via the user endpoint whether the
            // email is verified — we have to ask /user/emails using the
            // access token, and accept only the primary+verified row.
            $verifiedEmail = $this->fetchVerifiedGithubEmail($githubUser->token);
            if (! $verifiedEmail) {
                return redirect()->route('login')
                    ->withErrors(['error' => 'GitHub has not verified an email on this account. Please verify your primary email at github.com/settings/emails, then try again.']);
            }

            // Use the verified email rather than whatever default GitHub returned —
            // they can disagree if the user has multiple emails.
            $user = User::where('email', $verifiedEmail)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'provider' => 'github',
                    'provider_id' => $githubUser->id,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Create new user — bind to the verified email, not whatever
                // GitHub returns by default.
                $user = User::create([
                    'name' => $githubUser->name ?? $githubUser->nickname,
                    'email' => $verifiedEmail,
                    'provider' => 'github',
                    'provider_id' => $githubUser->id,
                    'email_verified_at' => now(),
                    'type' => 'g', // general user type
                    'newsletter_type' => 'n', // default newsletter setting
                    'silence' => 'n', // default silence setting
                ]);

                // Save GitHub avatar if available
                if ($githubUser->avatar) {
                    $user->update([
                        'largeImagePath' => $githubUser->avatar,
                        'thumbImagePath' => $githubUser->avatar,
                    ]);
                }
            }

            Auth::login($user);

            return redirect()->intended('/');

        } catch (\Exception $e) {
            Log::error('GitHub login error: '.$e->getMessage());

            return redirect()->route('login')
                ->withErrors(['error' => 'Failed to login with GitHub. Please try again.']);
        }
    }

    /**
     * Fetch the user's primary, verified email from GitHub's /user/emails API.
     * Returns the verified email address, or null if no primary+verified row
     * exists (typical when the account was created with an unverified email).
     */
    protected function fetchVerifiedGithubEmail(string $token): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'everything-immersive',
                ])
                ->get('https://api.github.com/user/emails');

            if (! $response->successful()) {
                Log::warning('GitHub /user/emails non-2xx', ['status' => $response->status()]);

                return null;
            }

            foreach ($response->json() ?? [] as $row) {
                if (($row['primary'] ?? false) && ($row['verified'] ?? false)) {
                    return $row['email'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning('GitHub /user/emails exception: '.$e->getMessage());
        }

        return null;
    }
}
