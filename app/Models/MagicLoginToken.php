<?php

namespace App\Models;

use Database\Factories\MagicLoginTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use, short-lived sign-in link. Only the SHA-256 hash of the token is stored.
 */
#[Fillable(['user_id', 'token_hash', 'expires_at', 'used_at'])]
class MagicLoginToken extends Model
{
    /** @use HasFactory<MagicLoginTokenFactory> */
    use HasFactory;

    /**
     * Minutes a sign-in link stays valid.
     */
    public const LIFETIME_MINUTES = 15;

    /**
     * Length of the plain token sent in the link.
     */
    public const TOKEN_LENGTH = 64;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Generate a new plain token for a sign-in link.
     */
    public static function generateToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    /**
     * The value stored for a plain token.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * User the link signs in.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to tokens that can still be used: not used yet and not expired.
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('used_at'))
            ->where($this->qualifyColumn('expires_at'), '>', now());
    }

    /**
     * Scope a query to the token matching the given plain token.
     */
    #[Scope]
    protected function forToken(Builder $query, string $token): void
    {
        $query->where($this->qualifyColumn('token_hash'), self::hashToken($token));
    }
}
