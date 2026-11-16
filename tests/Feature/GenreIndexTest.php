<?php

use App\Models\Genre;
use App\Models\Product;
use Database\Seeders\GenreSeeder;

it('returns every genre in display order', function () {
    Genre::factory()->create(['slug' => 'strategy', 'name' => 'Strategy', 'icon' => 'chess', 'is_featured' => false, 'sort_order' => 2]);
    Genre::factory()->create(['slug' => 'action', 'name' => 'Action', 'icon' => 'sword', 'is_featured' => true, 'sort_order' => 1]);
    Genre::factory()->create(['slug' => 'indie', 'name' => 'Indie', 'icon' => null, 'is_featured' => false, 'sort_order' => 3]);

    $this->getJson(route('v1.genres.index'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['value' => 'action', 'label' => 'Action', 'count' => 0, 'icon' => 'sword', 'is_featured' => true],
                ['value' => 'strategy', 'label' => 'Strategy', 'count' => 0, 'icon' => 'chess', 'is_featured' => false],
                ['value' => 'indie', 'label' => 'Indie', 'count' => 0, 'icon' => null, 'is_featured' => false],
            ],
        ]);
});

it('orders genres with the same sort order by name', function () {
    Genre::factory()->create(['slug' => 'shooter', 'name' => 'Shooter', 'sort_order' => 1]);
    Genre::factory()->create(['slug' => 'racing', 'name' => 'Racing', 'sort_order' => 1]);

    expect($this->getJson(route('v1.genres.index'))->json('data.*.value'))->toBe(['racing', 'shooter']);
});

it('counts active products in each genre', function () {
    Genre::factory()->create(['slug' => 'action', 'sort_order' => 1]);
    Genre::factory()->create(['slug' => 'shooter', 'sort_order' => 2]);
    Genre::factory()->create(['slug' => 'horror', 'sort_order' => 3]);

    Product::factory()->count(2)->withGenres(['action', 'shooter'])->create();
    Product::factory()->withGenres(['action'])->create();
    Product::factory()->inactive()->withGenres(['action', 'horror'])->create();
    Product::factory()->create();

    $counts = collect($this->getJson(route('v1.genres.index'))->assertOk()->json('data'))->pluck('count', 'value');

    expect($counts->all())->toBe(['action' => 3, 'shooter' => 2, 'horror' => 0]);
});

it('includes genres without any active products, with a zero count', function () {
    $action = Genre::factory()->create(['slug' => 'action']);
    Genre::factory()->create(['slug' => 'horror']);
    Product::factory()->inactive()->create()->genres()->attach($action);

    expect($this->getJson(route('v1.genres.index'))->json('data.*.count'))->toBe([0, 0])
        ->and($this->getJson(route('v1.genres.index'))->json('data.*.value'))->toEqualCanonicalizing(['action', 'horror']);
});

it('reports the same counts as the filters endpoint', function () {
    $this->seed(GenreSeeder::class);
    Product::factory()->count(12)->withCatalogDetails()->create();
    Product::factory()->count(3)->inactive()->withGenres(['action', 'indie'])->create();

    $genres = collect($this->getJson(route('v1.genres.index'))->json('data'))->pluck('count', 'value');
    $filterGenres = collect($this->getJson(route('v1.products.filters.index'))->json('data.genres'))->pluck('count', 'value');

    expect($genres->all())->toBe($filterGenres->all());
});

it('returns an empty list when there are no genres', function () {
    $this->getJson(route('v1.genres.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('returns every genre without pagination', function () {
    Genre::factory()->count(30)->create();

    $this->getJson(route('v1.genres.index'))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

it('loads the genres and their counts with a single query', function () {
    Genre::factory()->count(10)->create();
    Product::factory()->count(5)->withGenres(['action', 'shooter'])->create();

    expect(countQueries(fn () => $this->getJson(route('v1.genres.index'))->assertOk()))->toBe(1);
});

it('returns values the products genre filter accepts', function () {
    $this->seed(GenreSeeder::class);
    Product::factory()->withGenres(['action'])->create();

    $values = $this->getJson(route('v1.genres.index'))->assertOk()->assertJsonCount(15, 'data')->json('data.*.value');

    $this->getJson(route('v1.products.index', ['genre' => $values]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
