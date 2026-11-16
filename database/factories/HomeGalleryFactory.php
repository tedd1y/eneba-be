<?php

namespace Database\Factories;

use App\Models\HomeGallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HomeGallery>
 */
class HomeGalleryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'label' => $label,
            'href' => '/store/all?search='.Str::slug($label),
            'limit' => HomeGallery::DEFAULT_LIMIT,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the gallery is hidden from the home page.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
