<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLoginToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendMagicLink
{
    /**
     * Email a single-use sign-in link to the address. No account is created yet: that happens when the link is
     * used, so nobody gets an account for an address they don't control.
     *
     * Requesting a new link invalidates earlier unused links for the same address. Every address is treated the
     * same, so the response never reveals whether an account exists.
     *
     * @param  string|null  $redirect  Storefront path to open after signing in, e.g. `/cart`.
     */
    public function handle(string $email, ?string $redirect = null): void
    {
        $email = Str::lower(trim($email));
        $token = MagicLoginToken::generateToken();

        DB::transaction(function () use ($email, $token, $redirect) {
            MagicLoginToken::query()->forEmail($email)->whereNull('used_at')->delete();

            MagicLoginToken::query()->create([
                'email' => $email,
                'token_hash' => MagicLoginToken::hashToken($token),
                'redirect' => $redirect,
                'expires_at' => now()->addMinutes(MagicLoginToken::LIFETIME_MINUTES),
            ]);
        });

        Mail::to($email)->send(new MagicLinkMail($this->linkFor($token)));
    }

    /**
     * The storefront page that posts the token to `POST /api/auth/magic-link/verify`.
     */
    private function linkFor(string $token): string
    {
        return config('app.frontend_url').'/auth/magic?'.http_build_query(['token' => $token]);
    }
}
