<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed a few guest orders in different states from the existing catalog.
     */
    public function run(): void
    {
        $products = Product::query()->active()->with(['platform', 'region'])->inRandomOrder()->limit(12)->get();

        if ($products->isEmpty()) {
            return;
        }

        $orders = [
            Order::factory()->create(),
            Order::factory()->create(),
            Order::factory()->paid()->create(),
            Order::factory()->paid()->create(),
            Order::factory()->cancelled()->create(),
        ];

        foreach ($orders as $order) {
            $items = $products->random(min(3, $products->count()))
                ->map(fn (Product $product) => OrderItem::factory()->for($order)->forProduct($product)->create());

            $order->update([
                'subtotal' => $items->sum('line_total'),
                'cashback' => $items->sum(fn (OrderItem $item) => $item->unit_cashback * $item->quantity),
                'currency' => $products->first()->currency,
            ]);
        }
    }
}
