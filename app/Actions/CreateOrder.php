<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    /**
     * Create a pending guest order. Prices, cashback and product details are read from the catalog, never from the request.
     *
     * @param  list<array{product_id: int|string, quantity: int|string}>  $items
     *
     * @throws ValidationException when a product is no longer available or the products are priced in different currencies.
     */
    public function handle(string $email, array $items): Order
    {
        $quantities = collect($items)->mapWithKeys(fn (array $item) => [(int) $item['product_id'] => (int) $item['quantity']]);
        $products = $this->loadProducts($quantities->keys()->all());

        $currencies = $products->pluck('currency')->unique();

        if ($currencies->count() > 1) {
            throw ValidationException::withMessages(['items' => 'All products in an order must be priced in the same currency.']);
        }

        $lines = $quantities->map(function (int $quantity, int $productId) use ($products) {
            $product = $products->get($productId);

            return [
                'product_id' => $product->id,
                'title' => $product->title,
                'platform_label' => $product->platform->name,
                'region_label' => $product->region->name,
                'unit_price' => $product->price,
                'unit_cashback' => $product->cashback ?? 0,
                'quantity' => $quantity,
                'line_total' => $product->price * $quantity,
            ];
        })->values();

        return DB::transaction(function () use ($email, $lines, $currencies) {
            $order = Order::query()->create([
                'number' => $this->uniqueNumber(),
                'email' => $email,
                'subtotal' => $lines->sum('line_total'),
                'cashback' => $lines->sum(fn (array $line) => $line['unit_cashback'] * $line['quantity']),
                'currency' => $currencies->first(),
            ]);

            $timestamp = $order->freshTimestamp();

            OrderItem::query()->insert($lines->map(fn (array $line) => [
                ...$line,
                'order_id' => $order->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->all());

            return $order->load('items');
        });
    }

    /**
     * Load the active products being bought, keyed by ID, failing if any has become unavailable since validation.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     *
     * @throws ValidationException
     */
    private function loadProducts(array $productIds): Collection
    {
        $products = Product::query()
            ->active()
            ->with(['platform', 'region'])
            ->whereKey($productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'Some products are no longer available.']);
        }

        return $products;
    }

    /**
     * Generate an order number that is not taken yet.
     */
    private function uniqueNumber(): string
    {
        do {
            $number = Order::generateNumber();
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
