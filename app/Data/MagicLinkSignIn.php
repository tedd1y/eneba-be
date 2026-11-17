<?php

namespace App\Data;

use App\Models\User;

/**
 * The result of signing in with a magic link: who signed in, and where the storefront should go next.
 */
final readonly class MagicLinkSignIn
{
    public function __construct(
        public User $user,
        public string $redirect,
    ) {}
}
