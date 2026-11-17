<?php

namespace App\Actions\Auth;

use App\Data\MagicLinkSignIn;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogInWithMagicLink
{
    /**
     * Sign in with a token from an emailed link, creating the account on first sign-in. The token is used up,
     * and following the link proves the user owns the email address, so it is marked verified.
     *
     * @param  bool  $remember  Also set the long-lived "remember me" cookie.
     *
     * @throws ValidationException when the token is unknown, already used or expired.
     */
    public function handle(string $token, bool $remember = false): MagicLinkSignIn
    {
        $signIn = DB::transaction(function () use ($token) {
            $magicLoginToken = MagicLoginToken::query()->forToken($token)->usable()->lockForUpdate()->first();

            if ($magicLoginToken === null) {
                throw ValidationException::withMessages(['token' => __('This sign-in link is invalid or has expired.')]);
            }

            $magicLoginToken->update(['used_at' => now()]);

            $user = User::query()->createOrFirst(
                ['email' => $magicLoginToken->email],
                ['name' => Str::before($magicLoginToken->email, '@')],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return new MagicLinkSignIn($user, $magicLoginToken->redirect ?? MagicLoginToken::DEFAULT_REDIRECT);
        });

        Auth::guard('web')->login($signIn->user, $remember);

        return $signIn;
    }
}
