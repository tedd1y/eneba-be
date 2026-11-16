<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        $this->configureRateLimiting();
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
