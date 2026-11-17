<?php

namespace App\Providers;

use App\Http\Documentation\AuthDocumentation;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Requests per minute, per client IP, allowed to place or pay orders.
     */
    public const ORDER_REQUESTS_PER_MINUTE = 10;

    /**
     * Magic link requests per minute, per client IP.
     */
    public const MAGIC_LINK_REQUESTS_PER_MINUTE = 5;

    /**
     * Magic link requests per minute for one email address, from any IP.
     */
    public const MAGIC_LINK_REQUESTS_PER_EMAIL_PER_MINUTE = 3;

    /**
     * Magic link verification attempts per minute, per client IP.
     */
    public const MAGIC_LINK_VERIFICATIONS_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Used by the Brevo mail transport; tests swap it for a mock to avoid calling Brevo.
        $this->app->singleton(HttpClientInterface::class, fn () => HttpClient::create());
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

        $this->configureRateLimiting();
        $this->configureApiDocumentation();
        $this->configureBrevoMailer();
    }

    /**
     * Document authentication in the OpenAPI spec: the session security scheme and which operations need it.
     */
    private function configureApiDocumentation(): void
    {
        $authDocumentation = new AuthDocumentation;

        Scramble::configure()
            ->withDocumentTransformers(fn (OpenApi $openApi) => $authDocumentation->document($openApi))
            ->withOperationTransformers(fn (Operation $operation, RouteInfo $routeInfo) => $authDocumentation->operation($operation, $routeInfo));
    }

    /**
     * The `brevo` mailer sends through Brevo's HTTP API (port 443), which works on hosts that block SMTP ports.
     */
    private function configureBrevoMailer(): void
    {
        Mail::extend('brevo', fn () => (new BrevoTransportFactory(null, $this->app->make(HttpClientInterface::class)))
            ->create(new Dsn('brevo+api', 'default', (string) config('services.brevo.key'))));
    }

    /**
     * Placing and paying orders get separate per-IP budgets, so paying is never blocked by checkout attempts.
     * Magic link requests are also limited per email, so rotating IPs cannot flood one inbox.
     */
    private function configureRateLimiting(): void
    {
        foreach (['orders.store', 'orders.pay'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => Limit::perMinute(self::ORDER_REQUESTS_PER_MINUTE)->by($request->ip()));
        }

        RateLimiter::for('magic-link.request', fn (Request $request) => [
            Limit::perMinute(self::MAGIC_LINK_REQUESTS_PER_MINUTE)->by('ip|'.$request->ip()),
            Limit::perMinute(self::MAGIC_LINK_REQUESTS_PER_EMAIL_PER_MINUTE)
                ->by('email|'.$this->throttleEmail($request)),
        ]);

        RateLimiter::for('magic-link.verify', fn (Request $request) => Limit::perMinute(self::MAGIC_LINK_VERIFICATIONS_PER_MINUTE)->by($request->ip()));
    }

    /**
     * The submitted email, normalised so case, spacing and accents do not create separate rate-limit buckets.
     */
    private function throttleEmail(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) ? Str::transliterate(Str::lower(trim($email))) : '';
    }
}
