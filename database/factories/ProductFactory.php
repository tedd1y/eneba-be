<?php

namespace Database\Factories;

use App\Models\Genre;
use App\Models\OperatingSystem;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Catalog of realistic products: title, product type slug, platform slugs it is sold for, genre slugs.
     *
     * @var list<array{0: string, 1: string, 2: list<string>, 3: list<string>}>
     */
    private const CATALOG = [
        ['Elden Ring', 'game', ['steam', 'xbox', 'playstation'], ['action', 'role-playing', 'open-world']],
        ['Cyberpunk 2077', 'game', ['steam', 'gog', 'xbox', 'playstation'], ['action', 'role-playing', 'open-world', 'shooter']],
        ['Red Dead Redemption 2', 'game', ['steam', 'xbox', 'playstation'], ['action', 'adventure', 'open-world']],
        ['Baldur\'s Gate 3', 'game', ['steam', 'gog', 'playstation'], ['role-playing', 'strategy', 'adventure']],
        ['The Witcher 3: Wild Hunt', 'game', ['steam', 'gog', 'nintendo'], ['role-playing', 'open-world', 'adventure']],
        ['Hogwarts Legacy', 'game', ['steam', 'epic_games', 'xbox', 'playstation'], ['action', 'adventure', 'open-world']],
        ['Grand Theft Auto V', 'game', ['steam', 'epic_games', 'xbox', 'playstation'], ['action', 'open-world', 'multiplayer']],
        ['EA Sports FC 25', 'game', ['ea_app', 'xbox', 'playstation'], ['sports', 'multiplayer']],
        ['Assassin\'s Creed Shadows', 'game', ['ubisoft_connect', 'xbox', 'playstation'], ['action', 'adventure', 'open-world']],
        ['Diablo IV', 'game', ['battle_net', 'steam', 'xbox'], ['action', 'role-playing', 'multiplayer']],
        ['Starfield', 'game', ['steam', 'xbox'], ['role-playing', 'open-world', 'adventure']],
        ['Forza Horizon 5', 'game', ['steam', 'xbox'], ['racing', 'open-world', 'sports']],
        ['The Legend of Zelda: Tears of the Kingdom', 'game', ['nintendo'], ['action', 'adventure', 'open-world']],
        ['Super Mario Bros. Wonder', 'game', ['nintendo'], ['platformer', 'adventure']],
        ['Hades II', 'game', ['steam', 'epic_games'], ['action', 'roguelike', 'indie']],
        ['Stardew Valley', 'game', ['steam', 'gog', 'nintendo'], ['simulation', 'indie']],
        ['Hollow Knight', 'game', ['steam', 'gog', 'nintendo'], ['platformer', 'action', 'indie']],
        ['Sea of Thieves', 'game', ['steam', 'xbox'], ['adventure', 'multiplayer']],
        ['Call of Duty: Black Ops 6', 'game', ['battle_net', 'steam', 'xbox', 'playstation'], ['shooter', 'action', 'multiplayer']],
        ['Helldivers 2', 'game', ['steam', 'playstation'], ['shooter', 'multiplayer']],
        ['Palworld', 'game', ['steam', 'xbox'], ['survival', 'open-world', 'multiplayer']],
        ['Black Myth: Wukong', 'game', ['steam', 'epic_games', 'playstation'], ['action', 'role-playing']],
        ['Lies of P', 'game', ['steam', 'xbox', 'playstation'], ['action', 'role-playing', 'horror']],
        ['Far Cry 6', 'game', ['ubisoft_connect', 'xbox', 'playstation'], ['shooter', 'open-world']],
        ['Elden Ring: Shadow of the Erdtree', 'dlc', ['steam', 'xbox', 'playstation'], ['action', 'role-playing']],
        ['Cyberpunk 2077: Phantom Liberty', 'dlc', ['steam', 'gog', 'xbox'], ['action', 'role-playing', 'shooter']],
        ['The Witcher 3: Blood and Wine', 'dlc', ['steam', 'gog'], ['role-playing', 'adventure']],
        ['Forza Horizon 5: Rally Adventure', 'dlc', ['steam', 'xbox'], ['racing']],
        ['Steam Gift Card', 'gift_card', ['steam'], []],
        ['PlayStation Store Gift Card', 'gift_card', ['playstation'], []],
        ['Xbox Gift Card', 'gift_card', ['xbox'], []],
        ['Nintendo eShop Card', 'gift_card', ['nintendo'], []],
        ['Xbox Game Pass Ultimate - 3 Months', 'subscription', ['xbox'], []],
        ['PlayStation Plus Essential - 12 Months', 'subscription', ['playstation'], []],
        ['Nintendo Switch Online - 12 Months', 'subscription', ['nintendo'], []],
        ['EA Play - 12 Months', 'subscription', ['ea_app'], []],
        ['Fortnite 2800 V-Bucks', 'in_game_currency', ['epic_games'], ['shooter', 'multiplayer']],
        ['Call of Duty Points 2400', 'in_game_currency', ['battle_net'], ['shooter']],
        ['Apex Legends 2150 Coins', 'in_game_currency', ['ea_app'], ['shooter', 'multiplayer']],
    ];

    /**
     * How often each product type is picked; games are the bulk of the catalog.
     *
     * @var array<string, int>
     */
    private const TYPE_WEIGHTS = ['game' => 4, 'dlc' => 1, 'gift_card' => 1, 'subscription' => 1, 'in_game_currency' => 1];

    /**
     * @var list<string>
     */
    private const REGIONS = ['global', 'europe', 'north_america', 'united_kingdom', 'latin_america', 'asia'];

    /**
     * Platforms whose keys run on a computer, and therefore list operating systems.
     *
     * @var list<string>
     */
    private const PC_PLATFORMS = ['steam', 'epic_games', 'gog', 'ea_app', 'ubisoft_connect', 'battle_net'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $typeSlug = fake()->randomElement(array_merge(...array_map(
            fn (string $slug, int $weight) => array_fill(0, $weight, $slug),
            array_keys(self::TYPE_WEIGHTS),
            self::TYPE_WEIGHTS,
        )));

        [$title, , $platformSlugs] = fake()->randomElement(
            array_values(array_filter(self::CATALOG, fn (array $entry) => $entry[1] === $typeSlug)),
        );

        if ($typeSlug === 'gift_card') {
            $title .= ' '.fake()->randomElement([10, 20, 50, 100]).' EUR';
        }

        return [
            'title' => $title,
            'slug' => fn (array $attributes) => Str::slug($attributes['title']).'-'.fake()->unique()->numerify('#####'),
            'type_id' => fn () => self::lookupId(ProductType::class, $typeSlug),
            'platform_id' => fn () => self::lookupId(Platform::class, fake()->randomElement($platformSlugs)),
            'region_id' => fn () => self::lookupId(Region::class, fake()->randomElement(self::REGIONS)),
            'description' => fn (array $attributes) => fake()->boolean(85) ? $this->descriptionFor($attributes) : null,
            'price' => fn (array $attributes) => $this->priceFor($attributes),
            'original_price' => fn (array $attributes) => $this->originalPriceFor($attributes['price']),
            'cashback' => fn (array $attributes) => fake()->boolean(35)
                ? (int) round($attributes['price'] * fake()->randomFloat(2, 0.03, 0.10))
                : null,
            'currency' => Product::DEFAULT_CURRENCY,
            'image_url' => fn (array $attributes) => "https://picsum.photos/seed/{$attributes['slug']}/460/215",
            'is_active' => true,
        ];
    }

    /**
     * Sell the product on the platform with the given slug, creating the platform if needed.
     */
    public function onPlatform(string $slug): static
    {
        return $this->state(fn (array $attributes) => ['platform_id' => self::lookupId(Platform::class, $slug)]);
    }

    /**
     * Restrict the product to the region with the given slug, creating the region if needed.
     */
    public function inRegion(string $slug): static
    {
        return $this->state(fn (array $attributes) => ['region_id' => self::lookupId(Region::class, $slug)]);
    }

    /**
     * Make the product the type with the given slug, creating the type if needed.
     */
    public function ofType(string $slug): static
    {
        return $this->state(fn (array $attributes) => ['type_id' => self::lookupId(ProductType::class, $slug)]);
    }

    /**
     * Attach the genres with the given slugs, creating any that are missing.
     *
     * @param  list<string>  $slugs
     */
    public function withGenres(array $slugs): static
    {
        return $this->afterCreating(fn (Product $product) => $product->genres()->attach(
            array_map(fn (string $slug) => self::lookupId(Genre::class, $slug), $slugs),
        ));
    }

    /**
     * Attach the operating systems with the given slugs, creating any that are missing.
     *
     * @param  list<string>  $slugs
     */
    public function withOperatingSystems(array $slugs): static
    {
        return $this->afterCreating(fn (Product $product) => $product->operatingSystems()->attach(
            array_map(fn (string $slug) => self::lookupId(OperatingSystem::class, $slug), $slugs),
        ));
    }

    /**
     * Attach the genres the catalog lists for the product's title, and operating systems for PC platforms.
     */
    public function withCatalogDetails(): static
    {
        return $this->afterCreating(function (Product $product) {
            $entry = collect(self::CATALOG)->first(fn (array $entry) => str_starts_with($product->title, $entry[0]));

            $product->genres()->attach(array_map(fn (string $slug) => self::lookupId(Genre::class, $slug), $entry[3] ?? []));

            $platformSlug = Platform::query()->whereKey($product->platform_id)->value('slug');

            if (in_array($platformSlug, self::PC_PLATFORMS, true)) {
                $systems = ['windows', ...array_filter(['macos', 'linux'], fn () => fake()->boolean(30))];

                $product->operatingSystems()->attach(
                    array_map(fn (string $slug) => self::lookupId(OperatingSystem::class, $slug), $systems),
                );
            }
        });
    }

    /**
     * Indicate that the product has no description.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => ['description' => null]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the product is on sale relative to an original price.
     */
    public function discounted(int $price, int $originalPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
            'original_price' => $originalPrice,
        ]);
    }

    /**
     * Find a lookup row by slug, or create it with a name derived from the slug.
     *
     * @param  class-string<Model>  $model
     */
    private static function lookupId(string $model, string $slug): int
    {
        return $model::query()->where('slug', $slug)->value('id')
            ?? $model::factory()->create(['slug' => $slug, 'name' => Str::headline($slug)])->getKey();
    }

    /**
     * Write a storefront-style description for the product being built.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function descriptionFor(array $attributes): string
    {
        $title = $attributes['title'];
        $type = ProductType::query()->whereKey($attributes['type_id'])->value('slug');
        $platform = Platform::query()->whereKey($attributes['platform_id'])->value('name');
        $region = Region::query()->whereKey($attributes['region_id'])->first(['slug', 'name']);

        $activation = $region?->slug === 'global'
            ? "This key can be activated worldwide on {$platform}."
            : "This key can only be activated on {$platform} accounts in {$region?->name}.";

        $intro = match ($type) {
            'dlc' => "{$title} expands the base game with new areas, quests, gear and challenges. The base game is required to play.",
            'gift_card' => "Top up your {$platform} wallet with {$title} and spend it on games, add-ons and more.",
            'subscription' => "{$title} gives you access to a rotating library of games, online multiplayer and member-only discounts.",
            'in_game_currency' => "{$title} delivers premium currency straight to your account for skins, passes and other in-game items.",
            default => fake()->randomElement([
                "{$title} drops you into a vast world full of secrets, tough choices and memorable characters.",
                "Experience {$title}, a critically acclaimed adventure with hours of story-driven gameplay.",
                "{$title} blends tight combat, rich exploration and a world that reacts to every decision you make.",
            ]),
        };

        return implode("\n\n", [
            $intro,
            $activation,
            'You will receive a digital code immediately after purchase. No physical item will be shipped.',
        ]);
    }

    /**
     * Pick a realistic price in cents for the product being built.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function priceFor(array $attributes): int
    {
        $type = ProductType::query()->whereKey($attributes['type_id'])->value('slug');

        if ($type === 'gift_card' && preg_match('/(\d+) EUR$/', $attributes['title'], $matches)) {
            // Gift cards usually sell slightly under face value.
            return (int) round((int) $matches[1] * 100 * fake()->randomFloat(2, 0.92, 0.99));
        }

        [$min, $max] = match ($type) {
            'dlc' => [299, 3999],
            'subscription' => [1999, 7999],
            'in_game_currency' => [999, 2499],
            default => [499, 6999],
        };

        // Prices typically end in .99 or .49.
        return fake()->numberBetween(intdiv($min, 100), intdiv($max, 100)) * 100 + fake()->randomElement([49, 99]);
    }

    /**
     * Sometimes give the product a higher pre-discount price ending in .99.
     */
    private function originalPriceFor(int $price): ?int
    {
        if (! fake()->boolean(65)) {
            return null;
        }

        $originalPrice = (int) (round($price * fake()->randomFloat(2, 1.1, 2.5) / 100) * 100 - 1);

        return $originalPrice > $price ? $originalPrice : null;
    }
}
