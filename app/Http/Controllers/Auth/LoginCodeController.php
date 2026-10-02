<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class LoginCodeController extends Controller
{
    /** Wrong codes allowed per email per 15 minutes. */
    public const VERIFY_ATTEMPTS = 20;

    /**
     * Per-IP ceilings, far above what people behind one shared connection
     * (an office, a venue's Wi-Fi) would ever reach; they only stop a script
     * cycling through many addresses from one machine.
     */
    public const IP_SENDS_PER_HOUR = 100;

    public const IP_VERIFIES_PER_15_MINUTES = 400;

    /**
     * One spelling per account. Emails match case-insensitively in MySQL, so
     * VICTIM@x.com and victim@x.com are the same user, and every counter and
     * the cached code must be keyed the same way for both.
     */
    private function normalizedEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Counts one attempt and returns the new total. Atomic, so parallel
     * requests cannot all read the same count and slip under the limit.
     *
     * If the key expires between add() and increment(), Redis recreates it
     * with no expiry and the counter would never reset, locking the email
     * out. A first hit we did not add ourselves is that case, so give it
     * its expiry back (the same guard as Laravel's RateLimiter::increment).
     */
    private function countAttempt(string $key, \DateTimeInterface $expires): int
    {
        $added = Cache::add($key, 0, $expires);

        $attempts = (int) Cache::increment($key);

        if (! $added && $attempts === 1) {
            Cache::put($key, 1, $expires);
        }

        return $attempts;
    }

    private function throttleIp(Request $request, string $action, int $max, int $decaySeconds, string $field): void
    {
        $key = "login_code_ip:{$action}:".$request->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            throw ValidationException::withMessages([
                $field => ["Too many login attempts from this connection. Please try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.'],
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $validated['email'] = $this->normalizedEmail($validated['email']);

        $this->throttleIp($request, 'send', self::IP_SENDS_PER_HOUR, 3600, 'email');

        // Rate limiting: Max 5 code requests per email per hour
        $rateLimitKey = 'login_code_requests:'.$validated['email'];

        if ($this->countAttempt($rateLimitKey, now()->addHour()) > 5) {
            throw ValidationException::withMessages([
                'email' => ['Too many login attempts. Please try again in 1 hour.'],
            ]);
        }

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $validated['email']],
            ['name' => explode('@', $validated['email'])[0]]
        );

        // Generate 6 digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store code with user ID in cache for 15 minutes
        Cache::put(
            "login_code_{$validated['email']}",
            ['code' => $code, 'user_id' => $user->id],
            now()->addMinutes(15)
        );

        try {
            // Send code email
            Mail::to($user)->send(new LoginCode($code));

        } catch (TransportExceptionInterface $e) {
            // The mail provider is unreachable or refused the connection.
            // Keep Sentry informed and tell the visitor plainly to try again
            // shortly. The attempt still counts toward the hourly limit: that
            // is the only thing throttling repeat hits on a failing mail
            // server, and refunding it races with concurrent requests.
            report($e);

            return response()->json([
                'message' => 'We could not send the email right now.',
                'errors' => [
                    'email' => ['We couldn\'t send the email right now. Please try again in a few minutes.'],
                ],
            ], 503);
        }

        return response()->json([
            'message' => 'We sent you a login code! Please check your email.',
            'email' => $validated['email'],
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $validated['email'] = $this->normalizedEmail($validated['email']);

        $this->throttleIp($request, 'verify', self::IP_VERIFIES_PER_15_MINUTES, 900, 'code');

        // Rate limiting: Max VERIFY_ATTEMPTS tries per email per 15 minutes.
        // Every try counts up front (a right code clears the counter below).
        $rateLimitKey = 'login_verify_attempts:'.$validated['email'];

        if ($this->countAttempt($rateLimitKey, now()->addMinutes(15)) > self::VERIFY_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['Too many failed attempts. Please request a new code.'],
            ]);
        }

        $cacheKey = "login_code_{$validated['email']}";

        $cached = Cache::get($cacheKey);

        if (! $cached) {
            throw ValidationException::withMessages([
                'code' => ['This code has expired. Please request a new one.'],
            ]);
        }

        if (! hash_equals($cached['code'], $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => ['Invalid code. Please try again.'],
            ]);
        }

        // Find user and verify email if not already verified
        $user = User::findOrFail($cached['user_id']);
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        // Login with remember me
        auth()->login($user, true); // The true parameter enables "remember me"

        // Remove used code and clear verification attempts
        Cache::forget($cacheKey);
        Cache::forget($rateLimitKey);

        // Generate session
        $request->session()->regenerate();

        // Back to where the guest redirect sent them from — the OAuth consent
        // screen, when an assistant asked to connect (routes/oauth.php) — and
        // the home page otherwise. Social sign-in already did this.
        return response()->json([
            'redirect' => redirect()->intended('/')->getTargetUrl(),
        ]);
    }

    public function autoLogin($code)
    {
        // Validate the email param before flashing it — otherwise an attacker
        // could craft /login/auto/<code>?email=anything and prefill the login
        // form with arbitrary text. The verify path still requires the cached
        // code to match the email, so this is just hardening.
        $validated = request()->validate([
            'email' => 'required|email',
        ]);

        session()->flash('auto_code', $code);
        session()->flash('auto_email', $validated['email']);

        return redirect()->route('login');
    }
}
