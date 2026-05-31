<?php

namespace App\Providers;

use App\Models\Event;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
