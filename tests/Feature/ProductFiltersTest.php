<?php

use App\Models\Country;
use App\Models\Genre;
use App\Models\OperatingSystem;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Region;

/**
 * Pluck `value => count` pairs from one option group of the filters response.
 *
 * @return array<string, int>
 */
function filterCounts(array $data, string $group): array
{
    return collect($data[$group])->pluck('count', 'value')->all();
}

it('returns every filter option group', function () {
    $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'platforms', 'regions', 'countries', 'types', 'genres', 'operating_systems', 'price', 'currency',
        ]]);
});

it('counts active products per platform, region and type, including empty options', function () {
    Product::factory()->count(2)->onPlatform('steam')->inRegion('europe')->ofType('game')->create();
    Product::factory()->onPlatform('xbox')->inRegion('global')->ofType('gift_card')->create();
    Product::factory()->inactive()->onPlatform('gog')->inRegion('asia')->ofType('dlc')->create();
    Platform::factory()->create(['slug' => 'nintendo', 'name' => 'Nintendo']);

    $data = $this->getJson(route('v1.products.filters.index'))->assertOk()->json('data');

    expect(filterCounts($data, 'platforms'))->toBe(['gog' => 0, 'nintendo' => 0, 'steam' => 2, 'xbox' => 1])
        ->and(filterCounts($data, 'regions'))->toMatchArray(['europe' => 2, 'global' => 1, 'asia' => 0])
        ->and(filterCounts($data, 'types'))->toMatchArray(['game' => 2, 'gift_card' => 1, 'dlc' => 0]);
});

it('counts active products per genre and operating system', function () {
    Product::factory()->withGenres(['action', 'role-playing'])->withOperatingSystems(['windows', 'linux'])->create();
    Product::factory()->withGenres(['action'])->withOperatingSystems(['windows'])->create();
    Product::factory()->inactive()->withGenres(['action', 'horror'])->withOperatingSystems(['macos'])->create();

    $data = $this->getJson(route('v1.products.filters.index'))->assertOk()->json('data');

    expect(filterCounts($data, 'genres'))->toMatchArray(['action' => 2, 'role-playing' => 1, 'horror' => 0])
        ->and(filterCounts($data, 'operating_systems'))->toMatchArray(['windows' => 2, 'linux' => 1, 'macos' => 0]);
});

it('counts active products per country through their region', function () {
    $lithuania = Country::factory()->create(['slug' => 'lt', 'name' => 'Lithuania']);
    $japan = Country::factory()->create(['slug' => 'jp', 'name' => 'Japan']);
    Country::factory()->create(['slug' => 'aq', 'name' => 'Antarctica']);

    $global = Region::factory()->create(['slug' => 'global']);
    $global->countries()->attach([$lithuania->id, $japan->id]);
    $europe = Region::factory()->create(['slug' => 'europe']);
    $europe->countries()->attach($lithuania->id);

    Product::factory()->for($global)->create();
    Product::factory()->count(2)->for($europe)->create();
    Product::factory()->inactive()->for($europe)->create();

    $data = $this->getJson(route('v1.products.filters.index'))->assertOk()->json('data');

    expect(filterCounts($data, 'countries'))->toBe(['aq' => 0, 'jp' => 1, 'lt' => 3]);
});

it('orders options by sort order, then name', function () {
    Genre::factory()->create(['slug' => 'shooter', 'name' => 'Shooter', 'sort_order' => 1]);
    Genre::factory()->create(['slug' => 'adventure', 'name' => 'Adventure', 'sort_order' => 2]);
    Genre::factory()->create(['slug' => 'action', 'name' => 'Action', 'sort_order' => 2]);
    Country::factory()->create(['slug' => 'lt', 'name' => 'Lithuania']);
    Country::factory()->create(['slug' => 'de', 'name' => 'Germany']);

    $data = $this->getJson(route('v1.products.filters.index'))->assertOk()->json('data');

    expect(array_column($data['genres'], 'value'))->toBe(['shooter', 'action', 'adventure'])
        ->and(array_column($data['countries'], 'value'))->toBe(['de', 'lt']);
});

it('includes icon and featured flags on platforms, types and genres only', function () {
    Platform::factory()->featured()->create(['slug' => 'steam', 'name' => 'Steam', 'icon' => 'steam']);
    ProductType::factory()->create(['slug' => 'dlc', 'name' => 'DLC', 'icon' => null]);
    Genre::factory()->featured()->create(['slug' => 'action', 'name' => 'Action', 'icon' => 'sword']);
    Region::factory()->create(['slug' => 'global', 'name' => 'Global']);
    OperatingSystem::factory()->create(['slug' => 'windows', 'name' => 'Windows']);
    Country::factory()->create(['slug' => 'lt', 'name' => 'Lithuania']);

    $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonPath('data.platforms.0', ['value' => 'steam', 'label' => 'Steam', 'count' => 0, 'icon' => 'steam', 'is_featured' => true])
        ->assertJsonPath('data.types.0', ['value' => 'dlc', 'label' => 'DLC', 'count' => 0, 'icon' => null, 'is_featured' => false])
        ->assertJsonPath('data.genres.0', ['value' => 'action', 'label' => 'Action', 'count' => 0, 'icon' => 'sword', 'is_featured' => true])
        ->assertJsonPath('data.regions.0', ['value' => 'global', 'label' => 'Global', 'count' => 0])
        ->assertJsonPath('data.operating_systems.0', ['value' => 'windows', 'label' => 'Windows', 'count' => 0])
        ->assertJsonPath('data.countries.0', ['value' => 'lt', 'label' => 'Lithuania', 'count' => 0]);
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
    Product::factory()->inactive()->withGenres(['action'])->create();

    $data = $this->getJson(route('v1.products.filters.index'))
        ->assertOk()
        ->assertJsonPath('data.price.min', null)
        ->assertJsonPath('data.price.max', null)
        ->json('data');

    foreach (['platforms', 'regions', 'types', 'genres'] as $group) {
        expect(array_sum(filterCounts($data, $group)))->toBe(0);
    }
});

it('is not shadowed by the product slug route', function () {
    Product::factory()->create(['slug' => 'filters']);

    $this->getJson('/api/v1/products/filters')
        ->assertOk()
        ->assertJsonStructure(['data' => ['platforms', 'regions', 'countries', 'types', 'genres', 'operating_systems', 'price', 'currency']]);
});
