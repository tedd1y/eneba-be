<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => Order::generateNumber(),
            'email' => fake()->unique()->safeEmail(),
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'cashback' => 0,
            'currency' => Product::DEFAULT_CURRENCY,
            'paid_at' => null,
        ];
    }

    /**
     * Indicate that the order has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the order has been cancelled before payment.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
        ]);
    }

    /**
     * Add the given number of line items and set the order totals from them.
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $items = OrderItem::factory()->count($count)->for($order)->create();

            $order->update([
                'subtotal' => $items->sum('line_total'),
                'cashback' => $items->sum(fn (OrderItem $item) => $item->unit_cashback * $item->quantity),
            ]);
        });
    }
}
