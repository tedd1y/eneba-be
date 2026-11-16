<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Activation platforms, in display order.
     *
     * @var list<array{slug: string, name: string, icon: string|null, is_featured: bool}>
     */
    private const ROWS = [
        ['slug' => 'steam', 'name' => 'Steam', 'icon' => 'steam', 'is_featured' => true],
        ['slug' => 'epic_games', 'name' => 'Epic Games', 'icon' => 'epic-games', 'is_featured' => false],
        ['slug' => 'gog', 'name' => 'GOG', 'icon' => 'gog', 'is_featured' => false],
        ['slug' => 'ea_app', 'name' => 'EA App', 'icon' => 'ea', 'is_featured' => false],
        ['slug' => 'ubisoft_connect', 'name' => 'Ubisoft Connect', 'icon' => 'ubisoft', 'is_featured' => false],
        ['slug' => 'battle_net', 'name' => 'Battle.net', 'icon' => 'battle-net', 'is_featured' => false],
        ['slug' => 'xbox', 'name' => 'Xbox', 'icon' => 'xbox', 'is_featured' => true],
        ['slug' => 'playstation', 'name' => 'PlayStation', 'icon' => 'playstation', 'is_featured' => true],
        ['slug' => 'nintendo', 'name' => 'Nintendo', 'icon' => 'nintendo', 'is_featured' => true],
    ];

    /**
     * Seed the activation platforms. Safe to run repeatedly; existing rows are updated by slug.
     */
    public function run(): void
    {
        Platform::query()->upsert(
            collect(self::ROWS)->map(fn (array $row, int $index) => [...$row, 'sort_order' => $index + 1])->all(),
            uniqueBy: ['slug'],
            update: ['name', 'icon', 'is_featured', 'sort_order'],
        );
    }
}
