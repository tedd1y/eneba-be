<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendMagicLink
{
    /**
     * Email a single-use sign-in link to the address, creating the account if it is new.
     *
     * Requesting a new link invalidates any earlier unused links for the same user. Every address gets the same
     * treatment, so the response never reveals whether an account already existed.
     */
    public function handle(string $email): void
    {
        $email = Str::lower(trim($email));
        $token = MagicLoginToken::generateToken();

        $user = DB::transaction(function () use ($email, $token) {
            $user = User::query()->createOrFirst(['email' => $email], ['name' => Str::before($email, '@')]);

            $user->magicLoginTokens()->whereNull('used_at')->delete();

            $user->magicLoginTokens()->create([
                'token_hash' => MagicLoginToken::hashToken($token),
                'expires_at' => now()->addMinutes(MagicLoginToken::LIFETIME_MINUTES),
            ]);

            return $user;
        });

        Mail::to($user->email)->send(new MagicLinkMail($this->linkFor($token)));
    }

    /**
     * The storefront page that posts the token to `POST /api/auth/magic-link/verify`.
     */
    private function linkFor(string $token): string
    {
        return config('app.frontend_url').'/auth/magic?'.http_build_query(['token' => $token]);
    }
}
