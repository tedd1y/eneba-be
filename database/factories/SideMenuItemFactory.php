<?php

namespace Database\Factories;

use App\Models\SideMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SideMenuItem>
 */
class SideMenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::title(fake()->unique()->words(2, true));

        return [
            'label' => $label,
            'href' => '/store/all?search='.Str::slug($label),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the item is hidden from the menu.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
