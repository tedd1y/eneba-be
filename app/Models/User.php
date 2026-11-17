<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A storefront account. There are no passwords: users sign in with emailed magic links (see MagicLoginToken).
 * The remember token backs the optional "remember me" cookie.
 */
#[Fillable(['name', 'email'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Users have no password. Laravel ties "remember me" cookies to the password hash and ignores a cookie when
     * this is not a string, so an empty string keeps remember-me working.
     */
    public function getAuthPassword(): string
    {
        return '';
    }
}
