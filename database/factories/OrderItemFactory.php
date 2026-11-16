<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'title' => fake()->randomElement(['Elden Ring', 'Cyberpunk 2077', 'Hades II', 'Stardew Valley', 'Steam Gift Card']),
            'platform_label' => fake()->randomElement(['Steam', 'GOG', 'Xbox', 'PlayStation']),
            'region_label' => fake()->randomElement(['Global', 'Europe', 'North America']),
            'unit_price' => fake()->numberBetween(499, 6999),
            'unit_cashback' => fn (array $attributes) => (int) round($attributes['unit_price'] * 0.05),
            'quantity' => fake()->numberBetween(1, 3),
            'line_total' => fn (array $attributes) => $attributes['unit_price'] * $attributes['quantity'],
        ];
    }

    /**
     * Snapshot the given product's current details, as checkout does.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'title' => $product->title,
            'platform_label' => $product->platform->name,
            'region_label' => $product->region->name,
            'unit_price' => $product->price,
            'unit_cashback' => $product->cashback ?? 0,
        ]);
    }
}
