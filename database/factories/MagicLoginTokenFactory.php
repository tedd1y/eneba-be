<?php

namespace Database\Factories;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagicLoginToken>
 */
class MagicLoginTokenFactory extends Factory
{
    /**
     * Define the model's default state. Use forToken() to know the plain token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => MagicLoginToken::hashToken(MagicLoginToken::generateToken()),
            'expires_at' => now()->addMinutes(MagicLoginToken::LIFETIME_MINUTES),
            'used_at' => null,
        ];
    }

    /**
     * Store the hash of the given plain token.
     */
    public function forToken(string $token): static
    {
        return $this->state(fn (array $attributes) => ['token_hash' => MagicLoginToken::hashToken($token)]);
    }

    /**
     * Indicate that the link has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    /**
     * Indicate that the link has already been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()->subMinute()]);
    }
}
