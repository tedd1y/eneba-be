<?php

use App\Models\SideMenuItem;
use Database\Seeders\SideMenuItemSeeder;

it('returns active side menu items in display order', function () {
    SideMenuItem::factory()->create(['label' => 'GTA VI', 'href' => '/store/all?search=gta', 'sort_order' => 3]);
    SideMenuItem::factory()->create(['label' => 'Cheap Games', 'href' => '/store/all?types[]=game&sort=price', 'sort_order' => 1]);
    SideMenuItem::factory()->create(['label' => 'Software', 'href' => '/store/all?types[]=software', 'sort_order' => 2]);

    $this->getJson(route('v1.menus.sidemenu.index'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['label' => 'Cheap Games', 'href' => '/store/all?types[]=game&sort=price'],
                ['label' => 'Software', 'href' => '/store/all?types[]=software'],
                ['label' => 'GTA VI', 'href' => '/store/all?search=gta'],
            ],
        ]);
});

it('leaves out inactive items', function () {
    SideMenuItem::factory()->create(['label' => 'Visible']);
    SideMenuItem::factory()->inactive()->create(['label' => 'Hidden']);

    $this->getJson(route('v1.menus.sidemenu.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Visible');
});

it('keeps creation order for items with the same sort order', function () {
    SideMenuItem::factory()->create(['label' => 'Zeta', 'sort_order' => 1]);
    SideMenuItem::factory()->create(['label' => 'Alpha', 'sort_order' => 1]);

    expect($this->getJson(route('v1.menus.sidemenu.index'))->json('data.*.label'))->toBe(['Zeta', 'Alpha']);
});

it('returns an empty list when there are no active items', function () {
    SideMenuItem::factory()->inactive()->create();

    $this->getJson(route('v1.menus.sidemenu.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('returns every active item without pagination', function () {
    SideMenuItem::factory()->count(30)->create();

    $this->getJson(route('v1.menus.sidemenu.index'))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

it('loads the menu with a single query', function () {
    SideMenuItem::factory()->count(10)->create();

    expect(countQueries(fn () => $this->getJson(route('v1.menus.sidemenu.index'))->assertOk()))->toBe(1);
});

it('seeds the example side menu, and can be re-seeded safely', function () {
    $this->seed(SideMenuItemSeeder::class);
    $this->seed(SideMenuItemSeeder::class);

    $this->getJson(route('v1.menus.sidemenu.index'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                ['label' => 'Cheap Games', 'href' => '/store/all?types[]=game&sort=price'],
                ['label' => 'Software', 'href' => '/store/all?types[]=software'],
                ['label' => 'eGift Cards', 'href' => '/store/all?types[]=gift-card'],
                ['label' => 'Eneba Gift Cards', 'href' => '/store/all?search=eneba'],
                ['label' => 'FM27', 'href' => '/store/all?search=fm27'],
                ['label' => 'GTA VI', 'href' => '/store/all?search=gta'],
            ],
        ]);
});
