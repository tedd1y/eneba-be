<?php

use App\Enums\Platform;
use App\Enums\Region;
use App\Models\Product;

it('returns a paginated list of active products', function () {
    Product::factory()->count(25)->create();
    Product::factory()->inactive()->create();

    $this->getJson(route('v1.products.index'))
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonStructure([
            'data' => [[
                'id', 'title', 'slug',
                'platform' => ['value', 'label'],
                'region' => ['value', 'label'],
                'type' => ['value', 'label'],
                'price' => ['amount', 'formatted'],
                'original_price', 'cashback', 'discount_percent',
                'currency', 'image_url', 'created_at', 'updated_at',
            ]],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('respects the per_page parameter', function () {
    Product::factory()->count(8)->create();

    $this->getJson(route('v1.products.index', ['per_page' => 5, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

it('searches products by title', function () {
    Product::factory()->create(['title' => 'Elden Ring']);
    Product::factory()->create(['title' => 'Elden Ring: Shadow of the Erdtree']);
    Product::factory()->create(['title' => 'Stardew Valley']);

    $this->getJson(route('v1.products.index', ['search' => 'elden']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Elden Ring')
        ->assertJsonPath('data.1.title', 'Elden Ring: Shadow of the Erdtree');
});

it('filters products by platform', function () {
    Product::factory()->count(2)->create(['platform' => Platform::Steam]);
    Product::factory()->count(3)->create(['platform' => Platform::Xbox]);

    $this->getJson(route('v1.products.index', ['platform' => 'steam']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.platform.value', 'steam');
});

it('filters products by region', function () {
    Product::factory()->count(2)->create(['region' => Region::Europe]);
    Product::factory()->count(3)->create(['region' => Region::Global]);

    $this->getJson(route('v1.products.index', ['region' => 'europe']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.region.value', 'europe');
});

it('combines search, platform and region filters', function () {
    Product::factory()->create(['title' => 'Cyberpunk 2077', 'platform' => Platform::Gog, 'region' => Region::Global]);
    Product::factory()->create(['title' => 'Cyberpunk 2077', 'platform' => Platform::Steam, 'region' => Region::Global]);
    Product::factory()->create(['title' => 'Cyberpunk 2077', 'platform' => Platform::Gog, 'region' => Region::Europe]);
    Product::factory()->create(['title' => 'Hades II', 'platform' => Platform::Gog, 'region' => Region::Global]);

    $this->getJson(route('v1.products.index', ['search' => 'cyber', 'platform' => 'gog', 'region' => 'global']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.platform.value', 'gog')
        ->assertJsonPath('data.0.region.value', 'global');
});

it('rejects invalid filters', function (array $query, string $field) {
    $this->getJson(route('v1.products.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown platform' => [['platform' => 'sega'], 'platform'],
    'unknown region' => [['region' => 'mars'], 'region'],
    'per_page too large' => [['per_page' => 500], 'per_page'],
    'search too long' => [['search' => str_repeat('a', 101)], 'search'],
]);
