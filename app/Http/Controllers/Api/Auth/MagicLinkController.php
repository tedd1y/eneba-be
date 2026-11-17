<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\LogInWithMagicLink;
use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestMagicLinkRequest;
use App\Http\Requests\Auth\VerifyMagicLinkRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class MagicLinkController extends Controller
{
    public const SENT_MESSAGE = 'We have emailed you a sign-in link.';

    /**
     * Request a sign-in link.
     *
     * Emails a single-use link to `{FRONTEND_URL}/auth/magic?token=…`, valid for 15 minutes. The response is
     * the same for every address; the account is created when the link is first used. Pass `redirect` to have
     * verify send the user back to a storefront path. Requesting a new link invalidates earlier ones.
     * Limited to 5 per minute per IP and 3 per minute per email. Returns 503 when the email could not be sent.
     *
     * @throws ThrottleRequestsException
     * @throws ServiceUnavailableHttpException
     */
    public function store(RequestMagicLinkRequest $request, SendMagicLink $sendMagicLink): JsonResponse
    {
        $sendMagicLink->handle($request->validated('email'), $request->validated('redirect'));

        /** @status 202 */
        return new JsonResponse([
            /** @example We have emailed you a sign-in link. */
            'message' => __(self::SENT_MESSAGE),
        ], 202);
    }

    /**
     * Sign in with a link.
     *
     * Exchanges the token from the emailed link for a session (Sanctum cookie), creating the account on first
     * sign-in, and returns the user plus the `redirect` path requested with the link. With `remember: true` a
     * long-lived "remember me" cookie keeps the user signed in after the session expires. The token works once;
     * following it also verifies the email address. Returns 422 for an unknown, used or expired token.
     * Limited to 10 per minute per IP.
     *
     * @throws ThrottleRequestsException
     */
    public function verify(VerifyMagicLinkRequest $request, LogInWithMagicLink $logInWithMagicLink): JsonResponse
    {
        $signIn = $logInWithMagicLink->handle($request->validated('token'), $request->boolean('remember'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Always 200, also when this first sign-in created the account (a resource would answer 201).
        return (new UserResource($signIn->user))->additional([
            /**
             * Storefront path to open now, as requested with the link (`/` by default).
             *
             * @example /cart
             */
            'redirect' => (string) $signIn->redirect,
        ])->response()->setStatusCode(200);
    }
}
