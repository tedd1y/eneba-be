<?php

namespace Database\Seeders;

use App\Models\OperatingSystem;
use Illuminate\Database\Seeder;

class OperatingSystemSeeder extends Seeder
{
    /**
     * Operating systems, in display order.
     *
     * @var list<array{slug: string, name: string}>
     */
    private const ROWS = [
        ['slug' => 'windows', 'name' => 'Windows'],
        ['slug' => 'macos', 'name' => 'macOS'],
        ['slug' => 'linux', 'name' => 'Linux'],
    ];

    /**
     * Seed the operating systems. Safe to run repeatedly; existing rows are updated by slug.
     */
    public function run(): void
    {
        OperatingSystem::query()->upsert(
            collect(self::ROWS)->map(fn (array $row, int $index) => [...$row, 'sort_order' => $index + 1])->all(),
            uniqueBy: ['slug'],
            update: ['name', 'sort_order'],
        );
    }
}
