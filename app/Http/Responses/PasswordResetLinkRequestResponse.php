<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * The same response whether or not a reset link was sent, so the endpoint does not reveal which email
 * addresses have accounts (or are being throttled by the password broker).
 */
class PasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'If an account exists for that email address, we have sent a password reset link.';

    /**
     * @param  string|null  $status  The password broker status; deliberately not exposed.
     */
    public function __construct(public ?string $status = null) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => __(self::MESSAGE)]);
    }
}
