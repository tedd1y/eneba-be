<?php

use App\Http\Requests\ListProductsRequest;
use App\Models\Country;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Region;

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
    Product::factory()->count(2)->onPlatform('steam')->create();
    Product::factory()->count(3)->onPlatform('xbox')->create();

    $this->getJson(route('v1.products.index', ['platform' => 'steam']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.platform.value', 'steam');
});

it('filters products by region', function () {
    Product::factory()->count(2)->inRegion('europe')->create();
    Product::factory()->count(3)->inRegion('global')->create();

    $this->getJson(route('v1.products.index', ['region' => 'europe']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.region.value', 'europe');
});

it('combines search, platform and region filters', function () {
    Product::factory()->onPlatform('gog')->inRegion('global')->create(['title' => 'Cyberpunk 2077']);
    Product::factory()->onPlatform('steam')->inRegion('global')->create(['title' => 'Cyberpunk 2077']);
    Product::factory()->onPlatform('gog')->inRegion('europe')->create(['title' => 'Cyberpunk 2077']);
    Product::factory()->onPlatform('gog')->inRegion('global')->create(['title' => 'Hades II']);

    $this->getJson(route('v1.products.index', ['search' => 'cyber', 'platform' => 'gog', 'region' => 'global']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.platform.value', 'gog')
        ->assertJsonPath('data.0.region.value', 'global');
});

it('filters products by several platforms', function () {
    Product::factory()->onPlatform('steam')->create();
    Product::factory()->onPlatform('gog')->create();
    Product::factory()->onPlatform('xbox')->create();

    $response = $this->getJson(route('v1.products.index', ['platform' => ['steam', 'gog']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('platform.value')->sort()->values()->all())->toBe(['gog', 'steam']);
});

it('filters products by several regions', function () {
    Product::factory()->inRegion('europe')->create();
    Product::factory()->inRegion('global')->create();
    Product::factory()->inRegion('asia')->create();

    $response = $this->getJson(route('v1.products.index', ['region' => ['europe', 'global']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('region.value')->sort()->values()->all())->toBe(['europe', 'global']);
});

it('filters products by type', function () {
    Product::factory()->count(2)->ofType('game')->create();
    Product::factory()->ofType('dlc')->create();
    Product::factory()->ofType('gift_card')->create();

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

it('accepts price filters up to the largest storable price', function () {
    Product::factory()->create(['price' => 1999]);

    $this->getJson(route('v1.products.index', ['min_price' => 0, 'max_price' => 2147483647]))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson(route('v1.products.index', ['min_price' => 2147483647]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('combines array, type and price filters', function () {
    Product::factory()->onPlatform('steam')->inRegion('global')->ofType('game')->create(['price' => 1999]);
    Product::factory()->onPlatform('gog')->inRegion('europe')->ofType('game')->create(['price' => 2999]);
    Product::factory()->onPlatform('steam')->inRegion('global')->ofType('dlc')->create(['price' => 1999]);
    Product::factory()->onPlatform('steam')->inRegion('global')->ofType('game')->create(['price' => 5999]);
    Product::factory()->onPlatform('xbox')->inRegion('global')->ofType('game')->create(['price' => 1999]);

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

it('sorts products by title ascending by default', function () {
    foreach (['Stardew Valley', 'Elden Ring', 'Hades II'] as $title) {
        Product::factory()->create(['title' => $title]);
    }

    $response = $this->getJson(route('v1.products.index'))->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe(['Elden Ring', 'Hades II', 'Stardew Valley']);
});

it('sorts products', function (string $sort, array $expected) {
    Product::factory()->create(['title' => 'Elden Ring', 'price' => 4999, 'created_at' => now()->subDays(3)]);
    Product::factory()->create(['title' => 'Stardew Valley', 'price' => 999, 'created_at' => now()->subDay()]);
    Product::factory()->create(['title' => 'Hades II', 'price' => 2499, 'created_at' => now()->subDays(2)]);

    $response = $this->getJson(route('v1.products.index', ['sort' => $sort]))->assertOk();

    expect(collect($response->json('data'))->pluck('title')->all())->toBe($expected);
})->with([
    'title ascending' => ['title', ['Elden Ring', 'Hades II', 'Stardew Valley']],
    'title descending' => ['-title', ['Stardew Valley', 'Hades II', 'Elden Ring']],
    'price ascending' => ['price', ['Stardew Valley', 'Hades II', 'Elden Ring']],
    'price descending' => ['-price', ['Elden Ring', 'Hades II', 'Stardew Valley']],
    'oldest first' => ['created_at', ['Elden Ring', 'Hades II', 'Stardew Valley']],
    'newest first' => ['-created_at', ['Stardew Valley', 'Hades II', 'Elden Ring']],
]);

it('keeps a stable order across pages when sort values tie', function () {
    Product::factory()->count(6)->create(['price' => 1999]);

    $first = $this->getJson(route('v1.products.index', ['sort' => 'price', 'per_page' => 3]))->json('data.*.id');
    $second = $this->getJson(route('v1.products.index', ['sort' => 'price', 'per_page' => 3, 'page' => 2]))->json('data.*.id');

    expect(array_intersect($first, $second))->toBeEmpty()
        ->and([...$first, ...$second])->toBe(Product::query()->orderBy('id')->pluck('id')->all());
});

it('combines sorting with filters', function () {
    Product::factory()->onPlatform('steam')->create(['price' => 999]);
    Product::factory()->onPlatform('steam')->create(['price' => 4999]);
    Product::factory()->onPlatform('xbox')->create(['price' => 9999]);

    $this->getJson(route('v1.products.index', ['platform' => ['steam'], 'sort' => '-price']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.price.amount', 4999)
        ->assertJsonPath('data.1.price.amount', 999);
});

it('filters products by ids', function () {
    [$first, , $third] = Product::factory()->count(3)->create()->all();

    $response = $this->getJson(route('v1.products.index', ['ids' => [$first->id, $third->id]]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing([$first->id, $third->id]);
});

it('accepts a single id', function () {
    $product = Product::factory()->create();
    Product::factory()->create();

    $this->getJson(route('v1.products.index', ['ids' => $product->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $product->id);
});

it('leaves out inactive and unknown ids', function () {
    $active = Product::factory()->create();
    $inactive = Product::factory()->inactive()->create();

    $this->getJson(route('v1.products.index', ['ids' => [$active->id, $inactive->id, 999999]]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);
});

it('combines ids with other filters and sorting', function () {
    $cheap = Product::factory()->onPlatform('steam')->create(['price' => 999]);
    $expensive = Product::factory()->onPlatform('steam')->create(['price' => 4999]);
    $xbox = Product::factory()->onPlatform('xbox')->create(['price' => 1999]);
    Product::factory()->onPlatform('steam')->create(['price' => 2999]);

    $this->getJson(route('v1.products.index', [
        'ids' => [$cheap->id, $expensive->id, $xbox->id],
        'platform' => ['steam'],
        'sort' => '-price',
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $expensive->id)
        ->assertJsonPath('data.1.id', $cheap->id);
});

it('accepts up to the maximum number of ids', function () {
    $ids = range(1, ListProductsRequest::MAX_IDS);

    $this->getJson(route('v1.products.index', ['ids' => $ids]))->assertOk();

    $this->getJson(route('v1.products.index', ['ids' => [...$ids, ListProductsRequest::MAX_IDS + 1]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');
});

it('filters products by country through the region\'s countries', function () {
    $lithuania = Country::factory()->create(['slug' => 'lt', 'name' => 'Lithuania']);
    $germany = Country::factory()->create(['slug' => 'de', 'name' => 'Germany']);
    $japan = Country::factory()->create(['slug' => 'jp', 'name' => 'Japan']);

    $global = Region::factory()->create(['slug' => 'global']);
    $global->countries()->attach([$lithuania->id, $germany->id, $japan->id]);
    $europe = Region::factory()->create(['slug' => 'europe']);
    $europe->countries()->attach([$lithuania->id, $germany->id]);
    $asia = Region::factory()->create(['slug' => 'asia']);
    $asia->countries()->attach($japan->id);

    $globalKey = Product::factory()->for($global)->create();
    $europeanKey = Product::factory()->for($europe)->create();
    Product::factory()->for($asia)->create();

    $response = $this->getJson(route('v1.products.index', ['country' => 'lt']))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing([$globalKey->id, $europeanKey->id]);

    $this->getJson(route('v1.products.index', ['country' => 'JP']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters products by genres, matching any of them', function () {
    $rpg = Product::factory()->withGenres(['role-playing', 'action'])->create();
    $shooter = Product::factory()->withGenres(['shooter'])->create();
    Product::factory()->withGenres(['racing'])->create();
    Product::factory()->create();

    $this->getJson(route('v1.products.index', ['genre' => 'action']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $rpg->id);

    $response = $this->getJson(route('v1.products.index', ['genre' => ['action', 'shooter']]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing([$rpg->id, $shooter->id]);
});

it('filters products by operating systems, matching any of them', function () {
    $crossPlatform = Product::factory()->withOperatingSystems(['windows', 'macos', 'linux'])->create();
    $windowsOnly = Product::factory()->withOperatingSystems(['windows'])->create();
    Product::factory()->create();

    $this->getJson(route('v1.products.index', ['os' => ['linux']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $crossPlatform->id);

    $response = $this->getJson(route('v1.products.index', ['os' => 'windows']))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing([$crossPlatform->id, $windowsOnly->id]);
});

it('does not duplicate products matching several genres or operating systems', function () {
    Product::factory()->withGenres(['action', 'shooter'])->withOperatingSystems(['windows', 'linux'])->create();

    $this->getJson(route('v1.products.index', ['genre' => ['action', 'shooter'], 'os' => ['windows', 'linux']]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1);
});

it('combines the new filters with the existing ones', function () {
    $lithuania = Country::factory()->create(['slug' => 'lt']);
    $europe = Region::factory()->create(['slug' => 'europe']);
    $europe->countries()->attach($lithuania->id);
    $asia = Region::factory()->create(['slug' => 'asia']);

    $match = Product::factory()->for($europe)->onPlatform('steam')->withGenres(['action'])->withOperatingSystems(['windows'])
        ->create(['price' => 1999]);
    Product::factory()->for($asia)->onPlatform('steam')->withGenres(['action'])->withOperatingSystems(['windows'])
        ->create(['price' => 1999]);
    Product::factory()->for($europe)->onPlatform('xbox')->withGenres(['action'])->create(['price' => 1999]);
    Product::factory()->for($europe)->onPlatform('steam')->withGenres(['action'])->withOperatingSystems(['windows'])
        ->create(['price' => 9999]);

    $this->getJson(route('v1.products.index', [
        'country' => 'lt',
        'platform' => ['steam'],
        'genre' => ['action'],
        'os' => ['windows'],
        'max_price' => 5000,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('rejects invalid filters', function (array $query, string $field) {
    Platform::factory()->create(['slug' => 'steam']);

    $this->getJson(route('v1.products.index', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'unknown platform' => [['platform' => 'sega'], 'platform.0'],
    'unknown platform in list' => [['platform' => ['steam', 'sega']], 'platform.1'],
    'unknown region' => [['region' => 'mars'], 'region.0'],
    'unknown type' => [['type' => ['bundle']], 'type.0'],
    'unknown country' => [['country' => 'zz'], 'country'],
    'country as a list' => [['country' => ['lt']], 'country'],
    'unknown genre' => [['genre' => ['dating-sim']], 'genre.0'],
    'unknown operating system' => [['os' => ['amiga']], 'os.0'],
    'negative min_price' => [['min_price' => -1], 'min_price'],
    'min_price above the price column range' => [['min_price' => 2147483648], 'min_price'],
    'max_price above the price column range' => [['max_price' => 3000000000], 'max_price'],
    'huge min_price' => [['min_price' => 99999999999], 'min_price'],
    'non-integer max_price' => [['max_price' => '9.99'], 'max_price'],
    'max_price below min_price' => [['min_price' => 5000, 'max_price' => 1000], 'max_price'],
    'non-integer id' => [['ids' => ['abc']], 'ids.0'],
    'zero id' => [['ids' => [0]], 'ids.0'],
    'negative id' => [['ids' => [5, -1]], 'ids.1'],
    'duplicate ids' => [['ids' => [5, 5]], 'ids.0'],
    'unknown sort' => [['sort' => 'popularity'], 'sort'],
    'per_page too large' => [['per_page' => 500], 'per_page'],
    'search too long' => [['search' => str_repeat('a', 101)], 'search'],
]);
