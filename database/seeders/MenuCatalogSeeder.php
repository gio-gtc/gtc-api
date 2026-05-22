<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Broadcast & Streaming Video' => ['15s TV Commercial', '30s TV Commercial', '60s Cinema Trailer'],
            'Social Video' => ['Instagram Story Asset', 'TikTok Promo Video', 'YouTube Pre-Roll Bump'],
            'Audio' => ['Radio Single Commercial', 'Radio Dual Commercial', 'Spotify Audio Ad Loop'],
            'Key Art' => ['Billboard Master Layout', 'Digital Poster Kit', 'Social Media Graphics Print Sheet']
        ];

        foreach ($catalog as $categoryName => $items) {
            $category = MenuCategory::firstOrCreate(['name' => $categoryName]);

            foreach ($items as $itemName) {
                MenuItem::firstOrCreate(
                    ['name' => $itemName, 'menu_category_id' => $category->id],
                    ['default_price' => rand(150, 2200) . '.00']
                );
            }
        }
    }
}