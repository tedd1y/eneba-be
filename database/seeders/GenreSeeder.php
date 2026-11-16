<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    /**
     * Genres, in display order.
     *
     * @var list<array{slug: string, name: string, icon: string|null, is_featured: bool}>
     */
    private const ROWS = [
        ['slug' => 'action', 'name' => 'Action', 'icon' => 'sword', 'is_featured' => true],
        ['slug' => 'adventure', 'name' => 'Adventure', 'icon' => 'compass', 'is_featured' => true],
        ['slug' => 'role-playing', 'name' => 'Role-Playing', 'icon' => 'scroll', 'is_featured' => true],
        ['slug' => 'shooter', 'name' => 'Shooter', 'icon' => 'crosshair', 'is_featured' => true],
        ['slug' => 'strategy', 'name' => 'Strategy', 'icon' => 'chess', 'is_featured' => false],
        ['slug' => 'simulation', 'name' => 'Simulation', 'icon' => 'gear', 'is_featured' => false],
        ['slug' => 'sports', 'name' => 'Sports', 'icon' => 'ball', 'is_featured' => false],
        ['slug' => 'racing', 'name' => 'Racing', 'icon' => 'flag', 'is_featured' => false],
        ['slug' => 'open-world', 'name' => 'Open World', 'icon' => 'globe', 'is_featured' => false],
        ['slug' => 'survival', 'name' => 'Survival', 'icon' => 'tent', 'is_featured' => false],
        ['slug' => 'horror', 'name' => 'Horror', 'icon' => 'ghost', 'is_featured' => false],
        ['slug' => 'platformer', 'name' => 'Platformer', 'icon' => null, 'is_featured' => false],
        ['slug' => 'roguelike', 'name' => 'Roguelike', 'icon' => null, 'is_featured' => false],
        ['slug' => 'indie', 'name' => 'Indie', 'icon' => null, 'is_featured' => false],
        ['slug' => 'multiplayer', 'name' => 'Multiplayer', 'icon' => 'users', 'is_featured' => false],
    ];

    /**
     * Seed the genres. Safe to run repeatedly; existing rows are updated by slug.
     */
    public function run(): void
    {
        Genre::query()->upsert(
            collect(self::ROWS)->map(fn (array $row, int $index) => [...$row, 'sort_order' => $index + 1])->all(),
            uniqueBy: ['slug'],
            update: ['name', 'icon', 'is_featured', 'sort_order'],
        );
    }
}
