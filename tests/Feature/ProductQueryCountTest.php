<?php

use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Count the database queries the given callback runs.
 */
function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

/**
 * Create a product with its own platform, region, type, genres and operating systems, so nothing is shared.
 */
function createUniqueProduct(int $index): Product
{
    return Product::factory()
        ->onPlatform("platform-{$index}")
        ->inRegion("region-{$index}")
        ->ofType("type-{$index}")
        ->withGenres(["genre-{$index}-a", "genre-{$index}-b"])
        ->withOperatingSystems(["os-{$index}"])
        ->create();
}

it('prevents lazy loading outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();

    Product::factory()->count(2)->create();

    expect(fn () => Product::query()->get()->first()->platform)
        ->toThrow(LazyLoadingViolationException::class);
});

it('lists products with a constant number of queries', function () {
    createUniqueProduct(0);

    $baseline = countQueries(fn () => $this->getJson(route('v1.products.index'))->assertOk()->assertJsonCount(1, 'data'));

    foreach (range(1, 15) as $index) {
        createUniqueProduct($index);
    }

    $queries = countQueries(fn () => $this->getJson(route('v1.products.index'))->assertOk()->assertJsonCount(16, 'data'));

    expect($queries)->toBe($baseline)
        ->and($queries)->toBeLessThanOrEqual(5);
});

it('lists products filtered by country, genre and operating system with a constant number of queries', function () {
    $lithuania = Country::factory()->create(['slug' => 'lt']);
    $query = ['country' => 'lt', 'genre' => ['action'], 'os' => ['windows']];

    $createMatching = function () use ($lithuania) {
        $region = Region::factory()->create();
        $region->countries()->attach($lithuania->id);

        Product::factory()->for($region)->withGenres(['action'])->withOperatingSystems(['windows'])->create();
    };

    $createMatching();
    $baseline = countQueries(fn () => $this->getJson(route('v1.products.index', $query))->assertOk()->assertJsonCount(1, 'data'));

    foreach (range(1, 10) as $index) {
        $createMatching();
    }

    $queries = countQueries(fn () => $this->getJson(route('v1.products.index', $query))->assertOk()->assertJsonCount(11, 'data'));

    expect($queries)->toBe($baseline);
});

it('shows a product with a constant number of queries, however many genres and operating systems it has', function () {
    $bare = Product::factory()->create();
    $baseline = countQueries(fn () => $this->getJson(route('v1.products.show', $bare))->assertOk());

    $loaded = Product::factory()
        ->withGenres(['action', 'adventure', 'role-playing', 'shooter', 'strategy', 'horror'])
        ->withOperatingSystems(['windows', 'macos', 'linux'])
        ->create();

    $queries = countQueries(fn () => $this->getJson(route('v1.products.show', $loaded))
        ->assertOk()
        ->assertJsonCount(6, 'data.genres')
        ->assertJsonCount(3, 'data.operating_systems'));

    expect($queries)->toBe($baseline);
});

it('builds the filter options with a constant number of queries', function () {
    createUniqueProduct(0);
    $baseline = countQueries(fn () => $this->getJson(route('v1.products.filters.index'))->assertOk());

    foreach (range(1, 15) as $index) {
        createUniqueProduct($index);
        Country::factory()->create()->regions()->attach(Region::query()->inRandomOrder()->value('id'));
    }

    $queries = countQueries(fn () => $this->getJson(route('v1.products.filters.index'))->assertOk());

    expect($queries)->toBe($baseline)
        ->and($queries)->toBeLessThanOrEqual(8);
});
