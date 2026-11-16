<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Build an order payload for the given products, one key each unless quantities are given.
 *
 * @param  list<Product>  $products
 * @param  list<int>  $quantities
 * @return array{email: string, items: list<array{product_id: int, quantity: int}>}
 */
function orderPayload(array $products, array $quantities = [], string $email = 'buyer@example.com'): array
{
    return [
        'email' => $email,
        'items' => collect($products)
            ->values()
            ->map(fn (Product $product, int $index) => ['product_id' => $product->id, 'quantity' => $quantities[$index] ?? 1])
            ->all(),
    ];
}

it('creates a pending order with snapshots priced from the catalog', function () {
    $eldenRing = Product::factory()->onPlatform('steam')->inRegion('global')->create([
        'title' => 'Elden Ring',
        'price' => 4299,
        'cashback' => 215,
    ]);
    $giftCard = Product::factory()->onPlatform('xbox')->inRegion('europe')->create([
        'title' => 'Xbox Gift Card',
        'price' => 2500,
        'cashback' => null,
    ]);

    $response = $this->postJson(route('v1.orders.store'), orderPayload([$eldenRing, $giftCard], [2, 1]))
        ->assertCreated()
        ->assertJsonPath('data.email', 'buyer@example.com')
        ->assertJsonPath('data.status', ['value' => 'pending', 'label' => 'Pending'])
        ->assertJsonPath('data.subtotal', ['amount' => 11098, 'formatted' => '€110.98'])
        ->assertJsonPath('data.cashback', ['amount' => 430, 'formatted' => '€4.30'])
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.paid_at', null)
        ->assertJsonPath('data.items', [
            [
                'product_id' => $eldenRing->id,
                'title' => 'Elden Ring',
                'platform' => 'Steam',
                'region' => 'Global',
                'unit_price' => ['amount' => 4299, 'formatted' => '€42.99'],
                'unit_cashback' => ['amount' => 215, 'formatted' => '€2.15'],
                'quantity' => 2,
                'line_total' => ['amount' => 8598, 'formatted' => '€85.98'],
            ],
            [
                'product_id' => $giftCard->id,
                'title' => 'Xbox Gift Card',
                'platform' => 'Xbox',
                'region' => 'Europe',
                'unit_price' => ['amount' => 2500, 'formatted' => '€25.00'],
                'unit_cashback' => ['amount' => 0, 'formatted' => '€0.00'],
                'quantity' => 1,
                'line_total' => ['amount' => 2500, 'formatted' => '€25.00'],
            ],
        ]);

    $order = Order::query()->sole();

    expect($response->json('data.uuid'))->toBe($order->uuid)
        ->and(Str::isUuid($order->uuid, 7))->toBeTrue()
        ->and($response->json('data.number'))->toBe($order->number)
        ->and($order->number)->toMatch('/^O-[2-9A-HJ-NP-Z]{8}$/')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->subtotal)->toBe(11098)
        ->and($order->cashback)->toBe(430)
        ->and($order->items()->count())->toBe(2);
});

it('never exposes internal order or item IDs', function () {
    $product = Product::factory()->create();

    $data = $this->postJson(route('v1.orders.store'), orderPayload([$product]))->assertCreated()->json('data');

    expect($data)->not->toHaveKey('id')
        ->and($data['items'][0])->not->toHaveKeys(['id', 'order_id']);
});

it('ignores prices and other fields sent by the client', function () {
    $product = Product::factory()->create(['price' => 4299, 'cashback' => 100]);

    $payload = orderPayload([$product]);
    $payload['items'][0] += ['unit_price' => 1, 'price' => 1, 'line_total' => 1, 'unit_cashback' => 99999];
    $payload += ['subtotal' => 1, 'cashback' => 99999, 'status' => 'paid', 'currency' => 'USD', 'number' => 'O-HACKED00', 'uuid' => '00000000-0000-7000-8000-000000000000'];

    $this->postJson(route('v1.orders.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.subtotal.amount', 4299)
        ->assertJsonPath('data.cashback.amount', 100)
        ->assertJsonPath('data.status.value', 'pending')
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.items.0.unit_price.amount', 4299)
        ->assertJsonPath('data.number', fn (string $number) => $number !== 'O-HACKED00')
        ->assertJsonPath('data.uuid', fn (string $uuid) => $uuid !== '00000000-0000-7000-8000-000000000000');
});

it('keeps the snapshot when the product changes or is deleted later', function () {
    $product = Product::factory()->onPlatform('steam')->create(['title' => 'Elden Ring', 'price' => 4299]);

    $uuid = $this->postJson(route('v1.orders.store'), orderPayload([$product]))->assertCreated()->json('data.uuid');

    $product->update(['title' => 'Elden Ring (Renamed)', 'price' => 9999]);

    $this->getJson(route('v1.orders.show', $uuid))
        ->assertJsonPath('data.items.0.title', 'Elden Ring')
        ->assertJsonPath('data.items.0.unit_price.amount', 4299);

    $product->delete();

    $this->getJson(route('v1.orders.show', $uuid))
        ->assertOk()
        ->assertJsonPath('data.items.0.product_id', null)
        ->assertJsonPath('data.items.0.title', 'Elden Ring')
        ->assertJsonPath('data.subtotal.amount', 4299);
});

it('accepts the maximum of 40 items and 40 keys', function () {
    $products = Product::factory()->count(40)->create()->all();

    $this->postJson(route('v1.orders.store'), orderPayload($products))
        ->assertCreated()
        ->assertJsonCount(40, 'data.items');
});

it('accepts up to 10 of one product', function () {
    $product = Product::factory()->create(['price' => 1000]);

    $this->postJson(route('v1.orders.store'), orderPayload([$product], [10]))
        ->assertCreated()
        ->assertJsonPath('data.items.0.quantity', 10)
        ->assertJsonPath('data.subtotal.amount', 10000);
});

it('rejects more than 40 keys in total', function () {
    $products = Product::factory()->count(5)->create()->all();

    $this->postJson(route('v1.orders.store'), orderPayload($products, [10, 10, 10, 10, 1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items' => 'at most 40 keys']);

    expect(Order::query()->count())->toBe(0);
});

it('rejects more than 40 items', function () {
    $products = Product::factory()->count(41)->create()->all();

    $this->postJson(route('v1.orders.store'), orderPayload($products))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');
});

it('rejects inactive and unknown products', function () {
    $active = Product::factory()->create();
    $inactive = Product::factory()->inactive()->create();

    $this->postJson(route('v1.orders.store'), [
        'email' => 'buyer@example.com',
        'items' => [
            ['product_id' => $active->id, 'quantity' => 1],
            ['product_id' => $inactive->id, 'quantity' => 1],
            ['product_id' => 999999, 'quantity' => 1],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.1.product_id', 'items.2.product_id'])
        ->assertJsonMissingValidationErrors('items.0.product_id');

    expect(Order::query()->count())->toBe(0);
});

it('rejects products priced in different currencies', function () {
    $euro = Product::factory()->create(['currency' => 'EUR']);
    $dollar = Product::factory()->create(['currency' => 'USD']);

    $this->postJson(route('v1.orders.store'), orderPayload([$euro, $dollar]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items' => 'same currency']);

    expect(Order::query()->count())->toBe(0);
});

it('uses the currency the products are priced in', function () {
    $product = Product::factory()->create(['currency' => 'USD', 'price' => 1999]);

    $this->postJson(route('v1.orders.store'), orderPayload([$product]))
        ->assertCreated()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.subtotal.formatted', '$19.99');
});

it('rejects invalid order input', function (Closure $payload, string|array $errors) {
    $product = Product::factory()->create();

    $this->postJson(route('v1.orders.store'), $payload($product))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing email' => [fn (Product $p) => ['items' => [['product_id' => $p->id, 'quantity' => 1]]], 'email'],
    'invalid email' => [fn (Product $p) => ['email' => 'not-an-email', 'items' => [['product_id' => $p->id, 'quantity' => 1]]], 'email'],
    'missing items' => [fn (Product $p) => ['email' => 'buyer@example.com'], 'items'],
    'empty items' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => []], 'items'],
    'items not a list' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => 'lots'], 'items'],
    'missing product id' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['quantity' => 1]]], 'items.0.product_id'],
    'non-integer product id' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => 'abc', 'quantity' => 1]]], 'items.0.product_id'],
    'duplicate product' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $p->id, 'quantity' => 2]]], ['items.0.product_id', 'items.1.product_id']],
    'missing quantity' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => $p->id]]], 'items.0.quantity'],
    'zero quantity' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => $p->id, 'quantity' => 0]]], 'items.0.quantity'],
    'quantity over 10' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => $p->id, 'quantity' => 11]]], 'items.0.quantity'],
    'non-integer quantity' => [fn (Product $p) => ['email' => 'buyer@example.com', 'items' => [['product_id' => $p->id, 'quantity' => 1.5]]], 'items.0.quantity'],
]);

it('generates a different number and UUID for every order', function () {
    $product = Product::factory()->create();

    $orders = collect(range(1, 5))
        ->map(fn () => $this->postJson(route('v1.orders.store'), orderPayload([$product]))->assertCreated()->json('data'));

    expect($orders->pluck('number')->unique())->toHaveCount(5)
        ->and($orders->pluck('uuid')->unique())->toHaveCount(5);
});

it('throttles order creation to 10 requests per minute', function () {
    RateLimiter::clear('orders.store');
    $payload = orderPayload([Product::factory()->create()]);

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('v1.orders.store'), $payload)->assertCreated();
    }

    $this->postJson(route('v1.orders.store'), $payload)->assertTooManyRequests();

    expect(Order::query()->count())->toBe(10);
});
