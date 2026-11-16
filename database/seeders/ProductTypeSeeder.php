<?php

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    /**
     * Product types, in display order.
     *
     * @var list<array{slug: string, name: string, icon: string|null, is_featured: bool}>
     */
    private const ROWS = [
        ['slug' => 'game', 'name' => 'Game', 'icon' => 'gamepad', 'is_featured' => true],
        ['slug' => 'dlc', 'name' => 'DLC', 'icon' => 'puzzle', 'is_featured' => false],
        ['slug' => 'gift_card', 'name' => 'Gift Card', 'icon' => 'gift', 'is_featured' => true],
        ['slug' => 'subscription', 'name' => 'Subscription', 'icon' => 'calendar', 'is_featured' => false],
        ['slug' => 'in_game_currency', 'name' => 'In-Game Currency', 'icon' => 'coins', 'is_featured' => false],
    ];

    /**
     * Seed the product types. Safe to run repeatedly; existing rows are updated by slug.
     */
    public function run(): void
    {
        ProductType::query()->upsert(
            collect(self::ROWS)->map(fn (array $row, int $index) => [...$row, 'sort_order' => $index + 1])->all(),
            uniqueBy: ['slug'],
            update: ['name', 'icon', 'is_featured', 'sort_order'],
        );
    }
}
