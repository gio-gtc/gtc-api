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
                    'tags' => ['Audio'],
                    'billing_code' => 'Video',
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
                    ],
                    'pricing_matrix' => [
                        'first_cut_price'        => 575.00,
                        'additional_cut_price'   => 275.00,
                        'revision_price'         => 275.00,
                        'base_encoding_bundle'   => 250.00,
                        'base_encoding_limit'    => 2,
                        'additional_encoding'    => 75.00,
                    ]
                ]
            ],

            'Social Video' => [
                [
                    'name' => 'Social Platform Video Details',
                    'tags' => ['Audio'],
                    'billing_code' => 'Video',
                    'blueprint' => [
                        'types' => ['Social - 16:9', 'FB/IG Story', 'TikTok', 'Social Square', 'Social - 4:5'],
                        'cuts' => ['Pre Sale', 'On Sale Now', 'Evergreen', 'Sign Up Now'],
                        'card_holders' => ["Amex", "Citi"],
                        'durations' => [10, 15, 30],
                        'languages' => ['English', 'Spanish', 'French']
                    ],
                    'pricing_matrix' => [
                        'first_cut_price'        => 575.00,
                        'additional_cut_price'   => 275.00,
                        'revision_price'         => 275.00,
                        'base_encoding_bundle'   => 250.00,
                        'base_encoding_limit'    => 2,
                        'additional_encoding'    => 75.00,
                    ]
                ]
            ],

            'Radio' => [
                [
                    'name' => 'Radio Details',
                    'tags' => ['Audio'],
                    'billing_code' => 'Audio',
                    'blueprint' => [
                        'types' => ["Generic", "AmEx", "Verizon", "Citi", "International"],
                        'cuts' => ['Sign Up Now', 'Pre Sale', 'On Sale Now', 'Week of', 'Day Prior', 'Day of', 'International Radio Package'],
                        'durations' => [15, 30, 60],
                        'languages' => ['English', 'Spanish', 'French']
                    ],
                    'pricing_matrix' => [
                        'first_cut_price'        => 00.01,
                        'additional_cut_price'   => 00.01,
                        'revision_price'         => 00.01,
                        'base_encoding_bundle'   => 00.01,
                        'base_encoding_limit'    => 1,
                        'additional_encoding'    => 00.01,
                    ]
                ]
            ],

            'Key Art & Static Assets' => [
                [
                    'name' => 'Key Art & Static Assets Details',
                    'tags' => ['Art'],
                    'billing_code' => 'Static',
                    'blueprint' => [
                        'types' => ['Key Art Package', 'Socials & Web Banners', 'International Key art & Social Package']
                    ],
                    'pricing_matrix' => [
                        'first_cut_price'        => 00.01,
                        'additional_cut_price'   => 00.01,
                        'revision_price'         => 00.01,
                        'base_encoding_bundle'   => 00.01,
                        'base_encoding_limit'    => 1,
                        'additional_encoding'    => 00.01,
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
                        'billing_code'           => $item['billing_code'],
                        'pricing_matrix'         => $item['pricing_matrix'] ?? null,
                        'form_blueprint'         => $item['blueprint']
                    ]
                );
            }
        }
    }
}