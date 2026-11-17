<?php

namespace App\Providers;

use App\Http\Documentation\AuthDocumentation;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Requests per minute, per client IP, allowed to place or pay orders.
     */
    public const ORDER_REQUESTS_PER_MINUTE = 10;

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
        Model::preventLazyLoading(! $this->app->isProduction());

        URL::forceHttps($this->app->isProduction());

        // Scramble always allows the docs locally; this opens them on other non-production environments
        // (e.g. staging) and keeps them closed in production.
        Gate::define('viewApiDocs', fn ($user = null) => ! $this->app->isProduction());

        // Password reset emails link to the storefront, which posts the new password to /api/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?token='.$token
            .'&email='.urlencode($user->getEmailForPasswordReset()));

        // Used by registration and password reset (Password::default()). Production also rejects passwords
        // found in known data breaches, which needs a call to the Have I Been Pwned API.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(10)->letters()->mixedCase()->numbers());

        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    /**
     * Document authentication in the OpenAPI spec: security schemes, and the Fortify endpoints, which Scramble
     * cannot fully infer from the vendor package.
     */
    private function configureApiDocumentation(): void
    {
        $authDocumentation = new AuthDocumentation;

        Scramble::configure()
            ->withDocumentTransformers(fn (OpenApi $openApi) => $authDocumentation->document($openApi))
            ->withOperationTransformers(fn (Operation $operation, RouteInfo $routeInfo) => $authDocumentation->operation($operation, $routeInfo));
    }

    /**
     * Placing and paying orders get separate per-IP budgets, so paying is never blocked by checkout attempts.
     */
    private function configureRateLimiting(): void
    {
        foreach (['orders.store', 'orders.pay'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => Limit::perMinute(self::ORDER_REQUESTS_PER_MINUTE)->by($request->ip()));
        }
    }
}
