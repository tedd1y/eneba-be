<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email'])]
/**
 * A storefront account. There are no passwords: users sign in with emailed magic links (see MagicLoginToken).
 */
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
     * Magic sign-in links issued to this user.
     *
     * @return HasMany<MagicLoginToken, $this>
     */
    public function magicLoginTokens(): HasMany
    {
        return $this->hasMany(MagicLoginToken::class);
    }
}
