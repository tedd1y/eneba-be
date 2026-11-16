<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

it('returns an order by its UUID', function () {
    $order = Order::factory()->paid()->create([
        'number' => 'O-7KD2XQ9M',
        'email' => 'buyer@example.com',
        'subtotal' => 9097,
        'cashback' => 215,
    ]);
    OrderItem::factory()->for($order)->create([
        'title' => 'Elden Ring',
        'platform_label' => 'Steam',
        'region_label' => 'Global',
        'unit_price' => 4299,
        'unit_cashback' => 215,
        'quantity' => 1,
        'line_total' => 4299,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => null,
        'title' => 'Hades II',
        'unit_price' => 2399,
        'unit_cashback' => 0,
        'quantity' => 2,
        'line_total' => 4798,
    ]);

    $this->getJson(route('v1.orders.show', $order))
        ->assertOk()
        ->assertJsonPath('data.uuid', $order->uuid)
        ->assertJsonPath('data.number', 'O-7KD2XQ9M')
        ->assertJsonPath('data.email', 'buyer@example.com')
        ->assertJsonPath('data.status', ['value' => 'paid', 'label' => 'Paid'])
        ->assertJsonPath('data.subtotal', ['amount' => 9097, 'formatted' => '€90.97'])
        ->assertJsonPath('data.cashback', ['amount' => 215, 'formatted' => '€2.15'])
        ->assertJsonPath('data.paid_at', $order->paid_at->toIso8601String())
        ->assertJsonPath('data.items.0.title', 'Elden Ring')
        ->assertJsonPath('data.items.0.platform', 'Steam')
        ->assertJsonPath('data.items.0.region', 'Global')
        ->assertJsonPath('data.items.1.product_id', null)
        ->assertJsonPath('data.items.1.quantity', 2)
        ->assertJsonPath('data.items.1.line_total', ['amount' => 4798, 'formatted' => '€47.98']);

    expect(route('v1.orders.show', $order))->toEndWith("/api/v1/orders/{$order->uuid}");
});

it('generates a time-ordered UUID for every order', function () {
    $first = Order::factory()->create();
    $second = Order::factory()->create();

    expect(Str::isUuid($first->uuid, 7))->toBeTrue()
        ->and(Str::isUuid($second->uuid, 7))->toBeTrue()
        ->and($first->uuid)->not->toBe($second->uuid)
        ->and(strcmp($first->uuid, $second->uuid))->toBeLessThan(0)
        ->and($first->getKey())->toBeInt();
});

it('cannot be looked up by its order number', function () {
    $order = Order::factory()->create(['number' => 'O-7KD2XQ9M']);

    $this->getJson('/api/v1/orders/O-7KD2XQ9M')->assertNotFound();
    $this->getJson("/api/v1/orders/{$order->uuid}")->assertOk();
});

it('shows the status label for every status', function (string $state, array $status) {
    $order = Order::factory()->{$state}()->create();

    $this->getJson(route('v1.orders.show', $order))->assertJsonPath('data.status', $status);
})->with([
    'pending' => ['withItems', ['value' => 'pending', 'label' => 'Pending']],
    'paid' => ['paid', ['value' => 'paid', 'label' => 'Paid']],
    'cancelled' => ['cancelled', ['value' => 'cancelled', 'label' => 'Cancelled']],
]);

it('does not expose order or item IDs', function () {
    $order = Order::factory()->withItems(2)->create();

    $data = $this->getJson(route('v1.orders.show', $order))->assertOk()->json('data');

    expect($data)->not->toHaveKey('id')
        ->and(collect($data['items'])->every(fn (array $item) => ! array_key_exists('id', $item) && ! array_key_exists('order_id', $item)))->toBeTrue();
});

it('cannot be looked up by its database ID', function () {
    $order = Order::factory()->create();

    $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
});

it('returns 404 for an unknown UUID', function () {
    Order::factory()->create();

    $this->getJson('/api/v1/orders/'.Str::uuid7())->assertNotFound();
});
