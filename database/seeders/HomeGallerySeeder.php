<?php

namespace Database\Seeders;

use App\Models\HomeGallery;
use Illuminate\Database\Seeder;

class HomeGallerySeeder extends Seeder
{
    /**
     * Home page galleries, in display order. Each `href` is the storefront "See all" link, using the
     * same filters as `GET /api/v1/products`.
     *
     * @var list<array{label: string, href: string, limit: int}>
     */
    private const ROWS = [
        ['label' => 'Recommended for you', 'href' => '/store/all?type[]=game&sort=title', 'limit' => 10],
        ['label' => 'Top games', 'href' => '/store/all?type[]=game&sort=-price', 'limit' => 10],
        ['label' => 'Upcoming games', 'href' => '/store/all?type[]=game&sort=-created_at', 'limit' => 8],
    ];

    /**
     * Seed the home galleries. Safe to run repeatedly; existing galleries are updated by label.
     */
    public function run(): void
    {
        foreach (self::ROWS as $index => $row) {
            HomeGallery::query()->updateOrCreate(
                ['label' => $row['label']],
                ['href' => $row['href'], 'limit' => $row['limit'], 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
