<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Login attempts per minute, per email + IP.
     */
    public const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Login attempts per minute for one email from any IP, so rotating IPs does not get around the limit.
     */
    public const LOGIN_ATTEMPTS_PER_EMAIL_PER_MINUTE = 10;

    /**
     * Registrations per minute, per IP.
     */
    public const REGISTRATIONS_PER_MINUTE = 5;

    /**
     * Password reset link requests per minute, per IP. The password broker also allows only one email per
     * address per minute (`auth.passwords.users.throttle`).
     */
    public const RESET_LINK_REQUESTS_PER_MINUTE = 5;

    /**
     * Password reset link requests per minute for one email from any IP.
     */
    public const RESET_LINK_REQUESTS_PER_EMAIL_PER_MINUTE = 3;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Login: 5 attempts per minute per email + IP, and 10 per minute per email from any IP.
        RateLimiter::for('login', function (Request $request) {
            $email = $this->throttleEmail($request);

            return [
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)->by($email.'|'.$request->ip()),
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_EMAIL_PER_MINUTE)->by('login-email|'.$email),
            ];
        });

        // Applied to every Fortify route (see fortify.middleware); only registration and reset-link requests are limited.
        RateLimiter::for('auth', fn (Request $request) => match ($request->route()?->getName()) {
            'register.store' => Limit::perMinute(self::REGISTRATIONS_PER_MINUTE)->by('register|'.$request->ip()),
            'password.email' => [
                Limit::perMinute(self::RESET_LINK_REQUESTS_PER_MINUTE)->by('forgot-password|'.$request->ip()),
                Limit::perMinute(self::RESET_LINK_REQUESTS_PER_EMAIL_PER_MINUTE)->by('forgot-password-email|'.$this->throttleEmail($request)),
            ],
            default => Limit::none(),
        });
    }

    /**
     * The submitted email, normalised so case and accents do not create separate rate-limit buckets.
     */
    private function throttleEmail(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input(Fortify::email())));
    }
}
