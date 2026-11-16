<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'icon' => fake()->optional()->randomElement(['star', 'bolt', 'heart', 'globe', 'gamepad']),
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the record is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }
}
