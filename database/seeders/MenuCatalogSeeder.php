<?php

namespace Database\Seeders;

use App\Models\OrderMenuCategory;
use App\Models\OrderMenuItem;
use Illuminate\Database\Seeder;

class MenuCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $formCatalog = [
            'Broadcast & Streaming Video' => [
                [
                    'name' => 'Broadcast & Streaming Video Details',
                    'price' => 1200.00,
                    'tags' => ['Audio', 'Voice Over'],
                    'blueprint' => [
                        'encodings' => [
                            "H264-MP4 (Online or Venue)", 
                            "Station MP4 (Broadcast)", 
                            "Hulu", 
                            "Amazon", 
                            "Netflix", 
                            "Connect TV"
                        ],
                        'types' => [
                            'Generic' => [
                                'cuts'      => ['Sign Up Now', 'Pre Sale', 'On Sale Now', 'Week of', 'Day Prior', 'Day of', 'Superless', 'Sample'],
                                'durations' => [10, 15, 30],
                                'languages' => ['English', 'Spanish', 'French']
                            ],
                            'AmEx' => [
                                'cuts'      => ['Pre Sale', 'Now Through'],
                                'durations' => [15, 30],
                                'languages' => ['English', 'Spanish', 'French']
                            ],
                            'Verizon' => [
                                'cuts'      => ['Pre Sale', 'Now Through'],
                                'durations' => [15, 30],
                                'languages' => ['English', 'Spanish', 'French']
                            ],
                            'Citi' => [
                                'cuts'      => ['Pre Sale', 'Now Through'],
                                'durations' => [15, 30],
                                'languages' => ['English', 'Spanish', 'French']
                            ],
                            'International' => [
                                'cuts'      => ['International TV Package'],
                                'durations' => [30],
                                'languages' => ['English']
                            ]
                        ]
                    ]
                ]
            ],

            'Social Video' => [
                [
                    'name' => 'Social Platform Video Details',
                    'price' => 350.00,
                    'tags' => ['Audio', 'Voice Over'],
                    'blueprint' => [
                        'types' => ['Social - 16:9', 'FB/IG Story', 'TikTok', 'Social Square', 'Social - 4:5'],
                        'cuts' => ['Pre Sale', 'On Sale Now', 'Evergreen', 'Sign Up Now'],
                        'card_holders' => ["Amex", "Citi"],
                        'durations' => [10, 15, 30],
                        'languages' => ['English', 'Spanish', 'French']
                    ]
                ]
            ],

            'Radio' => [
                [
                    'name' => 'Radio Details',
                    'price' => 300.00,
                    'tags' => ['Voice Over'],
                    'blueprint' => [
                        'types' => ["Generic", "AmEx", "Verizon", "Citi", "International"],
                        'cuts' => ['Sign Up Now', 'Pre Sale', 'On Sale Now', 'Week of', 'Day Prior', 'Day of'],
                        'durations' => [15, 30, 60],
                        'languages' => ['English', 'Spanish', 'French']
                    ]
                ]
            ],

            'Key Art & Static Assets' => [
                [
                    'name' => 'Key Art & Static Assets Details',
                    'price' => 800.00,
                    'tags' => ['Art'],
                    'blueprint' => [
                        'types' => ['Key Art Package', 'Socials & Web Banners', 'International Key art & Social Package']
                    ]
                ]
            ]
        ];

        foreach ($formCatalog as $categoryName => $items) {
            $categoryTags = $items[0]['tags'] ?? [];

            $category = OrderMenuCategory::updateOrCreate(
                ['name' => $categoryName],
                ['required_tags' => $categoryTags]
            );

            foreach ($items as $item) {
                OrderMenuItem::updateOrCreate(
                    [
                        'order_menu_category_id' => $category->id,
                        'name'                   => $item['name']
                    ],
                    [
                        'default_price'          => $item['price'],
                        'form_blueprint'         => $item['blueprint']
                    ]
                );
            }
        }
    }
}