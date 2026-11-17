<?php

namespace App\Actions\Auth;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogInWithMagicLink
{
    /**
     * Sign the user in with a token from their emailed link. The token is used up, and following the link proves
     * the user owns the email address, so it is marked verified.
     *
     * @throws ValidationException when the token is unknown, already used or expired.
     */
    public function handle(string $token): User
    {
        $user = DB::transaction(function () use ($token) {
            $magicLoginToken = MagicLoginToken::query()->forToken($token)->usable()->lockForUpdate()->first();

            if ($magicLoginToken === null) {
                throw ValidationException::withMessages(['token' => __('This sign-in link is invalid or has expired.')]);
            }

            $magicLoginToken->update(['used_at' => now()]);

            $user = $magicLoginToken->user()->sole();

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });

        Auth::guard('web')->login($user);

        return $user;
    }
}
