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

class MagicLinkController extends Controller
{
    public const SENT_MESSAGE = 'We have emailed you a sign-in link.';

    /**
     * Request a sign-in link.
     *
     * Emails a single-use link to `{FRONTEND_URL}/auth/magic?token=…`, valid for 15 minutes. An account is
     * created for an unknown address, so the response is always the same. Requesting a new link invalidates
     * earlier ones. Limited to 5 per minute per IP and 3 per minute per email.
     *
     * @throws ThrottleRequestsException
     */
    public function store(RequestMagicLinkRequest $request, SendMagicLink $sendMagicLink): JsonResponse
    {
        $sendMagicLink->handle($request->validated('email'));

        /** @status 202 */
        return new JsonResponse([
            /** @example We have emailed you a sign-in link. */
            'message' => __(self::SENT_MESSAGE),
        ], 202);
    }

    /**
     * Sign in with a link.
     *
     * Exchanges the token from the emailed link for a session (Sanctum cookie) and returns the user. The token
     * works once; following it also verifies the email address. Returns 422 for an unknown, used or expired
     * token. Limited to 10 per minute per IP.
     *
     * @throws ThrottleRequestsException
     */
    public function verify(VerifyMagicLinkRequest $request, LogInWithMagicLink $logInWithMagicLink): UserResource
    {
        $user = $logInWithMagicLink->handle($request->validated('token'));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return new UserResource($user);
    }
}
