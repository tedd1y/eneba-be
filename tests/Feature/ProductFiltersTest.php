<?php

use App\Enums\Platform;
use App\Enums\ProductType;
use App\Enums\Region;
use App\Models\Product;

it('returns every filter option with counts of active products', function () {
    Product::factory()->count(2)->create([
        'platform' => Platform::Steam,
        'region' => Region::Europe,
        'type' => ProductType::Game,
    ]);
    Product::factory()->create([
        'platform' => Platform::Xbox,
        'region' => Region::Global,
        'type' => ProductType::GiftCard,
    ]);
    Product::factory()->inactive()->create([
        'platform' => Platform::Gog,
        'region' => Region::Asia,
        'type' => ProductType::Dlc,
    ]);

    $response = $this->getJson(route('v1.products.filters.index'))->assertOk();

    expect($response->json('data.platforms'))->toHaveCount(count(Platform::cases()))
        ->and($response->json('data.regions'))->toHaveCount(count(Region::cases()))
        ->and($response->json('data.types'))->toHaveCount(count(ProductType::cases()));

    $counts = fn (string $group) => collect($response->json("data.{$group}"))->pluck('count', 'value')->all();

    expect($counts('platforms'))->toMatchArray(['steam' => 2, 'xbox' => 1, 'gog' => 0, 'nintendo' => 0])
        ->and($counts('regions'))->toMatchArray(['europe' => 2, 'global' => 1, 'asia' => 0])
        ->and($counts('types'))->toMatchArray(['game' => 2, 'gift_card' => 1, 'dlc' => 0]);
});

it('returns options in enum order with labels', function () {
    $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonPath('data.platforms.0', ['value' => 'steam', 'label' => 'Steam', 'count' => 0])
        ->assertJsonPath('data.regions.0', ['value' => 'global', 'label' => 'Global', 'count' => 0])
        ->assertJsonPath('data.types.1', ['value' => 'dlc', 'label' => 'DLC', 'count' => 0]);
});

it('returns the price range of active products', function () {
    Product::factory()->create(['price' => 1999]);
    Product::factory()->create(['price' => 4549]);
    Product::factory()->create(['price' => 7999]);
    Product::factory()->inactive()->create(['price' => 99]);
    Product::factory()->inactive()->create(['price' => 99999]);

    $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonPath('data.price.min', ['amount' => 1999, 'formatted' => '€19.99'])
        ->assertJsonPath('data.price.max', ['amount' => 7999, 'formatted' => '€79.99'])
        ->assertJsonPath('data.currency', 'EUR');
});

it('returns a null price range and zero counts when there are no active products', function () {
    Product::factory()->inactive()->create();

    $response = $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonPath('data.price.min', null)
        ->assertJsonPath('data.price.max', null);

    expect(collect($response->json('data.platforms'))->sum('count'))->toBe(0);
});

it('is not shadowed by the product slug route', function () {
    Product::factory()->create(['slug' => 'filters']);

    $this->getJson('/api/v1/products/filters')
        ->assertOk()
        ->assertJsonStructure(['data' => ['platforms', 'regions', 'types', 'price', 'currency']]);
});
