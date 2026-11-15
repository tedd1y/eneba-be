<?php

use App\Enums\Platform;
use App\Enums\ProductType;
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

it('does not include the description in list items', function () {
    Product::factory()->create(['description' => 'Long-form text for the product page.']);

    $this->getJson(route('v1.products.index'))
        ->assertOk()
        ->assertJsonMissingPath('data.0.description');
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

it('filters products by several platforms', function () {
    Product::factory()->create(['platform' => Platform::Steam]);
    Product::factory()->create(['platform' => Platform::Gog]);
    Product::factory()->create(['platform' => Platform::Xbox]);

    $response = $this->getJson(route('v1.products.index', ['platform' => ['steam', 'gog']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('platform.value')->sort()->values()->all())->toBe(['gog', 'steam']);
});

it('filters products by several regions', function () {
    Product::factory()->create(['region' => Region::Europe]);
    Product::factory()->create(['region' => Region::Global]);
    Product::factory()->create(['region' => Region::Asia]);

    $response = $this->getJson(route('v1.products.index', ['region' => ['europe', 'global']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('region.value')->sort()->values()->all())->toBe(['europe', 'global']);
});

it('filters products by type', function () {
    Product::factory()->count(2)->create(['type' => ProductType::Game]);
    Product::factory()->create(['type' => ProductType::Dlc]);
    Product::factory()->create(['type' => ProductType::GiftCard]);

    $this->getJson(route('v1.products.index', ['type' => 'game']))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson(route('v1.products.index', ['type' => ['dlc', 'gift_card']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters products by price range in cents, inclusive', function (array $query, array $expected) {
    foreach ([999, 1999, 2999, 4999] as $price) {
        Product::factory()->create(['price' => $price]);
    }

    $response = $this->getJson(route('v1.products.index', $query))->assertOk();

    expect(collect($response->json('data'))->pluck('price.amount')->sort()->values()->all())->toBe($expected);
})->with([
    'min only' => [['min_price' => 1999], [1999, 2999, 4999]],
    'max only' => [['max_price' => 1999], [999, 1999]],
    'min and max' => [['min_price' => 1999, 'max_price' => 2999], [1999, 2999]],
    'min equals max' => [['min_price' => 2999, 'max_price' => 2999], [2999]],
]);

it('combines array, type and price filters', function () {
    Product::factory()->create(['platform' => Platform::Steam, 'region' => Region::Global, 'type' => ProductType::Game, 'price' => 1999]);
    Product::factory()->create(['platform' => Platform::Gog, 'region' => Region::Europe, 'type' => ProductType::Game, 'price' => 2999]);
    Product::factory()->create(['platform' => Platform::Steam, 'region' => Region::Global, 'type' => ProductType::Dlc, 'price' => 1999]);
    Product::factory()->create(['platform' => Platform::Steam, 'region' => Region::Global, 'type' => ProductType::Game, 'price' => 5999]);
    Product::factory()->create(['platform' => Platform::Xbox, 'region' => Region::Global, 'type' => ProductType::Game, 'price' => 1999]);

    $this->getJson(route('v1.products.index', [
        'platform' => ['steam', 'gog'],
        'region' => ['global', 'europe'],
        'type' => ['game'],
        'min_price' => 1000,
        'max_price' => 3000,
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('rejects invalid filters', function (array $query, string $field) {
    $this->getJson(route('v1.products.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown platform' => [['platform' => 'sega'], 'platform.0'],
    'unknown platform in list' => [['platform' => ['steam', 'sega']], 'platform.1'],
    'unknown region' => [['region' => 'mars'], 'region.0'],
    'unknown type' => [['type' => ['bundle']], 'type.0'],
    'negative min_price' => [['min_price' => -1], 'min_price'],
    'non-integer max_price' => [['max_price' => '9.99'], 'max_price'],
    'max_price below min_price' => [['min_price' => 5000, 'max_price' => 1000], 'max_price'],
    'per_page too large' => [['per_page' => 500], 'per_page'],
    'search too long' => [['search' => str_repeat('a', 101)], 'search'],
]);
