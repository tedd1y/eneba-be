<?php

use App\Models\HomeGallery;
use App\Models\Product;
use Database\Seeders\HomeGallerySeeder;
use Database\Seeders\ProductTypeSeeder;

it('returns active galleries in display order', function () {
    $upcoming = HomeGallery::factory()->create(['label' => 'Upcoming games', 'href' => '/store/all?sort=-created_at', 'limit' => 8, 'sort_order' => 3]);
    $recommended = HomeGallery::factory()->create(['label' => 'Recommended for you', 'href' => '/store/all?type[]=game', 'sort_order' => 1]);
    $top = HomeGallery::factory()->create(['label' => 'Top games', 'href' => '/store/all?sort=-price', 'limit' => 12, 'sort_order' => 2]);

    $this->getJson(route('v1.home.galleries.index'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['id' => $recommended->id, 'label' => 'Recommended for you', 'href' => '/store/all?type[]=game', 'limit' => 10],
                ['id' => $top->id, 'label' => 'Top games', 'href' => '/store/all?sort=-price', 'limit' => 12],
                ['id' => $upcoming->id, 'label' => 'Upcoming games', 'href' => '/store/all?sort=-created_at', 'limit' => 8],
            ],
        ]);
});

it('defaults the limit to 10', function () {
    $gallery = HomeGallery::query()->create(['label' => 'Top games', 'href' => '/store/all']);

    expect($gallery->limit)->toBe(10)
        ->and($gallery->fresh()->limit)->toBe(10);
});

it('leaves out inactive galleries', function () {
    HomeGallery::factory()->create(['label' => 'Visible']);
    HomeGallery::factory()->inactive()->create(['label' => 'Hidden']);

    $this->getJson(route('v1.home.galleries.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Visible');
});

it('keeps creation order for galleries with the same sort order', function () {
    HomeGallery::factory()->create(['label' => 'Zeta', 'sort_order' => 1]);
    HomeGallery::factory()->create(['label' => 'Alpha', 'sort_order' => 1]);

    expect($this->getJson(route('v1.home.galleries.index'))->json('data.*.label'))->toBe(['Zeta', 'Alpha']);
});

it('returns an empty list when there are no active galleries', function () {
    HomeGallery::factory()->inactive()->create();

    $this->getJson(route('v1.home.galleries.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('returns every active gallery without pagination', function () {
    HomeGallery::factory()->count(25)->create();

    $this->getJson(route('v1.home.galleries.index'))
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

it('loads the galleries with a single query', function () {
    HomeGallery::factory()->count(10)->create();

    expect(countQueries(fn () => $this->getJson(route('v1.home.galleries.index'))->assertOk()))->toBe(1);
});

it('seeds the example galleries, and can be re-seeded safely', function () {
    $this->seed(HomeGallerySeeder::class);
    $this->seed(HomeGallerySeeder::class);

    $this->getJson(route('v1.home.galleries.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.*.label', ['Recommended for you', 'Top games', 'Upcoming games'])
        ->assertJsonPath('data.*.limit', [10, 10, 8]);
});

it('seeds hrefs whose filters the products endpoint accepts', function () {
    $this->seed([ProductTypeSeeder::class, HomeGallerySeeder::class]);
    Product::factory()->count(3)->ofType('game')->create();

    foreach (HomeGallery::query()->ordered()->get() as $gallery) {
        parse_str((string) parse_url($gallery->href, PHP_URL_QUERY), $filters);

        $this->getJson(route('v1.products.index', [...$filters, 'per_page' => $gallery->limit]))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
});
