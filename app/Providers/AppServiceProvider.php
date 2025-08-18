<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Auth\Notifications\ResetPassword;

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
        RateLimiter::for('login', function (Request $request): Limit {
            $email = (string) str($request->input('email', ''))->lower();
            return Limit::perMinutes(15, 5)->by($email.'|'.$request->ip());
        });

        // Customize the password reset URL used in emails so it doesn't rely on a missing named route
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $email = urlencode($notifiable->getEmailForPasswordReset());
            // Point to a frontend URL you control; for local testing we’ll just use app.url
            return config('app.url')."/reset-password?token={$token}&email={$email}";
        });
    }
}
