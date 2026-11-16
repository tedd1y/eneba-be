<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Activation regions, in display order.
     *
     * @var list<array{slug: string, name: string}>
     */
    private const ROWS = [
        ['slug' => 'global', 'name' => 'Global'],
        ['slug' => 'europe', 'name' => 'Europe'],
        ['slug' => 'north_america', 'name' => 'North America'],
        ['slug' => 'united_kingdom', 'name' => 'United Kingdom'],
        ['slug' => 'latin_america', 'name' => 'Latin America'],
        ['slug' => 'asia', 'name' => 'Asia'],
    ];

    /**
     * Seed the activation regions. Safe to run repeatedly; existing rows are updated by slug.
     */
    public function run(): void
    {
        Region::query()->upsert(
            collect(self::ROWS)->map(fn (array $row, int $index) => [...$row, 'sort_order' => $index + 1])->all(),
            uniqueBy: ['slug'],
            update: ['name', 'sort_order'],
        );
    }
}
