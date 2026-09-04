<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/reset-password/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Register the "Sign in with Apple" Socialite driver (socialiteproviders/apple).
        // Google/GitHub are built into Socialite core, but Apple is a provider package
        // that must be wired up via the SocialiteWasCalled event.
        EventFacade::listen(SocialiteWasCalled::class, [AppleExtendSocialite::class, 'handle']);

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
    }
}
