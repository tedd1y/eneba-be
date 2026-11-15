<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\ProductType;
use App\Enums\Region;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Catalog of realistic titles, keyed by product type.
     *
     * @var array<string, list<array{0: string, 1: list<Platform>}>>
     */
    private const CATALOG = [
        'game' => [
            ['Elden Ring', [Platform::Steam, Platform::Xbox, Platform::PlayStation]],
            ['Cyberpunk 2077', [Platform::Steam, Platform::Gog, Platform::Xbox, Platform::PlayStation]],
            ['Red Dead Redemption 2', [Platform::Steam, Platform::Xbox, Platform::PlayStation]],
            ['Baldur\'s Gate 3', [Platform::Steam, Platform::Gog, Platform::PlayStation]],
            ['The Witcher 3: Wild Hunt', [Platform::Steam, Platform::Gog, Platform::Nintendo]],
            ['Hogwarts Legacy', [Platform::Steam, Platform::EpicGames, Platform::Xbox, Platform::PlayStation]],
            ['Grand Theft Auto V', [Platform::Steam, Platform::EpicGames, Platform::Xbox, Platform::PlayStation]],
            ['EA Sports FC 25', [Platform::EaApp, Platform::Xbox, Platform::PlayStation]],
            ['Assassin\'s Creed Shadows', [Platform::UbisoftConnect, Platform::Xbox, Platform::PlayStation]],
            ['Diablo IV', [Platform::BattleNet, Platform::Steam, Platform::Xbox]],
            ['Starfield', [Platform::Steam, Platform::Xbox]],
            ['Forza Horizon 5', [Platform::Steam, Platform::Xbox]],
            ['The Legend of Zelda: Tears of the Kingdom', [Platform::Nintendo]],
            ['Super Mario Bros. Wonder', [Platform::Nintendo]],
            ['Hades II', [Platform::Steam, Platform::EpicGames]],
            ['Stardew Valley', [Platform::Steam, Platform::Gog, Platform::Nintendo]],
            ['Hollow Knight', [Platform::Steam, Platform::Gog, Platform::Nintendo]],
            ['Sea of Thieves', [Platform::Steam, Platform::Xbox]],
            ['Call of Duty: Black Ops 6', [Platform::BattleNet, Platform::Steam, Platform::Xbox, Platform::PlayStation]],
            ['Helldivers 2', [Platform::Steam, Platform::PlayStation]],
            ['Palworld', [Platform::Steam, Platform::Xbox]],
            ['Black Myth: Wukong', [Platform::Steam, Platform::EpicGames, Platform::PlayStation]],
            ['Lies of P', [Platform::Steam, Platform::Xbox, Platform::PlayStation]],
            ['Far Cry 6', [Platform::UbisoftConnect, Platform::Xbox, Platform::PlayStation]],
        ],
        'dlc' => [
            ['Elden Ring: Shadow of the Erdtree', [Platform::Steam, Platform::Xbox, Platform::PlayStation]],
            ['Cyberpunk 2077: Phantom Liberty', [Platform::Steam, Platform::Gog, Platform::Xbox]],
            ['The Witcher 3: Blood and Wine', [Platform::Steam, Platform::Gog]],
            ['Forza Horizon 5: Rally Adventure', [Platform::Steam, Platform::Xbox]],
        ],
        'gift_card' => [
            ['Steam Gift Card', [Platform::Steam]],
            ['PlayStation Store Gift Card', [Platform::PlayStation]],
            ['Xbox Gift Card', [Platform::Xbox]],
            ['Nintendo eShop Card', [Platform::Nintendo]],
        ],
        'subscription' => [
            ['Xbox Game Pass Ultimate - 3 Months', [Platform::Xbox]],
            ['PlayStation Plus Essential - 12 Months', [Platform::PlayStation]],
            ['Nintendo Switch Online - 12 Months', [Platform::Nintendo]],
            ['EA Play - 12 Months', [Platform::EaApp]],
        ],
        'in_game_currency' => [
            ['Fortnite 2800 V-Bucks', [Platform::EpicGames]],
            ['Call of Duty Points 2400', [Platform::BattleNet]],
            ['Apex Legends 2150 Coins', [Platform::EaApp]],
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([
            ProductType::Game, ProductType::Game, ProductType::Game, ProductType::Game,
            ProductType::Dlc, ProductType::GiftCard, ProductType::Subscription, ProductType::InGameCurrency,
        ]);

        [$title, $platforms] = fake()->randomElement(self::CATALOG[$type->value]);
        $platform = fake()->randomElement($platforms);
        $region = fake()->randomElement(Region::cases());

        if ($type === ProductType::GiftCard) {
            $title .= ' '.fake()->randomElement([10, 20, 50, 100]).' EUR';
        }

        $price = $this->priceFor($type, $title);
        $originalPrice = fake()->boolean(65)
            ? (int) (round($price * fake()->randomFloat(2, 1.1, 2.5) / 100) * 100 - 1)
            : null;

        $slug = Str::slug("{$title} {$platform->label()} {$region->label()}")
            .'-'.fake()->unique()->numerify('#####');

        return [
            'title' => $title,
            'slug' => $slug,
            'platform' => $platform,
            'region' => $region,
            'type' => $type,
            'price' => $price,
            'original_price' => $originalPrice !== null && $originalPrice > $price ? $originalPrice : null,
            'cashback' => fake()->boolean(35) ? (int) round($price * fake()->randomFloat(2, 0.03, 0.10)) : null,
            'currency' => 'EUR',
            'image_url' => "https://picsum.photos/seed/{$slug}/460/215",
            'is_active' => true,
        ];
    }

    /**
     * Pick a realistic price in cents for the given product type.
     */
    private function priceFor(ProductType $type, string $title): int
    {
        if ($type === ProductType::GiftCard && preg_match('/(\d+) EUR$/', $title, $matches)) {
            // Gift cards usually sell slightly under face value.
            return (int) round((int) $matches[1] * 100 * fake()->randomFloat(2, 0.92, 0.99));
        }

        [$min, $max] = match ($type) {
            ProductType::Game => [499, 6999],
            ProductType::Dlc => [299, 3999],
            ProductType::Subscription => [1999, 7999],
            ProductType::InGameCurrency => [999, 2499],
            ProductType::GiftCard => [1000, 10000],
        };

        // Prices typically end in .99 or .49.
        return fake()->numberBetween(intdiv($min, 100), intdiv($max, 100)) * 100 + fake()->randomElement([49, 99]);
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
}
