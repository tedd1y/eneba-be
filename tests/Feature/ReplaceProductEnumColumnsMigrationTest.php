<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const ENUM_MIGRATION = 'database/migrations/2026_11_16_113946_replace_enum_columns_on_products_table.php';

/**
 * Insert a product row in the pre-migration shape, with string enum columns.
 */
function insertLegacyProduct(string $slug, string $platform, string $region, string $type): void
{
    DB::table('products')->insert([
        'title' => $slug,
        'slug' => $slug,
        'platform' => $platform,
        'region' => $region,
        'type' => $type,
        'price' => 999,
        'currency' => 'EUR',
        'image_url' => 'https://example.com/image.png',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    Artisan::call('migrate:rollback', ['--path' => ENUM_MIGRATION, '--force' => true]);
});

afterEach(function () {
    Artisan::call('migrate', ['--path' => ENUM_MIGRATION, '--force' => true]);
});

it('moves enum values into lookup tables and foreign keys', function () {
    insertLegacyProduct('first', 'epic_games', 'north_america', 'dlc');
    insertLegacyProduct('second', 'epic_games', 'global', 'in_game_currency');
    DB::table('platforms')->insert(['slug' => 'steam', 'name' => 'Steam', 'sort_order' => 0]);

    Artisan::call('migrate', ['--path' => ENUM_MIGRATION, '--force' => true]);

    expect(Schema::hasColumns('products', ['platform', 'region', 'type']))->toBeFalse()
        ->and(Schema::hasColumns('products', ['platform_id', 'region_id', 'type_id']))->toBeTrue()
        ->and(DB::table('platforms')->pluck('name', 'slug')->all())->toEqualCanonicalizing(['steam' => 'Steam', 'epic_games' => 'Epic Games'])
        ->and(DB::table('regions')->pluck('name', 'slug')->all())->toEqualCanonicalizing(['north_america' => 'North America', 'global' => 'Global'])
        ->and(DB::table('product_types')->pluck('name', 'slug')->all())->toEqualCanonicalizing(['dlc' => 'DLC', 'in_game_currency' => 'In-Game Currency']);

    $first = DB::table('products as p')
        ->join('platforms', 'platforms.id', '=', 'p.platform_id')
        ->join('regions', 'regions.id', '=', 'p.region_id')
        ->join('product_types', 'product_types.id', '=', 'p.type_id')
        ->where('p.slug', 'first')
        ->first(['platforms.slug as platform', 'regions.slug as region', 'product_types.slug as type']);

    expect((array) $first)->toBe(['platform' => 'epic_games', 'region' => 'north_america', 'type' => 'dlc']);
});

it('restores the enum columns on rollback', function () {
    insertLegacyProduct('first', 'gog', 'asia', 'game');

    Artisan::call('migrate', ['--path' => ENUM_MIGRATION, '--force' => true]);
    Artisan::call('migrate:rollback', ['--path' => ENUM_MIGRATION, '--force' => true]);

    expect(Schema::hasColumns('products', ['platform_id', 'region_id', 'type_id']))->toBeFalse()
        ->and((array) DB::table('products')->where('slug', 'first')->first(['platform', 'region', 'type']))
        ->toBe(['platform' => 'gog', 'region' => 'asia', 'type' => 'game']);
});
