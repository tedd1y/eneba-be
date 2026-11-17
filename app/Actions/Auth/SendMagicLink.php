<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLoginToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendMagicLink
{
    public const SEND_FAILED_MESSAGE = 'We could not send the sign-in email. Please try again.';

    /**
     * Email a single-use sign-in link to the address. No account is created yet: that happens when the link is
     * used, so nobody gets an account for an address they don't control.
     *
     * Once the new link is sent, earlier unused links for the same address stop working; if sending fails, they
     * keep working. Every address is treated the same, so the response never reveals whether an account exists.
     *
     * @param  string|null  $redirect  Storefront path to open after signing in, e.g. `/cart`.
     *
     * @throws ServiceUnavailableHttpException when the email provider cannot send the email.
     */
    public function handle(string $email, ?string $redirect = null): void
    {
        $email = Str::lower(trim($email));
        $token = MagicLoginToken::generateToken();

        $magicLoginToken = MagicLoginToken::query()->create([
            'email' => $email,
            'token_hash' => MagicLoginToken::hashToken($token),
            'redirect' => $redirect,
            'expires_at' => now()->addMinutes(MagicLoginToken::LIFETIME_MINUTES),
        ]);

        try {
            Mail::to($email)->send(new MagicLinkMail($this->linkFor($token)));
        } catch (TransportExceptionInterface $exception) {
            // The link never reached the user, so it should not stay usable; earlier links keep working.
            $magicLoginToken->delete();
            report($exception);

            throw new ServiceUnavailableHttpException(message: self::SEND_FAILED_MESSAGE, previous: $exception);
        }

        // Only now that the new link is on its way, retire the earlier unused ones.
        MagicLoginToken::query()
            ->forEmail($email)
            ->whereNull('used_at')
            ->whereKeyNot($magicLoginToken->getKey())
            ->delete();
    }

    /**
     * The storefront page that posts the token to `POST /api/auth/magic-link/verify`.
     */
    private function linkFor(string $token): string
    {
        return config('app.frontend_url').'/auth/magic?'.http_build_query(['token' => $token]);
    }
}
