<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `guest` middleware (used by login, register and password reset): API and JSON requests from a
 * signed-in user get a JSON answer instead of a redirect; anything else is redirected as usual.
 */
class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    public const MESSAGE = 'You are already logged in.';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $request->expectsJson() || $request->is('api/*')
                    ? new JsonResponse(['message' => __(self::MESSAGE)])
                    : redirect($this->redirectTo($request));
            }
        }

        return $next($request);
    }
}
