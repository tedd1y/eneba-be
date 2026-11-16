<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /**
     * Seed the lookup tables products are classified by. Safe to run repeatedly; rows are updated by slug.
     */
    public function run(): void
    {
        $this->call([
            PlatformSeeder::class,
            ProductTypeSeeder::class,
            RegionSeeder::class,
            CountrySeeder::class,
            GenreSeeder::class,
            OperatingSystemSeeder::class,
        ]);
    }
}
