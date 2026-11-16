<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production only gets the reference data the storefront needs (taxonomy, side menu, home galleries), all of
     * which is safe to re-seed. Other environments also get a test user, demo products and orders, which rely on
     * factories and Faker (a dev-only dependency).
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->call([
                TaxonomySeeder::class,
                SideMenuItemSeeder::class,
                HomeGallerySeeder::class,
            ]);

            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            TaxonomySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            SideMenuItemSeeder::class,
            HomeGallerySeeder::class,
        ]);
    }
}
