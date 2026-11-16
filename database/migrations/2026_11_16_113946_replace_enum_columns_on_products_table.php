<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Product enum columns and the lookup tables that replace them.
     *
     * @var array<string, array{table: string, foreign_key: string}>
     */
    private const COLUMNS = [
        'platform' => ['table' => 'platforms', 'foreign_key' => 'platform_id'],
        'region' => ['table' => 'regions', 'foreign_key' => 'region_id'],
        'type' => ['table' => 'product_types', 'foreign_key' => 'type_id'],
    ];

    /**
     * Names and order of the former enum cases, keyed by their stored value. Snapshotted here so the
     * migration does not depend on enum classes that no longer exist.
     *
     * @var array<string, array<string, string>>
     */
    private const NAMES = [
        'platforms' => [
            'steam' => 'Steam',
            'epic_games' => 'Epic Games',
            'gog' => 'GOG',
            'ea_app' => 'EA App',
            'ubisoft_connect' => 'Ubisoft Connect',
            'battle_net' => 'Battle.net',
            'xbox' => 'Xbox',
            'playstation' => 'PlayStation',
            'nintendo' => 'Nintendo',
        ],
        'regions' => [
            'global' => 'Global',
            'europe' => 'Europe',
            'north_america' => 'North America',
            'united_kingdom' => 'United Kingdom',
            'latin_america' => 'Latin America',
            'asia' => 'Asia',
        ],
        'product_types' => [
            'game' => 'Game',
            'dlc' => 'DLC',
            'gift_card' => 'Gift Card',
            'subscription' => 'Subscription',
            'in_game_currency' => 'In-Game Currency',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('platform_id')->nullable()->after('description')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->nullable()->after('platform_id')->constrained()->restrictOnDelete();
            $table->foreignId('type_id')->nullable()->after('region_id')->constrained('product_types')->restrictOnDelete();
        });

        foreach (self::COLUMNS as $column => ['table' => $table, 'foreign_key' => $foreignKey]) {
            $this->createLookupRows($column, $table);

            DB::table('products')->update([
                $foreignKey => DB::table($table)->select('id')->whereColumn("{$table}.slug", "products.{$column}")->limit(1),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['platform']);
            $table->dropIndex(['region']);
            $table->dropIndex(['type']);
            $table->dropColumn(['platform', 'region', 'type']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('platform_id')->nullable(false)->change();
            $table->foreignId('region_id')->nullable(false)->change();
            $table->foreignId('type_id')->nullable(false)->change();
            $table->index('platform_id');
            $table->index('region_id');
            $table->index('type_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('platform')->nullable()->after('description');
            $table->string('region')->nullable()->after('platform');
            $table->string('type')->nullable()->after('region');
        });

        foreach (self::COLUMNS as $column => ['table' => $table, 'foreign_key' => $foreignKey]) {
            DB::table('products')->update([
                $column => DB::table($table)->select('slug')->whereColumn("{$table}.id", "products.{$foreignKey}")->limit(1),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropForeign(['platform_id']);
            $table->dropForeign(['region_id']);
            $table->dropForeign(['type_id']);
            $table->dropIndex(['platform_id']);
            $table->dropIndex(['region_id']);
            $table->dropIndex(['type_id']);
            $table->dropColumn(['platform_id', 'region_id', 'type_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('platform')->nullable(false)->index()->change();
            $table->string('region')->nullable(false)->index()->change();
            $table->string('type')->nullable(false)->index()->change();
        });
    }

    /**
     * Create a lookup row for every distinct value already stored in the product column.
     */
    private function createLookupRows(string $column, string $table): void
    {
        $order = array_flip(array_keys(self::NAMES[$table]));
        $now = now();

        $rows = DB::table('products')->distinct()->pluck($column)
            ->reject(fn (?string $slug) => $slug === null || DB::table($table)->where('slug', $slug)->exists())
            ->map(fn (string $slug) => [
                'slug' => $slug,
                'name' => self::NAMES[$table][$slug] ?? Str::headline($slug),
                'sort_order' => $order[$slug] ?? count($order),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table($table)->insert($rows);
    }
};
