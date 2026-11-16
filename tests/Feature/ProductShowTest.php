<?php

use App\Models\Genre;
use App\Models\OperatingSystem;
use App\Models\Product;

it('returns a product by slug', function () {
    $product = Product::factory()
        ->onPlatform('steam')
        ->inRegion('global')
        ->ofType('game')
        ->create([
            'title' => 'Baldur\'s Gate 3',
            'slug' => 'baldurs-gate-3-steam-global',
            'description' => "Gather your party and return to the Forgotten Realms.\n\nThis key can be activated worldwide on Steam.",
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
        ->assertJsonPath('data.description', "Gather your party and return to the Forgotten Realms.\n\nThis key can be activated worldwide on Steam.")
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

it('returns a null description when the product has none', function () {
    $product = Product::factory()->withoutDescription()->create();

    $this->getJson(route('v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.description', null);
});

it('returns genres and operating systems in display order', function () {
    $rpg = Genre::factory()->create(['slug' => 'role-playing', 'name' => 'Role-Playing', 'sort_order' => 2]);
    $action = Genre::factory()->create(['slug' => 'action', 'name' => 'Action', 'sort_order' => 1]);
    $linux = OperatingSystem::factory()->create(['slug' => 'linux', 'name' => 'Linux', 'sort_order' => 3]);
    $windows = OperatingSystem::factory()->create(['slug' => 'windows', 'name' => 'Windows', 'sort_order' => 1]);

    $product = Product::factory()->create();
    $product->genres()->attach([$rpg->id, $action->id]);
    $product->operatingSystems()->attach([$linux->id, $windows->id]);

    $this->getJson(route('v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.genres', [
            ['value' => 'action', 'label' => 'Action'],
            ['value' => 'role-playing', 'label' => 'Role-Playing'],
        ])
        ->assertJsonPath('data.operating_systems', [
            ['value' => 'windows', 'label' => 'Windows'],
            ['value' => 'linux', 'label' => 'Linux'],
        ]);
});

it('returns empty genre and operating system lists when there are none', function () {
    $product = Product::factory()->create();

    $this->getJson(route('v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.genres', [])
        ->assertJsonPath('data.operating_systems', []);
});

it('returns every list field plus the description, genres and operating systems', function () {
    $product = Product::factory()->withGenres(['action'])->withOperatingSystems(['windows'])->create();

    $listItem = $this->getJson(route('v1.products.index'))->json('data.0');
    $detail = $this->getJson(route('v1.products.show', $product))->json('data');

    $detailOnly = ['description', 'genres', 'operating_systems'];

    expect(array_keys($detail))->toBe([...array_keys($listItem), ...$detailOnly])
        ->and(collect($detail)->except($detailOnly)->all())->toBe($listItem);
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
