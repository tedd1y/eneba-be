<?php

namespace App\Http\Documentation;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * OpenAPI documentation for authentication: the Sanctum session scheme, which operations need a signed-in
 * user, and the CSRF header that browser requests must send.
 */
class AuthDocumentation
{
    public const TAG = 'Auth';

    public const SESSION_SCHEME = 'sanctumSession';

    /**
     * Register the session security scheme (document transformer).
     */
    public function document(OpenApi $openApi): void
    {
        $openApi->components->addSecurityScheme(self::SESSION_SCHEME, SecurityScheme::apiKey('cookie', 'laravel-session')
            ->setDescription('Session cookie set by `POST /auth/magic-link/verify` (Sanctum SPA authentication). Call `GET /sanctum/csrf-cookie` first, send requests with credentials, and send the `XSRF-TOKEN` cookie value back in the `X-XSRF-TOKEN` header on every POST, PUT, PATCH and DELETE.'));
    }

    /**
     * Mark operations that need a signed-in user, and group the authentication endpoints (operation transformer).
     */
    public function operation(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;
        $requiresAuthentication = collect($route->gatherMiddleware())
            ->contains(fn (string $middleware) => Str::is(['auth', 'auth:*'], $middleware));

        if ($requiresAuthentication) {
            $operation->security = [new SecurityRequirement([self::SESSION_SCHEME => []])];
        }

        if (Str::startsWith((string) $route->getName(), 'auth.') || $route->getName() === 'v1.me.show') {
            $operation->setTags([self::TAG]);
        }
    }
}
