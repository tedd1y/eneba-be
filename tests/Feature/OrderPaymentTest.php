<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

it('pays a pending order', function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $order = Order::factory()->withItems(2)->create();

    $this->postJson(route('v1.orders.pay', $order))
        ->assertOk()
        ->assertJsonPath('data.uuid', $order->uuid)
        ->assertJsonPath('data.number', $order->number)
        ->assertJsonPath('data.status', ['value' => 'paid', 'label' => 'Paid'])
        ->assertJsonPath('data.paid_at', '2026-11-16T12:00:00+00:00')
        ->assertJsonCount(2, 'data.items');

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->paid_at->toEqual(Carbon::parse('2026-11-16 12:00:00'));
});

it('pays an order whose previous payment failed', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Failed]);

    $this->postJson(route('v1.orders.pay', $order))
        ->assertOk()
        ->assertJsonPath('data.status.value', 'paid');
});

it('is idempotent for orders that are already paid', function (string $status) {
    $paidAt = Carbon::parse('2026-11-15 08:30:00');
    $order = Order::factory()->create(['status' => $status, 'paid_at' => $paidAt]);

    Carbon::setTestNow('2026-11-16 12:00:00');

    $this->postJson(route('v1.orders.pay', $order))
        ->assertOk()
        ->assertJsonPath('data.status.value', $status)
        ->assertJsonPath('data.paid_at', $paidAt->toIso8601String());

    expect($order->fresh())
        ->status->value->toBe($status)
        ->paid_at->toEqual($paidAt);
})->with(['paid', 'completed']);

it('can be retried without changing a freshly paid order', function () {
    $order = Order::factory()->create();

    $first = $this->postJson(route('v1.orders.pay', $order))->assertOk()->json('data');

    Carbon::setTestNow(now()->addMinutes(5));

    $second = $this->postJson(route('v1.orders.pay', $order))->assertOk()->json('data');

    expect($second)->toBe($first);
});

it('returns 409 when the order cannot be paid', function () {
    $order = Order::factory()->cancelled()->create();

    $this->postJson(route('v1.orders.pay', $order))
        ->assertConflict()
        ->assertJsonPath('message', 'Orders that are cancelled cannot be paid.');

    expect($order->fresh())
        ->status->toBe(OrderStatus::Cancelled)
        ->paid_at->toBeNull();
});

it('returns 404 when paying an unknown order', function () {
    $this->postJson('/api/v1/orders/'.Str::uuid7().'/pay')->assertNotFound();
});

it('cannot be paid by its order number', function () {
    $order = Order::factory()->create();

    $this->postJson("/api/v1/orders/{$order->number}/pay")->assertNotFound();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('throttles payments to 10 requests per minute', function () {
    RateLimiter::clear('orders.pay');
    $order = Order::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('v1.orders.pay', $order))->assertOk();
    }

    $this->postJson(route('v1.orders.pay', $order))->assertTooManyRequests();
});

it('throttles payments separately from order creation', function () {
    $order = Order::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('v1.orders.store'), ['email' => 'buyer@example.com'])->assertUnprocessable();
    }

    $this->postJson(route('v1.orders.store'), ['email' => 'buyer@example.com'])->assertTooManyRequests();
    $this->postJson(route('v1.orders.pay', $order))->assertOk();
});
