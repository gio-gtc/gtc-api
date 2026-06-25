<?php

namespace App\Services\Pricing;

use App\Models\Order;
use App\Models\OrderItemBillingReference;
use App\Support\OrderItemBillingReference as SupportOrderItemBillingReference;
use Illuminate\Support\Collection;

class VideoPricingCalculator
{
    /**
     * Calculate all lines and costs for video items within an order.
     */
    public function calculate(Order $order, Collection $videoItems): array
    {
        $lines = [];
        $uniqueCutsTracked = [];
        $globalEncodingPlatforms = collect();

        // Pass 1: Build Primary Asset Cut & Revision Rows
        foreach ($videoItems as $item) {
            $menuItem = $item->orderMenuItem;
            $matrix = $menuItem?->pricing_matrix ?? [];
            $description = SupportOrderItemBillingReference::fromOrderItem($item);
            $spec = $item->specifiable;

            if (!$spec) {
                continue;
            }

            // A. Revision Scanner: Pulls price from DB matrix row if item is an asset correction loop
            if (!empty($spec->isci) && preg_match('/R\d+$/i', $spec->isci)) {
                $unitPrice = (float) ($matrix['revision_price'] ?? 275.00);
                $description = 'Revision';
            } 
            // B. Unique Cut Engine: Pulls first vs additional cut prices straight from matrix parameters
            else {
                $cutSignature = implode('-', [
                    $spec->type ?? 'default',
                    $spec->duration_seconds ?? $spec->duration ?? '0',
                    $spec->language ?? 'English'
                ]);

                if (!in_array($cutSignature, $uniqueCutsTracked)) {
                    $unitPrice = (float) ($matrix['first_cut_price'] ?? 575.00);
                    $description = "First Cut: {$description}";
                    $uniqueCutsTracked[] = $cutSignature;
                } else {
                    $unitPrice = (float) ($matrix['additional_cut_price'] ?? 275.00);
                    $description = "Additional Cut: {$description}";
                }
            }

            $lines[] = [
                'order_item_id' => $item->id,
                'description'   => $description,
                'unit_price'    => $unitPrice,
                'quantity'      => 1,
                'total'         => $unitPrice,
            ];

            // Accumulate delivery targets into our global order calculation pool
            if (isset($spec->encoding) && is_array($spec->encoding)) {
                foreach ($spec->encoding as $platform) {
                    $globalEncodingPlatforms->push($platform);
                }
            }
        }

        // Pass 2: Build Order-Wide Bundled Encoding Breakouts
        $totalUniqueEncodings = $globalEncodingPlatforms->unique()->count();
        $primaryVideoItem = $videoItems->first();

        if ($primaryVideoItem && $totalUniqueEncodings > 0) {
            $videoMatrix = $primaryVideoItem->orderMenuItem?->pricing_matrix ?? [];
            $baseBundlePrice = (float) ($videoMatrix['base_encoding_bundle'] ?? 250.00);
            $additionalPrice = (float) ($videoMatrix['additional_encoding'] ?? 75.00);

            if ($totalUniqueEncodings === 1) {
                $lines[] = [
                    'order_item_id' => $primaryVideoItem->id,
                    'description'   => 'Encoding',
                    'unit_price'    => $baseBundlePrice,
                    'quantity'      => 1,
                    'total'         => $baseBundlePrice,
                ];
            } else {
                // Break out the 2-pack bundle into itemized lines ($250 and $0) as requested
                $lines[] = [
                    'order_item_id' => $primaryVideoItem->id,
                    'description'   => 'Encoding',
                    'unit_price'    => $baseBundlePrice,
                    'quantity'      => 1,
                    'total'         => $baseBundlePrice,
                ];
                $lines[] = [
                    'order_item_id' => $primaryVideoItem->id,
                    'description'   => 'Encoding',
                    'unit_price'    => 0.00,
                    'quantity'      => 1,
                    'total'         => 0.00,
                ];

                // Append any overflow items at the standard additional matrix unit price
                if ($totalUniqueEncodings > 2) {
                    $extraCount = $totalUniqueEncodings - 2;
                    for ($i = 0; $i < $extraCount; $i++) {
                        $lines[] = [
                            'order_item_id' => $primaryVideoItem->id,
                            'description'   => 'Encoding',
                            'unit_price'    => $additionalPrice,
                            'quantity'      => 1,
                            'total'         => $additionalPrice,
                        ];
                    }
                }
            }
        }

        return $lines;
    }
}