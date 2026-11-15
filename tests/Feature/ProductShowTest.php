<?php

use App\Enums\Platform;
use App\Enums\ProductType;
use App\Enums\Region;
use App\Models\Product;

it('returns a product by slug', function () {
    $product = Product::factory()->create([
        'title' => 'Baldur\'s Gate 3',
        'slug' => 'baldurs-gate-3-steam-global',
        'platform' => Platform::Steam,
        'region' => Region::Global,
        'type' => ProductType::Game,
        'price' => 4299,
        'original_price' => 5999,
        'cashback' => 215,
        'currency' => 'EUR',
    ]);

    $this->getJson(route('v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.title', 'Baldur\'s Gate 3')
        ->assertJsonPath('data.slug', 'baldurs-gate-3-steam-global')
        ->assertJsonPath('data.platform', ['value' => 'steam', 'label' => 'Steam'])
        ->assertJsonPath('data.region', ['value' => 'global', 'label' => 'Global'])
        ->assertJsonPath('data.type', ['value' => 'game', 'label' => 'Game'])
        ->assertJsonPath('data.price', ['amount' => 4299, 'formatted' => '€42.99'])
        ->assertJsonPath('data.original_price', ['amount' => 5999, 'formatted' => '€59.99'])
        ->assertJsonPath('data.cashback', ['amount' => 215, 'formatted' => '€2.15'])
        ->assertJsonPath('data.discount_percent', 28)
        ->assertJsonPath('data.currency', 'EUR');

    expect(route('v1.products.show', $product))->toEndWith('/api/v1/products/baldurs-gate-3-steam-global');
});

it('returns null prices and zero discount when there is no original price or cashback', function () {
    $product = Product::factory()->create([
        'price' => 1999,
        'original_price' => null,
        'cashback' => null,
    ]);

    $this->getJson(route('v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.original_price', null)
        ->assertJsonPath('data.cashback', null)
        ->assertJsonPath('data.discount_percent', 0);
});

it('returns 404 for an unknown slug', function () {
    $this->getJson('/api/v1/products/does-not-exist')
        ->assertNotFound();
});

it('returns 404 for an inactive product', function () {
    $product = Product::factory()->inactive()->create();

    $this->getJson(route('v1.products.show', $product))
        ->assertNotFound();
});
