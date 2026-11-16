<?php

namespace Database\Seeders;

use App\Models\SideMenuItem;
use Illuminate\Database\Seeder;

class SideMenuItemSeeder extends Seeder
{
    /**
     * Side menu links, in display order.
     *
     * @var list<array{label: string, href: string}>
     */
    private const ROWS = [
        ['label' => 'Cheap Games', 'href' => '/store/all?types[]=game&sort=price'],
        ['label' => 'Software', 'href' => '/store/all?types[]=software'],
        ['label' => 'eGift Cards', 'href' => '/store/all?types[]=gift-card'],
        ['label' => 'Eneba Gift Cards', 'href' => '/store/all?search=eneba'],
        ['label' => 'FM27', 'href' => '/store/all?search=fm27'],
        ['label' => 'GTA VI', 'href' => '/store/all?search=gta'],
    ];

    /**
     * Seed the side menu. Safe to run repeatedly; existing items are updated by label.
     */
    public function run(): void
    {
        foreach (self::ROWS as $index => $row) {
            SideMenuItem::query()->updateOrCreate(
                ['label' => $row['label']],
                ['href' => $row['href'], 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }
}
