<?php

use App\Models\Order;
use App\Models\Product;

/**
 * Create the given number of products, each on its own platform and region, so nothing is shared.
 *
 * @return list<array{product_id: int, quantity: int}>
 */
function uniqueOrderItems(int $count, int $offset = 0): array
{
    return collect(range(1, $count))
        ->map(fn (int $index) => Product::factory()
            ->onPlatform('platform-'.($offset + $index))
            ->inRegion('region-'.($offset + $index))
            ->create())
        ->map(fn (Product $product) => ['product_id' => $product->id, 'quantity' => 1])
        ->all();
}

it('places an order with a constant number of queries, however many items it has', function () {
    $singleItem = uniqueOrderItems(1);

    $baseline = countQueries(fn () => $this->postJson(route('v1.orders.store'), [
        'email' => 'buyer@example.com',
        'items' => $singleItem,
    ])->assertCreated());

    $items = uniqueOrderItems(30, offset: 100);

    $queries = countQueries(fn () => $this->postJson(route('v1.orders.store'), [
        'email' => 'buyer@example.com',
        'items' => $items,
    ])->assertCreated()->assertJsonCount(30, 'data.items'));

    expect($queries)->toBe($baseline)
        ->and($queries)->toBeLessThanOrEqual(8);
});

it('shows an order with a constant number of queries, however many items it has', function () {
    $small = Order::factory()->withItems(1)->create();
    $baseline = countQueries(fn () => $this->getJson(route('v1.orders.show', $small))->assertOk());

    $large = Order::factory()->withItems(25)->create();
    $queries = countQueries(fn () => $this->getJson(route('v1.orders.show', $large))->assertOk()->assertJsonCount(25, 'data.items'));

    expect($queries)->toBe($baseline)
        ->and($queries)->toBeLessThanOrEqual(2);
});

it('pays an order with a constant number of queries, however many items it has', function () {
    $small = Order::factory()->withItems(1)->create();
    $baseline = countQueries(fn () => $this->postJson(route('v1.orders.pay', $small))->assertOk());

    $large = Order::factory()->withItems(25)->create();
    $queries = countQueries(fn () => $this->postJson(route('v1.orders.pay', $large))->assertOk()->assertJsonCount(25, 'data.items'));

    expect($queries)->toBe($baseline)
        ->and($queries)->toBeLessThanOrEqual(4);
});
