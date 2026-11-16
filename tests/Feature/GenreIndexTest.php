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
                ['value' => 'action', 'label' => 'Action', 'icon' => 'sword', 'is_featured' => true],
                ['value' => 'strategy', 'label' => 'Strategy', 'icon' => 'chess', 'is_featured' => false],
                ['value' => 'indie', 'label' => 'Indie', 'icon' => null, 'is_featured' => false],
            ],
        ]);
});

it('orders genres with the same sort order by name', function () {
    Genre::factory()->create(['slug' => 'shooter', 'name' => 'Shooter', 'sort_order' => 1]);
    Genre::factory()->create(['slug' => 'racing', 'name' => 'Racing', 'sort_order' => 1]);

    expect($this->getJson(route('v1.genres.index'))->json('data.*.value'))->toBe(['racing', 'shooter']);
});

it('includes genres without any active products', function () {
    $action = Genre::factory()->create(['slug' => 'action']);
    Genre::factory()->create(['slug' => 'horror']);
    Product::factory()->inactive()->create()->genres()->attach($action);

    expect($this->getJson(route('v1.genres.index'))->json('data.*.value'))->toEqualCanonicalizing(['action', 'horror']);
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

it('loads the genres with a single query', function () {
    Genre::factory()->count(10)->create();

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
