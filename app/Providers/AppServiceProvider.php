<?php

namespace App\Providers;

use App\Mail\PasskeyChanged;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkeys;
use Laravel\Passport\Passport;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passport's own routes are not used: the handful of OAuth endpoints
        // the MCP server needs are declared in routes/oauth.php and
        // routes/ai.php, so each carries exactly the middleware it should
        // (consent gating, throttles, no CSRF on the token endpoint). Must be
        // called here, before Passport's provider boots and would register them.
        Passport::ignoreRoutes();

        // Same for passkeys: routes/auth.php declares them with EI's own
        // limiters instead of the package's shared throttle:6,1.
        Passkeys::ignoreRoutes();
        $this->app->singleton(
            \Laravel\Passkeys\Contracts\PasskeyLoginResponse::class,
            \App\Http\Responses\PasskeyLoginResponse::class,
        );

        // One per process, so the 'array' analytics buffer (tests) holds.
        $this->app->singleton(\App\Support\Analytics\Analytics::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Support\LocalIsolation::check();

        // Every {{ }} echo also breaks up Vue mustaches in user text, since
        // Vue compiles the whole server-rendered body (see VueSafe).
        Blade::setEchoFormat('\App\Support\VueSafe::e(%s)');

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/reset-password/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Register the "Sign in with Apple" Socialite driver (socialiteproviders/apple).
        // Google/GitHub are built into Socialite core, but Apple is a provider package
        // that must be wired up via the SocialiteWasCalled event.
        EventFacade::listen(SocialiteWasCalled::class, [AppleExtendSocialite::class, 'handle']);

        // Tell the account owner whenever a passkey is added or removed.
        EventFacade::listen(PasskeyRegistered::class, fn (PasskeyRegistered $event) => Mail::to($event->user)
            ->send(new PasskeyChanged(PasskeyChanged::ADDED, $event->passkey->name)));
        EventFacade::listen(PasskeyDeleted::class, fn (PasskeyDeleted $event) => Mail::to($event->user)
            ->send(new PasskeyChanged(PasskeyChanged::REMOVED, $event->passkey->name)));

        $this->configurePassport();
        $this->configureRateLimiting();
    }

    /**
     * OAuth for the MCP server. See routes/ai.php and routes/oauth.php.
     *
     * Two scopes, and the difference between them is the security model:
     * `mcp:use` acts on the token owner's own organizers; `mcp:moderate` is
     * the moderator/admin cross-tenant power (User::credentialAllowsModeration).
     * The consent screen only ever grants `mcp:use` (EnsureMcpConsentAllowed);
     * `mcp:moderate` exists only on personal access tokens a moderator mints
     * deliberately on the API keys page.
     *
     * Passport 13 leaves the password, implicit and device grants off unless
     * enabled, always hashes client secrets, and requires PKCE for public
     * clients — nothing to switch on or off here.
     */
    protected function configurePassport(): void
    {
        Passport::tokensCan([
            'mcp:use' => 'Create and edit events for your organizers',
            User::MODERATE_SCOPE => 'Moderator: read and edit any event or organizer on the platform',
            User::READ_SCOPE => 'Read only: read any published event, change nothing',
        ]);
        Passport::defaultScopes(['mcp:use']);

        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::personalAccessTokensExpireIn(CarbonInterval::days(90));

        Passport::authorizationView('oauth.authorize');
    }

    /**
     * Named limiters for the MCP and OAuth endpoints. Laravel sorts the
     * `throttle` middleware after `auth`, so `mcp` keys on the user once
     * authenticated; the pre-auth, per-IP limit on /mcp is a separate
     * middleware (ThrottleMcpByIp) precisely because it must run first.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)
            ->by('mcp:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('oauth-token', fn (Request $request) => Limit::perMinute(30)
            ->by('oauth-token:'.$request->ip()));

        RateLimiter::for('oauth-register', fn (Request $request) => Limit::perMinute(10)
            ->by('oauth-register:'.$request->ip()));

        RateLimiter::for('oauth-metadata', fn (Request $request) => Limit::perMinute(60)
            ->by('oauth-metadata:'.$request->ip()));

        // "Missing an event?" form. Named (not `throttle:N,M`) so the counters
        // are their own: unnamed throttles share one key per user/IP across
        // every route, which let a challenge fetch reset the send window and
        // let ordinary searching use up a person's send allowance.
        RateLimiter::for('event-suggestion-challenge', fn (Request $request) => Limit::perMinute(30)
            ->by('event-suggestion-challenge:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Analytics beacons (search click, time on page): their own counter,
        // so map panning or nav typing on the shared unnamed throttle cannot
        // use up a visitor's allowance and silently drop the beacon.
        RateLimiter::for('analytics-beacon', fn (Request $request) => Limit::perMinute(60)
            ->by('analytics-beacon:'.$request->ip()));

        // The load ping: one per page view, and a whole office or campus
        // shares an address, so its own, roomier counter.
        RateLimiter::for('analytics-ping', fn (Request $request) => Limit::perMinute(240)
            ->by('analytics-ping:'.$request->ip()));

        // Passkey sign-in. Autofill asks for options on every login page and
        // login modal view, so roomy per IP; the answer is single use and
        // the signature cannot be guessed, so this only stops floods.
        RateLimiter::for('passkey-login', fn (Request $request) => Limit::perMinute(30)
            ->by('passkey-login:'.$request->ip()));

        // Adding and removing passkeys in Account settings.
        RateLimiter::for('passkey-manage', fn (Request $request) => Limit::perMinute(10)
            ->by('passkey-manage:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('event-suggestion-send', fn (Request $request) => Limit::perHour(10)
            ->by('event-suggestion-send:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
